<?php

namespace AIJOH\RateLimit;

/**
 * ファイルシステム上にレート制限カウンタを保存する Store 実装。
 *
 * - 1 キー = 1 ファイル: {storage_dir}/{sha1(key)}.json
 * - flock(LOCK_EX) で TOCTOU 競合回避
 * - スライディングウィンドウ方式（タイムスタンプ列を保持）
 */
class FileStore extends RateLimitStore {

    public function __construct(
        private readonly string $storageDir,
    ) {
        if ( ! is_dir($this->storageDir) ) {
            // 0700: ディレクトリ自体は所有者のみアクセス可
            @mkdir($this->storageDir, 0700, true);
        }
    }


    public function countWithin( string $key, int $windowSec ) : int {
        $now = $this->now();
        $threshold = $now - $windowSec;
        $timestamps = $this->readTimestamps($key);
        $count = 0;
        foreach ( $timestamps as $ts ) {
            if ( $ts >= $threshold ) {
                $count++;
            }
        }
        return $count;
    }


    public function record( string $key ) : void {
        $path = $this->getPath($key);
        $fp = fopen($path, 'c+');
        if ( $fp === false ) {
            return;
        }
        try {
            if ( ! flock($fp, LOCK_EX) ) {
                return;
            }
            $contents = stream_get_contents($fp);
            $data = json_decode($contents, true);
            $timestamps = is_array($data['ts'] ?? null) ? $data['ts'] : [];
            $now = $this->now();
            $timestamps[] = $now;

            // 古い記録をある程度刈り込んでファイル膨張を防ぐ
            // （24時間より古い ts は捨てる。各ルールの window はこれ以下を想定）
            $cutoff = $now - 86400;
            $timestamps = array_values(array_filter($timestamps, fn($t) => $t >= $cutoff));

            $newContents = json_encode(['ts' => $timestamps]);
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $newContents);
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }


    /**
     * flock(LOCK_EX) の範囲内で「件数判定 + 記録」をアトミックに行う。
     * countWithin / record を別ステップで呼ぶと並列リクエスト間で TOCTOU 競合が発生し、
     * 上限を超過するため、checkAndRecord 経由でのみ正しいレート制限が保証される。
     */
    public function checkAndRecord( string $key, int $windowSec, int $limit ) : bool {
        $path = $this->getPath($key);
        $fp = fopen($path, 'c+');
        if ( $fp === false ) {
            // ファイルが開けないときは fail-open ( ストレージ障害でアプリを止めない )
            return true;
        }
        try {
            if ( ! flock($fp, LOCK_EX) ) {
                return true;
            }
            try {
                $contents = stream_get_contents($fp);
                $data = json_decode($contents, true);
                $timestamps = is_array($data['ts'] ?? null) ? $data['ts'] : [];

                $now = $this->now();
                $threshold = $now - $windowSec;

                // 件数を数える ( 同じループで cutoff を兼ねるためフィルタも兼用 )
                $count = 0;
                foreach ( $timestamps as $ts ) {
                    if ( $ts >= $threshold ) {
                        $count++;
                    }
                }

                if ( $count >= $limit ) {
                    // 上限到達: 記録せず false を返す ( count を再呼出されても結果は同じ )
                    return false;
                }

                $timestamps[] = $now;

                // ファイル膨張防止: 24h より古いタイムスタンプを切り捨てる
                $cutoff = $now - 86400;
                $timestamps = array_values(array_filter($timestamps, fn($t) => $t >= $cutoff));

                $newContents = json_encode(['ts' => $timestamps]);
                ftruncate($fp, 0);
                rewind($fp);
                fwrite($fp, $newContents);
                fflush($fp);
                return true;
            } finally {
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
    }


    /**
     * 古い記録ファイルを掃除する（cron などから呼び出す想定）。
     */
    public function gc( int $maxAgeSec = 86400 ) : int {
        $deleted = 0;
        $now = $this->now();
        $files = glob($this->storageDir . '/*.json') ?: [];
        foreach ( $files as $file ) {
            if ( filemtime($file) < $now - $maxAgeSec ) {
                @unlink($file);
                $deleted++;
            }
        }
        return $deleted;
    }


    private function readTimestamps( string $key ) : array {
        $path = $this->getPath($key);
        if ( ! is_file($path) ) {
            return [];
        }
        $contents = @file_get_contents($path);
        if ( $contents === false ) {
            return [];
        }
        $data = json_decode($contents, true);
        return is_array($data['ts'] ?? null) ? $data['ts'] : [];
    }


    private function getPath( string $key ) : string {
        return $this->storageDir . '/' . sha1($key) . '.json';
    }

}

<?php

namespace AIJOH\RateLimit;

/**
 * レート制限カウンタの永続化を抽象化する。
 *
 * 主 API は checkAndRecord() で、count + record を原子的に行う。
 * 旧 API の countWithin() / record() は別ステップで動くため TOCTOU 競合があり、
 * 並列リクエストで上限を超過する。新規実装は checkAndRecord() を override すること。
 */
abstract class RateLimitStore {

    /**
     * 直近 $windowSec 秒以内に $key で記録された件数を返す。
     *
     * @deprecated checkAndRecord() を使うこと。単独利用は TOCTOU 競合を含む
     */
    abstract public function countWithin( string $key, int $windowSec ) : int;


    /**
     * 現在時刻を $key のカウンタに追記する。
     *
     * @deprecated checkAndRecord() を使うこと。単独利用は TOCTOU 競合を含む
     */
    abstract public function record( string $key ) : void;


    /**
     * 直近 $windowSec 秒以内の件数が $limit 未満なら現在時刻を記録して true、
     * すでに上限に達していたら record せず false を返す。
     *
     * 実装は count と record をアトミック ( ストレージ層のロック内 ) に行う必要がある。
     * デフォルト実装は後方互換のため countWithin() + record() の組み合わせだが、
     * これは TOCTOU 競合を含む。並列性が問題になるストアは override すること。
     */
    public function checkAndRecord( string $key, int $windowSec, int $limit ) : bool {
        $count = $this->countWithin($key, $windowSec);
        if ( $count >= $limit ) {
            return false;
        }
        $this->record($key);
        return true;
    }


    /**
     * 現在時刻（unix epoch、秒）を返す。テスト時に上書きできるよう分離。
     */
    public function now() : int {
        return time();
    }

}

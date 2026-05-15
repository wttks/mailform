<?php

namespace AIJOH\Test\RateLimit;

use AIJOH\Http\DevBypass;
use AIJOH\RateLimit\FileStore;
use AIJOH\RateLimit\RateLimit;
use AIJOH\RateLimit\RateLimitStore;
use PHPUnit\Framework\TestCase;

/**
 * 旧 API ( countWithin + record ) のみを実装したテスト用 Store。
 * 並列リクエストで全員が同じ古い snapshot を見てから record する状況を再現するため、
 * countWithin は snapshot を、record は実カウンタを更新する。
 *
 * これは「旧 API には TOCTOU が残っている」ことの確認用 ( = 新 API checkAndRecord を
 * override せず使うとどうなるか ) であり、RateLimit::check() が呼ぶ checkAndRecord は
 * RateLimitStore のデフォルト実装 ( countWithin + record の組合せ ) を経由するため、
 * SnapshotStore に対しては TOCTOU が再現する。
 */
class SnapshotStore extends RateLimitStore {
    /** @var array<string, int[]> 実カウンタ ( record で増える ) */
    public array $records = [];
    /** @var array<string, int[]> countWithin が参照するスナップショット */
    public array $snapshot = [];
    public int $fakeNow = 1000;

    public function now() : int { return $this->fakeNow; }

    public function countWithin( string $key, int $windowSec ) : int {
        $threshold = $this->fakeNow - $windowSec;
        return count(array_filter($this->snapshot[ $key ] ?? [], fn($t) => $t >= $threshold));
    }

    public function record( string $key ) : void {
        $this->records[ $key ][] = $this->fakeNow;
    }
}


/**
 * checkAndRecord を atomic に override したテスト用 Store。
 * 「修正後の正しい実装」相当で、count と record を 1 ステップで処理する。
 */
class AtomicMemoryStore extends RateLimitStore {
    /** @var array<string, int[]> */
    public array $records = [];
    public int $fakeNow = 1000;

    public function now() : int { return $this->fakeNow; }

    public function countWithin( string $key, int $windowSec ) : int {
        $threshold = $this->fakeNow - $windowSec;
        return count(array_filter($this->records[ $key ] ?? [], fn($t) => $t >= $threshold));
    }

    public function record( string $key ) : void {
        $this->records[ $key ][] = $this->fakeNow;
    }

    public function checkAndRecord( string $key, int $windowSec, int $limit ) : bool {
        $threshold = $this->fakeNow - $windowSec;
        $count = count(array_filter($this->records[ $key ] ?? [], fn($t) => $t >= $threshold));
        if ( $count >= $limit ) {
            return false;
        }
        $this->records[ $key ][] = $this->fakeNow;
        return true;
    }
}


/**
 * RateLimit の TOCTOU 修正を検証するテスト。
 *
 * - 旧 API ( SnapshotStore ) では現状の TOCTOU 競合がデフォルト実装に残ること
 * - 新 API ( AtomicMemoryStore / FileStore ) では並列リクエストでも上限を超えないこと
 *   を確認する。
 */
class RateLimitTocTouTest extends TestCase {

    protected function setUp() : void {
        RateLimit::reset();
        DevBypass::reset();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_POST = [];
    }

    protected function tearDown() : void {
        RateLimit::reset();
        DevBypass::reset();
        $_POST = [];
    }


    /**
     * SnapshotStore は旧 API のみ実装しているため、RateLimitStore のデフォルト
     * checkAndRecord ( countWithin + record ) を経由する。snapshot が古いまま固定
     * された状態 = 並列リクエストが「同じ古い件数」を見る状況 = TOCTOU を再現する。
     *
     * これは修正後も残る「旧 API のリスク」を文書化するためのテスト。
     */
    public function test_旧API_SnapshotStoreでは依然TOCTOUが残る(): void {
        $store = new SnapshotStore();
        RateLimit::configure([
            'enabled'     => true,
            'storage_dir' => '/tmp/dummy',
            'endpoints'   => ['submit' => [
                ['key' => 'ip', 'limit' => 3, 'window' => 60],
            ]],
        ]);
        RateLimit::setStoreForTest($store);

        // snapshot は空のまま固定 = 並列リクエストが全員 count=0 を見る
        $results = [];
        for ( $i = 0; $i < 5; $i++ ) {
            $results[] = RateLimit::check('submit');
        }

        // limit=3 なのに 5 件すべて通る ( 旧 API では TOCTOU が残るため )
        $this->assertSame([true, true, true, true, true], $results);
        $this->assertCount(5, $store->records['submit:ip:w60:203.0.113.1'] ?? []);
    }


    /**
     * AtomicMemoryStore は checkAndRecord を override して count と record を
     * 1 ステップで行うため、5 連続呼び出ししても limit=3 を超過しない。
     */
    public function test_新API_AtomicMemoryStoreでは上限超過しない(): void {
        $store = new AtomicMemoryStore();
        RateLimit::configure([
            'enabled'     => true,
            'storage_dir' => '/tmp/dummy',
            'endpoints'   => ['submit' => [
                ['key' => 'ip', 'limit' => 3, 'window' => 60],
            ]],
        ]);
        RateLimit::setStoreForTest($store);

        $results = [];
        for ( $i = 0; $i < 5; $i++ ) {
            $results[] = RateLimit::check('submit');
        }

        // limit=3 なので最初の 3 件のみ true、残り 2 件は false
        $this->assertSame([true, true, true, false, false], $results);
        $this->assertCount(3, $store->records['submit:ip:w60:203.0.113.1'] ?? []);
    }


    /**
     * pcntl_fork で本物の並列プロセス + FileStore で TOCTOU が解消されたことを確認する。
     * checkAndRecord の flock 範囲で count + record がアトミックに行われるため、
     * 並列実行でも limit を超えない。
     */
    public function test_並列プロセスで上限を超えない_FileStore_pcntl(): void {
        if ( ! function_exists('pcntl_fork') ) {
            $this->markTestSkipped('pcntl extension が必要');
        }

        $dir = sys_get_temp_dir() . '/mailform_ratelimit_atomic_' . uniqid();
        mkdir($dir, 0700, true);
        $resultFile = $dir . '/results.txt';
        touch($resultFile);

        $concurrency = 10;
        $limit = 3;

        $barrierFile = $dir . '/barrier';

        $children = [];
        for ( $i = 0; $i < $concurrency; $i++ ) {
            $pid = pcntl_fork();
            if ( $pid === 0 ) {
                while ( ! file_exists($barrierFile) ) {
                    usleep(1000);
                }

                RateLimit::reset();
                RateLimit::configure([
                    'enabled'     => true,
                    'storage_dir' => $dir,
                    'endpoints'   => ['submit' => [
                        ['key' => 'ip', 'limit' => $limit, 'window' => 60],
                    ]],
                ]);
                $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

                $ok = RateLimit::check('submit');
                file_put_contents(
                    $resultFile,
                    ( $ok ? '1' : '0' ) . "\n",
                    FILE_APPEND | LOCK_EX
                );
                exit(0);
            }
            $children[] = $pid;
        }

        usleep(50000);
        touch($barrierFile);

        foreach ( $children as $pid ) {
            pcntl_waitpid($pid, $status);
        }

        $results = file($resultFile, FILE_IGNORE_NEW_LINES);
        $passed = count(array_filter($results, fn($r) => trim($r) === '1'));

        // cleanup
        foreach ( glob($dir . '/*') as $f ) { @unlink($f); }
        @rmdir($dir);

        // 並列でも limit を超えない
        $this->assertLessThanOrEqual(
            $limit,
            $passed,
            "並列 {$concurrency} プロセス中 {$passed} 件通過 ( limit={$limit} を超えないこと )"
        );
        // 少なくとも limit 件は通る ( fail-open しすぎていない )
        $this->assertSame($limit, $passed,
            "checkAndRecord は limit 件ちょうど通すべき");
    }

}

<?php

namespace AIJOH\Test\RateLimit;

use AIJOH\Http\DevBypass;
use AIJOH\RateLimit\RateLimit;
use AIJOH\RateLimit\RateLimitStore;
use PHPUnit\Framework\TestCase;

/**
 * メモリ上で動作するテスト用 Store。タイムスタンプを直接保持する。
 */
class MemoryStore extends RateLimitStore {
    /** @var array<string, int[]> */
    public array $records = [];
    public int $fakeNow = 1000;

    public function now() : int { return $this->fakeNow; }

    public function countWithin( string $key, int $windowSec ) : int {
        $threshold = $this->fakeNow - $windowSec;
        return count(array_filter($this->records[$key] ?? [], fn($t) => $t >= $threshold));
    }

    public function record( string $key ) : void {
        $this->records[$key][] = $this->fakeNow;
    }
}


class RateLimitTest extends TestCase {

    private MemoryStore $store;

    protected function setUp(): void {
        RateLimit::reset();
        DevBypass::reset();
        $this->store = new MemoryStore();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_POST = [];
    }

    protected function tearDown(): void {
        RateLimit::reset();
        DevBypass::reset();
        $_POST = [];
    }

    private function configure( array $endpointRules, bool $enabled = true ) : void {
        RateLimit::configure([
            'enabled'     => $enabled,
            'storage_dir' => '/tmp/dummy',  // MemoryStore で上書きするので使わない
            'endpoints'   => ['submit' => $endpointRules],
        ]);
        RateLimit::setStoreForTest($this->store);
    }

    // ---- 通常動作 ----

    public function test_check_上限内なら_true_かつ_カウント増加(): void {
        $this->configure([['key' => 'ip', 'limit' => 3, 'window' => 60]]);

        $this->assertTrue(RateLimit::check('submit'));
        $this->assertTrue(RateLimit::check('submit'));
        $this->assertTrue(RateLimit::check('submit'));
        $this->assertSame(3, $this->store->countWithin('submit:ip:203.0.113.1', 60));
    }

    public function test_check_上限超過なら_false_かつ_カウント増加しない(): void {
        $this->configure([['key' => 'ip', 'limit' => 2, 'window' => 60]]);

        $this->assertTrue(RateLimit::check('submit'));
        $this->assertTrue(RateLimit::check('submit'));
        $this->assertFalse(RateLimit::check('submit'));
        // 上限を超えたら新規記録はしない
        $this->assertSame(2, $this->store->countWithin('submit:ip:203.0.113.1', 60));
    }

    // ---- 設定無効 ----

    public function test_disabled_なら_常に_true_かつ_記録しない(): void {
        $this->configure([['key' => 'ip', 'limit' => 1, 'window' => 60]]);
        RateLimit::disable();

        $this->assertTrue(RateLimit::check('submit'));
        $this->assertTrue(RateLimit::check('submit'));
        $this->assertSame(0, $this->store->countWithin('submit:ip:203.0.113.1', 60));
    }

    public function test_enabled_false_なら_常に_true(): void {
        $this->configure([['key' => 'ip', 'limit' => 1, 'window' => 60]], false);

        $this->assertTrue(RateLimit::check('submit'));
        $this->assertTrue(RateLimit::check('submit'));
    }

    public function test_設定が無いエンドポイントは_常に_true(): void {
        $this->configure([['key' => 'ip', 'limit' => 1, 'window' => 60]]);

        $this->assertTrue(RateLimit::check('not_configured'));
    }

    // ---- dev_bypass（IP ホワイトリストの代替）----

    public function test_dev_bypass_一致でレート制限を通過_カウント増加なし(): void {
        $this->configure([['key' => 'ip', 'limit' => 1, 'window' => 60]]);
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => ['rate_limit'],
            'match'   => ['email' => ['qa@example.com']],
            'expires_at' => '2099-12-31',
        ]);

        $data = ['email' => 'qa@example.com'];
        $this->assertTrue(RateLimit::check('submit', $data));
        $this->assertTrue(RateLimit::check('submit', $data));
        $this->assertTrue(RateLimit::check('submit', $data));
        // バイパス時はカウンタを増やさない
        $this->assertSame(0, $this->store->countWithin('submit:ip:203.0.113.1', 60));
    }

    public function test_dev_bypass_不一致なら通常評価(): void {
        $this->configure([['key' => 'ip', 'limit' => 1, 'window' => 60]]);
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => ['rate_limit'],
            'match'   => ['email' => ['qa@example.com']],
            'expires_at' => '2099-12-31',
        ]);

        $data = ['email' => 'random@example.com'];
        $this->assertTrue(RateLimit::check('submit', $data));
        $this->assertFalse(RateLimit::check('submit', $data));
    }

    public function test_dev_bypass_data省略時は_POSTを読む(): void {
        $this->configure([['key' => 'ip', 'limit' => 1, 'window' => 60]]);
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => ['rate_limit'],
            'match'   => ['email' => ['qa@example.com']],
            'expires_at' => '2099-12-31',
        ]);
        $_POST = ['email' => 'qa@example.com'];

        $this->assertTrue(RateLimit::check('submit'));
        $this->assertTrue(RateLimit::check('submit'));
        $this->assertSame(0, $this->store->countWithin('submit:ip:203.0.113.1', 60));
    }

    // ---- X-Forwarded-For ----

    public function test_X_Forwarded_For_の先頭IP_を採用(): void {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 172.20.0.3';
        $this->configure([['key' => 'ip', 'limit' => 5, 'window' => 60]]);

        RateLimit::check('submit');
        $this->assertSame(1, $this->store->countWithin('submit:ip:198.51.100.1', 60));
    }

    // ---- 複数ルール ----

    public function test_複数ルール_どれか1つでも超過すれば_false(): void {
        $this->configure([
            ['key' => 'ip', 'limit' => 100, 'window' => 60],
            ['key' => 'ip', 'limit' => 2,   'window' => 3600],
        ]);

        $this->assertTrue(RateLimit::check('submit'));
        $this->assertTrue(RateLimit::check('submit'));
        // 短期は OK だが長期 (limit=2) を超えるので false
        $this->assertFalse(RateLimit::check('submit'));
    }

}

<?php

namespace AIJOH\Test\RateLimit;

use AIJOH\RateLimit\RateLimit;
use AIJOH\RateLimit\RateLimitEndpoint;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/RateLimitTest.php';  // MemoryStore を流用

/**
 * RateLimit が enum と文字列の両方を受け付けることを検証する。
 */
class RateLimitEnumTest extends TestCase {

    private MemoryStore $store;

    protected function setUp(): void {
        RateLimit::reset();
        $this->store = new MemoryStore();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        RateLimit::configure([
            'enabled' => true,
            'storage_dir' => '/tmp/dummy',
            'whitelist_ips' => [],
            'endpoints' => [
                'submit'   => [['key' => 'ip', 'limit' => 2, 'window' => 60]],
                'validate' => [['key' => 'ip', 'limit' => 5, 'window' => 60]],
            ],
        ]);
        RateLimit::setStoreForTest($this->store);
    }

    protected function tearDown(): void {
        RateLimit::reset();
    }

    public function test_check_は_enum_を受け付ける(): void {
        $this->assertTrue(RateLimit::check(RateLimitEndpoint::Submit));
        $this->assertTrue(RateLimit::check(RateLimitEndpoint::Submit));
        $this->assertFalse(RateLimit::check(RateLimitEndpoint::Submit));  // 上限到達
    }

    public function test_check_は_文字列も受け付ける_互換維持(): void {
        $this->assertTrue(RateLimit::check('submit'));
        $this->assertTrue(RateLimit::check('submit'));
        $this->assertFalse(RateLimit::check('submit'));
    }

    public function test_enum_と_文字列で_同じカウンタが共有される(): void {
        RateLimit::check(RateLimitEndpoint::Submit);    // 1
        RateLimit::check('submit');                      // 2
        $this->assertFalse(RateLimit::check('submit'));  // 3 = false
    }

}

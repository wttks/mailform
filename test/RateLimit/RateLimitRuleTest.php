<?php

namespace AIJOH\Test\RateLimit;

use AIJOH\RateLimit\RateLimitRule;
use PHPUnit\Framework\TestCase;

class RateLimitRuleTest extends TestCase {

    public function test_fromConfig_設定配列から_インスタンスを生成(): void {
        $rule = RateLimitRule::fromConfig(['key' => 'ip', 'limit' => 5, 'window' => 60]);
        $this->assertSame('ip', $rule->keyType);
        $this->assertSame(5, $rule->limit);
        $this->assertSame(60, $rule->windowSec);
    }

    public function test_fromConfig_欠落キーは_デフォルト値で補完(): void {
        $rule = RateLimitRule::fromConfig([]);
        $this->assertSame('ip', $rule->keyType);
        $this->assertSame(0, $rule->limit);
        $this->assertSame(0, $rule->windowSec);
    }

    public function test_fromConfig_文字列の数値も_int_に変換(): void {
        $rule = RateLimitRule::fromConfig(['key' => 'session', 'limit' => '10', 'window' => '120']);
        $this->assertSame(10, $rule->limit);
        $this->assertSame(120, $rule->windowSec);
    }

}

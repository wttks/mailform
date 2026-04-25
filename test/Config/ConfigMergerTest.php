<?php

namespace AIJOH\Test\Config;

use AIJOH\Config\ConfigMerger;
use PHPUnit\Framework\TestCase;

class ConfigMergerTest extends TestCase {

    public function test_空のマージは空配列(): void {
        $this->assertSame([], ConfigMerger::merge());
    }

    public function test_単一の配列はそのまま返す(): void {
        $this->assertSame(['a' => 1], ConfigMerger::merge(['a' => 1]));
    }

    public function test_浅いキーは後勝ち(): void {
        $r = ConfigMerger::merge(
            ['a' => 1, 'b' => 2],
            ['b' => 3, 'c' => 4],
        );
        $this->assertSame(['a' => 1, 'b' => 3, 'c' => 4], $r);
    }

    public function test_連想配列は再帰マージ(): void {
        $r = ConfigMerger::merge(
            ['rate_limit' => ['enabled' => true, 'storage_dir' => '/a']],
            ['rate_limit' => ['enabled' => false]],
        );
        $this->assertSame(
            ['rate_limit' => ['enabled' => false, 'storage_dir' => '/a']],
            $r,
        );
    }

    public function test_深い連想配列も再帰マージ(): void {
        $r = ConfigMerger::merge(
            ['ai' => ['provider' => 'cli', 'options' => ['timeout' => 30, 'retry' => 3]]],
            ['ai' => ['options' => ['timeout' => 60]]],
        );
        $this->assertSame(
            ['ai' => ['provider' => 'cli', 'options' => ['timeout' => 60, 'retry' => 3]]],
            $r,
        );
    }

    public function test_リスト_数値キー_は完全置換(): void {
        $r = ConfigMerger::merge(
            ['whitelist_ips' => ['127.0.0.1', '::1', '172.20.0.0/16']],
            ['whitelist_ips' => []],
        );
        $this->assertSame(['whitelist_ips' => []], $r);
    }

    public function test_リスト_の置換は空でなくても(): void {
        $r = ConfigMerger::merge(
            ['allowed' => ['a', 'b', 'c']],
            ['allowed' => ['x']],
        );
        $this->assertSame(['allowed' => ['x']], $r);
    }

    public function test_3つ以上のマージも順次後勝ち(): void {
        $r = ConfigMerger::merge(
            ['a' => 1],
            ['a' => 2, 'b' => 2],
            ['b' => 3, 'c' => 3],
        );
        $this->assertSame(['a' => 2, 'b' => 3, 'c' => 3], $r);
    }

    public function test_新規キーは追加(): void {
        $r = ConfigMerger::merge(
            ['ai' => ['provider' => 'cli']],
            ['ai_spam' => ['enabled' => true]],
        );
        $this->assertSame(
            ['ai' => ['provider' => 'cli'], 'ai_spam' => ['enabled' => true]],
            $r,
        );
    }

    public function test_片方が連想配列でもう片方がスカラなら後勝ち(): void {
        $r = ConfigMerger::merge(
            ['a' => ['x' => 1]],
            ['a' => 'replaced'],
        );
        $this->assertSame(['a' => 'replaced'], $r);
    }

    public function test_想定ユースケース_本番環境上書き(): void {
        $base = [
            'rate_limit' => [
                'enabled' => true,
                'whitelist_ips' => ['127.0.0.1', '::1', '172.20.0.0/16'],
                'endpoints' => [
                    'submit' => [['key' => 'ip', 'limit' => 5, 'window' => 60]],
                ],
            ],
            'ai_spam' => ['enabled' => false],
            'ai'      => ['provider' => 'claude_cli'],
        ];
        $local = [
            'rate_limit' => ['whitelist_ips' => []],
            'ai_spam'    => ['enabled' => true],
            'ai'         => ['provider' => 'claude_api', 'api_key' => 'sk-prod'],
        ];
        $r = ConfigMerger::merge($base, $local);
        $this->assertSame([], $r['rate_limit']['whitelist_ips']);
        $this->assertTrue($r['rate_limit']['enabled']);  // 元が残る
        $this->assertCount(1, $r['rate_limit']['endpoints']['submit']);  // 元が残る
        $this->assertTrue($r['ai_spam']['enabled']);
        $this->assertSame('claude_api', $r['ai']['provider']);
        $this->assertSame('sk-prod', $r['ai']['api_key']);
    }

}

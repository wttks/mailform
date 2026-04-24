<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateUrl;
use PHPUnit\Framework\TestCase;

/**
 * ValidateUrl のテスト
 * filter_var($value, FILTER_VALIDATE_URL) を使用
 */
class ValidateUrlTest extends TestCase {

    private ValidateUrl $rule;

    protected function setUp(): void {
        $this->rule = new ValidateUrl();
    }

    // ---- 正常系 ----

    public function test_http_URLはtrueを返す(): void {
        $this->assertTrue($this->rule->validate('http://example.com'));
    }

    public function test_https_URLはtrueを返す(): void {
        $this->assertTrue($this->rule->validate('https://example.com'));
    }

    public function test_パス付きURLはtrueを返す(): void {
        $this->assertTrue($this->rule->validate('https://example.com/path/to/page'));
    }

    public function test_クエリ付きURLはtrueを返す(): void {
        $this->assertTrue($this->rule->validate('https://example.com/?key=value&foo=bar'));
    }

    public function test_ポート番号付きURLはtrueを返す(): void {
        $this->assertTrue($this->rule->validate('http://example.com:8080'));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 異常系 ----

    public function test_スキームなしはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('example.com'));
    }

    public function test_スペース付きはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('http://exa mple.com'));
    }

    public function test_日本語ドメインのみではfalseを返す(): void {
        // filter_var は日本語ドメインを通常拒否する
        $this->assertFalse($this->rule->validate('https://テスト.jp'));
    }

    public function test_配列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(['http://example.com']));
    }

    public function test_数値はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(12345));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

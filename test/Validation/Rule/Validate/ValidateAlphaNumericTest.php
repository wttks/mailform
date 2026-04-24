<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateAlphaNumeric;
use PHPUnit\Framework\TestCase;

/**
 * ValidateAlphaNumeric のテスト
 * パターン: /^[a-zA-Z0-9]+$/
 * 半角英数字のみ
 */
class ValidateAlphaNumericTest extends TestCase {

    private ValidateAlphaNumeric $rule;

    protected function setUp(): void {
        $this->rule = new ValidateAlphaNumeric();
    }

    // ---- 正常系 ----

    public function test_小文字英字はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('abc'));
    }

    public function test_大文字英字はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('ABC'));
    }

    public function test_数字はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('123'));
    }

    public function test_英数字混在はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('abc123'));
    }

    public function test_1文字の英字はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('a'));
    }

    public function test_1文字の数字はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('0'));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 異常系 ----

    public function test_スペース混じりはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('abc 123'));
    }

    public function test_ハイフン混じりはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('abc-123'));
    }

    public function test_日本語混じりはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('abc日本語'));
    }

    public function test_アンダースコア混じりはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('abc_123'));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

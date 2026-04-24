<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidatePostalCode;
use PHPUnit\Framework\TestCase;

/**
 * ValidatePostalCode のテスト
 * パターン: /\A\d{3}-?\d{4}\z/
 * ハイフンあり: 123-4567 / ハイフンなし: 1234567
 */
class ValidatePostalCodeTest extends TestCase {

    private ValidatePostalCode $rule;

    protected function setUp(): void {
        $this->rule = new ValidatePostalCode();
    }

    // ---- 正常系 ----

    public function test_ハイフン付き郵便番号はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('123-4567'));
    }

    public function test_ハイフンなし郵便番号はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('1234567'));
    }

    public function test_先頭3桁の境界_000はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('000-0000'));
    }

    public function test_先頭3桁の境界_999はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('999-9999'));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 桁数境界テスト ----

    public function test_前部2桁はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('12-4567'));
    }

    public function test_後部3桁はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('123-456'));
    }

    public function test_後部5桁はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('123-45678'));
    }

    public function test_6桁数字はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('123456'));
    }

    public function test_8桁数字はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('12345678'));
    }

    // ---- 異常系 ----

    public function test_英字混じりはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('abc-1234'));
    }

    public function test_配列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(['123-4567']));
    }

    public function test_数値型はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(1234567));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

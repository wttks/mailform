<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateString;
use PHPUnit\Framework\TestCase;

/**
 * ValidateString のテスト
 * is_string($value) が true の場合のみ OK
 * isRequiredCheck=false のため空値（null, ''）はスキップされる
 */
class ValidateStringTest extends TestCase {

    private ValidateString $rule;

    protected function setUp(): void {
        $this->rule = new ValidateString();
    }

    // ---- 正常系 ----

    public function test_文字列ならtrueを返す(): void {
        $this->assertTrue($this->rule->validate('hello'));
    }

    public function test_日本語文字列ならtrueを返す(): void {
        $this->assertTrue($this->rule->validate('こんにちは'));
    }

    public function test_数字文字列ならtrueを返す(): void {
        $this->assertTrue($this->rule->validate('123'));
    }

    // ---- 空値はスキップ（isRequiredCheck=false）----

    public function test_空文字は空値スキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullは空値スキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 異常系 ----

    public function test_整数はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(123));
    }

    public function test_浮動小数点はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(1.5));
    }

    public function test_配列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(['a']));
    }

    public function test_trueはfalseを返す(): void {
        $this->assertFalse($this->rule->validate(true));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

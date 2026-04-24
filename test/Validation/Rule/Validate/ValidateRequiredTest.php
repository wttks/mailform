<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateRequired;
use PHPUnit\Framework\TestCase;

/**
 * ValidateRequired のテスト
 * isRequiredCheck=true で動作するため、空値でも check() が呼ばれる
 */
class ValidateRequiredTest extends TestCase {

    private ValidateRequired $rule;

    protected function setUp(): void {
        $this->rule = new ValidateRequired();
    }

    // ---- 正常系 ----

    public function test_文字列が入力されている場合はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('hello'));
    }

    public function test_半角スペースの文字列はtrueを返す(): void {
        // スペースは空文字ではないので true
        $this->assertTrue($this->rule->validate(' '));
    }

    public function test_数値0はtrueを返す(): void {
        // 0 は is_null でも is_string でもないので isEmpty=false → true
        $this->assertTrue($this->rule->validate(0));
    }

    public function test_数値はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(42));
    }

    public function test_要素ありの配列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(['a', 'b']));
    }

    public function test_falseはtrueを返す(): void {
        // false は isEmpty でも null でもないので true
        $this->assertTrue($this->rule->validate(false));
    }

    // ---- 異常系 ----

    public function test_nullの場合はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(null));
    }

    public function test_空文字の場合はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(''));
    }

    public function test_空配列の場合はfalseを返す(): void {
        $this->assertFalse($this->rule->validate([]));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

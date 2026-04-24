<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateInt;
use PHPUnit\Framework\TestCase;

/**
 * ValidateInt のテスト
 * int 型 または "-?[0-9]+" にマッチする文字列が OK
 */
class ValidateIntTest extends TestCase {

    private ValidateInt $rule;

    protected function setUp(): void {
        $this->rule = new ValidateInt();
    }

    // ---- 正常系 ----

    public function test_int型ならtrueを返す(): void {
        $this->assertTrue($this->rule->validate(42));
    }

    public function test_負のint型ならtrueを返す(): void {
        $this->assertTrue($this->rule->validate(-1));
    }

    public function test_0はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(0));
    }

    public function test_数字文字列ならtrueを返す(): void {
        $this->assertTrue($this->rule->validate('123'));
    }

    public function test_負の数字文字列ならtrueを返す(): void {
        $this->assertTrue($this->rule->validate('-5'));
    }

    public function test_0の文字列ならtrueを返す(): void {
        $this->assertTrue($this->rule->validate('0'));
    }

    // ---- 空値スキップ（isRequiredCheck=false）----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 異常系 ----

    public function test_小数点付き文字列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('1.5'));
    }

    public function test_float型はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(1.5));
    }

    public function test_英字混じり文字列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('12a'));
    }

    public function test_配列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate([1]));
    }

    public function test_bool型はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(true));
    }

    public function test_スペース付き数字文字列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(' 123'));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

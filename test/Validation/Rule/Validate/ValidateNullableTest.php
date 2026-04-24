<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateNullable;
use PHPUnit\Framework\TestCase;

/**
 * ValidateNullable のテスト
 * check() は常に true を返す
 * isRequiredCheck=true のため空値でも check() が呼ばれる
 */
class ValidateNullableTest extends TestCase {

    private ValidateNullable $rule;

    protected function setUp(): void {
        $this->rule = new ValidateNullable();
    }

    public function test_nullはtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    public function test_空文字はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_空配列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate([]));
    }

    public function test_文字列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('hello'));
    }

    public function test_整数はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(123));
    }

    public function test_配列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(['a', 'b']));
    }

    public function test_falseはtrueを返す(): void {
        $this->assertTrue($this->rule->validate(false));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが取得できる(): void {
        $this->assertIsString($this->rule->getErrorMessage());
    }
}

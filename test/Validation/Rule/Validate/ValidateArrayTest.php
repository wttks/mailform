<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateArray;
use PHPUnit\Framework\TestCase;

/**
 * ValidateArray のテスト
 * is_array($value) の場合のみ true
 */
class ValidateArrayTest extends TestCase {

    private ValidateArray $rule;

    protected function setUp(): void {
        $this->rule = new ValidateArray();
    }

    // ---- 正常系 ----

    public function test_配列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(['a', 'b']));
    }

    public function test_空配列もスキップされtrueを返す(): void {
        // isRequiredCheck=false かつ ObjectUtil::isEmpty([]) = true なのでスキップ
        $this->assertTrue($this->rule->validate([]));
    }

    public function test_連想配列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(['key' => 'value']));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 異常系 ----

    public function test_文字列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('string'));
    }

    public function test_整数はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(123));
    }

    public function test_boolはfalseを返す(): void {
        $this->assertFalse($this->rule->validate(true));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

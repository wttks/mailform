<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Rule\Validate\ValidateRequiredIf;
use PHPUnit\Framework\TestCase;

/**
 * ValidateRequiredIf のテスト
 * args[0]: 他のフィールド名, args[1]: そのフィールドの比較値
 * 他フィールドの値が一致した場合のみ必須チェックを行う
 */
class ValidateRequiredIfTest extends TestCase {

    private ValidateRequiredIf $rule;

    protected function setUp(): void {
        $this->rule = new ValidateRequiredIf();
    }

    // ---- 他フィールドが指定値と一致し、値あり → true ----

    public function test_条件フィールドが一致し値が入力済みならtrueを返す(): void {
        $result = $this->rule->validate(
            'my_value',
            ['other_field', 'yes'],
            'target',
            ['other_field' => 'yes']
        );
        $this->assertTrue($result);
    }

    // ---- 他フィールドが指定値と一致し、値なし → false ----

    public function test_条件フィールドが一致し値が空ならfalseを返す(): void {
        $result = $this->rule->validate(
            '',
            ['other_field', 'yes'],
            'target',
            ['other_field' => 'yes']
        );
        $this->assertFalse($result);
    }

    public function test_条件フィールドが一致し値がnullならfalseを返す(): void {
        $result = $this->rule->validate(
            null,
            ['other_field', 'yes'],
            'target',
            ['other_field' => 'yes']
        );
        $this->assertFalse($result);
    }

    // ---- 他フィールドが指定値と不一致 → 必須チェックなし → true ----

    public function test_条件フィールドが不一致なら空でもtrueを返す(): void {
        $result = $this->rule->validate(
            '',
            ['other_field', 'yes'],
            'target',
            ['other_field' => 'no']
        );
        $this->assertTrue($result);
    }

    // ---- 他フィールドが存在しない (null) → 必須チェックなし → true ----

    public function test_条件フィールドが存在しない場合は空でもtrueを返す(): void {
        $result = $this->rule->validate(
            '',
            ['other_field', 'yes'],
            'target',
            []
        );
        $this->assertTrue($result);
    }

    // ---- bool フィールドの比較 ----

    public function test_条件フィールドがtrueでありtrue指定なら必須チェックする(): void {
        // true === filter_var('true', FILTER_VALIDATE_BOOLEAN)
        $result = $this->rule->validate(
            '',
            ['flag', 'true'],
            'target',
            ['flag' => true]
        );
        $this->assertFalse($result);
    }

    public function test_条件フィールドがfalseでありtrue指定なら必須チェックしない(): void {
        $result = $this->rule->validate(
            '',
            ['flag', 'true'],
            'target',
            ['flag' => false]
        );
        $this->assertTrue($result);
    }

    // ---- args[0] が空の場合は例外 ----

    public function test_引数がない場合は例外を投げる(): void {
        $this->expectException(ValidationRuleException::class);
        $this->rule->validate('value', [''], 'target', []);
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $msg = $this->rule->getErrorMessage();
        $this->assertStringContainsString(':field', $msg);
        $this->assertStringContainsString(':title', $msg);
    }

    // ---- getArgNames ----

    public function test_引数名がfield_valueの順で返る(): void {
        $this->assertSame(['field', 'value'], $this->rule->getArgNames());
    }
}

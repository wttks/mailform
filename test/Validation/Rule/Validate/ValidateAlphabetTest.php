<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateAlphabet;
use PHPUnit\Framework\TestCase;

/**
 * ValidateAlphabet のテスト
 * パターン: /\A[a-zA-Z]+\z/
 * 半角英字のみ
 */
class ValidateAlphabetTest extends TestCase {

    private ValidateAlphabet $rule;

    protected function setUp(): void {
        $this->rule = new ValidateAlphabet();
    }

    // ---- 正常系 ----

    public function test_小文字英字はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('abc'));
    }

    public function test_大文字英字はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('ABC'));
    }

    public function test_大小混在英字はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('AbCdEf'));
    }

    public function test_1文字の英字はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('a'));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 異常系 ----

    public function test_数字混じりはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('abc1'));
    }

    public function test_スペース混じりはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('ab cd'));
    }

    public function test_ひらがな混じりはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('abcあ'));
    }

    public function test_記号混じりはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('abc-def'));
    }

    public function test_配列はfalseを返す(): void {
        // checkは is_string チェックなしで直接 preg_match するため例外やfalseになる
        // preg_match に配列を渡すと警告が出るが false を返す
        $result = @$this->rule->validate(['abc']);
        $this->assertFalse((bool)$result);
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

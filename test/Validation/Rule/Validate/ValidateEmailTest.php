<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateEmail;
use PHPUnit\Framework\TestCase;

/**
 * ValidateEmail のテスト
 * StrUtil::isEmail() = filter_var($email, FILTER_VALIDATE_EMAIL) を使用
 */
class ValidateEmailTest extends TestCase {

    private ValidateEmail $rule;

    protected function setUp(): void {
        $this->rule = new ValidateEmail();
    }

    // ---- 正常系 ----

    public function test_標準的なメールアドレスはtrueを返す(): void {
        $this->assertTrue($this->rule->validate('user@example.com'));
    }

    public function test_サブドメイン付きメールアドレスはtrueを返す(): void {
        $this->assertTrue($this->rule->validate('user@mail.example.co.jp'));
    }

    public function test_プラス記号付きメールアドレスはtrueを返す(): void {
        $this->assertTrue($this->rule->validate('user+tag@example.com'));
    }

    public function test_ドット付きローカル部はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('first.last@example.com'));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 異常系 ----

    public function test_アットマークなしはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('userexample.com'));
    }

    public function test_ドメインなしはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('user@'));
    }

    public function test_ローカル部なしはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('@example.com'));
    }

    public function test_スペース付きはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('user @example.com'));
    }

    public function test_配列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(['user@example.com']));
    }

    public function test_数値はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(123));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

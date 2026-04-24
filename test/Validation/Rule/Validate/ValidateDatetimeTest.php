<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateDatetime;
use PHPUnit\Framework\TestCase;

/**
 * ValidateDatetime のテスト
 * ValidateDate と同じロジック（strtotime または DateTime/DateTimeImmutable）
 */
class ValidateDatetimeTest extends TestCase {

    private ValidateDatetime $rule;

    protected function setUp(): void {
        $this->rule = new ValidateDatetime();
    }

    // ---- 正常系 ----

    public function test_日時文字列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-01-15 12:30:00'));
    }

    public function test_日付のみ文字列もtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-01-15'));
    }

    public function test_T区切り日時はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-01-15T12:30:00'));
    }

    public function test_DateTimeImmutableインスタンスはtrueを返す(): void {
        $this->assertTrue($this->rule->validate(new \DateTimeImmutable('2024-01-01 00:00:00')));
    }

    public function test_DateTimeインスタンスはtrueを返す(): void {
        $this->assertTrue($this->rule->validate(new \DateTime('2024-01-01 00:00:00')));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 異常系 ----

    public function test_無効な文字列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('not-a-datetime'));
    }

    public function test_整数はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(20240101));
    }

    public function test_配列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(['2024-01-01 12:00:00']));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

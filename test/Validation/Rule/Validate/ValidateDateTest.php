<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateDate;
use PHPUnit\Framework\TestCase;

/**
 * ValidateDate のテスト
 * strtotime($value) !== false または DateTime/DateTimeImmutable インスタンスを受け付ける
 */
class ValidateDateTest extends TestCase {

    private ValidateDate $rule;

    protected function setUp(): void {
        $this->rule = new ValidateDate();
    }

    // ---- 正常系 ----

    public function test_YYYY_MM_DD形式はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-01-15'));
    }

    public function test_YYYY_slash_MM_slash_DD形式はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024/01/15'));
    }

    public function test_うるう年の2月29日はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-02-29'));
    }

    public function test_DateTimeImmutableインスタンスはtrueを返す(): void {
        $this->assertTrue($this->rule->validate(new \DateTimeImmutable('2024-01-01')));
    }

    public function test_DateTimeインスタンスはtrueを返す(): void {
        $this->assertTrue($this->rule->validate(new \DateTime('2024-01-01')));
    }

    // ---- うるう年テスト ----

    public function test_非うるう年の2月29日はstrtotime次第(): void {
        // strtotime('2023-02-29') は 2023-03-01 を返す場合があるため true になりうる
        // ValidateDate は strtotime が false でなければ OK なため、厳密な日付チェックはしない
        // この仕様を記録しておく（falseを期待するのは誤り）
        $result = $this->rule->validate('2023-02-29');
        // strtotime は false を返さず解釈するため true になる（仕様確認テスト）
        $this->assertTrue($result);
    }

    // ---- 空値スキップ（isRequiredCheck=false）----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 異常系 ----

    public function test_無効な文字列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('not-a-date'));
    }

    public function test_整数はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(20240101));
    }

    public function test_配列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(['2024-01-01']));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

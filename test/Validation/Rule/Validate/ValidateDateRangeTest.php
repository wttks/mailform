<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateDateRange;
use PHPUnit\Framework\TestCase;

/**
 * ValidateDateRange のテスト
 * DateUtil::checkDateRange() を使用
 * 対応フォーマット: Ymd / Y/m/d / Y-m-d
 * 範囲区切り: ～〜-
 */
class ValidateDateRangeTest extends TestCase {

    private ValidateDateRange $rule;

    protected function setUp(): void {
        $this->rule = new ValidateDateRange();
    }

    // ---- 単一日付 ----

    public function test_Yハイフンmハイフンd単一日付はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-01-15'));
    }

    public function test_Yスラッシュmスラッシュd単一日付はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024/01/15'));
    }

    public function test_Ymd単一日付はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('20240115'));
    }

    // ---- 範囲指定 ----

    public function test_ハイフン区切り範囲はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-01-01-2024-01-31'));
    }

    public function test_波線区切り範囲はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024/01/01～2024/01/31'));
    }

    public function test_全角波線区切り範囲はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024/01/01〜2024/01/31'));
    }

    public function test_開始日のみの範囲はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024/01/01～'));
    }

    public function test_終了日のみの範囲はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('～2024/01/31'));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 配列入力 ----

    public function test_有効な日付範囲の配列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(['2024-01-01', '2024-01-31']));
    }

    public function test_無効な要素を含む配列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(['2024-01-01', 'invalid']));
    }

    // ---- 異常系 ----

    public function test_無効な文字列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('not-a-date'));
    }

    public function test_整数はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(20240101));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

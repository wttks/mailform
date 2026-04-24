<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Rule\Validate\ValidateDateFormat;
use PHPUnit\Framework\TestCase;

/**
 * ValidateDateFormat のテスト
 * 複数フォーマットを args として渡し、いずれかに合致すれば OK
 * DateTime::createFromFormat を使用、warning/error が 0 の場合のみ OK
 */
class ValidateDateFormatTest extends TestCase {

    private ValidateDateFormat $rule;

    protected function setUp(): void {
        $this->rule = new ValidateDateFormat();
    }

    // ---- 正常系（Y-m-d フォーマット）----

    public function test_Ymd形式に合致する日付はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-01-15', ['Y-m-d']));
    }

    public function test_Yスラッシュmスラッシュd形式はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024/01/15', ['Y/m/d']));
    }

    public function test_複数フォーマットのいずれかに合致すればtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-01-15', ['Y/m/d', 'Y-m-d']));
    }

    public function test_2つ目のフォーマットに合致すればtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024/01/15', ['Y-m-d', 'Y/m/d']));
    }

    // ---- うるう年テスト ----

    public function test_うるう年2月29日はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-02-29', ['Y-m-d']));
    }

    public function test_非うるう年2月29日はfalseを返す(): void {
        // createFromFormat はエラーなしで生成されないはず
        $this->assertFalse($this->rule->validate('2023-02-29', ['Y-m-d']));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate('', ['Y-m-d']));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null, ['Y-m-d']));
    }

    // ---- 異常系 ----

    public function test_フォーマットが不一致の場合はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('2024/01/15', ['Y-m-d']));
    }

    public function test_完全に無効な文字列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('invalid-date', ['Y-m-d']));
    }

    public function test_argsが空の場合は例外を投げる(): void {
        $this->expectException(ValidationRuleException::class);
        $this->rule->validate('2024-01-15', []);
    }

    public function test_整数はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(20240115, ['Y-m-d']));
    }

    public function test_日時フォーマットYmdHis(): void {
        $this->assertTrue($this->rule->validate('2024-01-15 12:30:00', ['Y-m-d H:i:s']));
    }

    public function test_日付部分のみ指定で時刻が余分なのはwarningが出てfalseになる(): void {
        // '2024-01-15 12:30:00' を 'Y-m-d' フォーマットでパースすると trailing data warning が発生
        $this->assertFalse($this->rule->validate('2024-01-15 12:30:00', ['Y-m-d']));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $msg = $this->rule->getErrorMessage();
        $this->assertStringContainsString(':title', $msg);
        $this->assertStringContainsString(':format', $msg);
    }
}

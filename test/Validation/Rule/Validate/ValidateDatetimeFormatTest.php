<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Rule\Validate\ValidateDatetimeFormat;
use PHPUnit\Framework\TestCase;

/**
 * ValidateDatetimeFormat のテスト
 * ValidateDateFormat と同じロジック（datetime 向け）
 */
class ValidateDatetimeFormatTest extends TestCase {

    private ValidateDatetimeFormat $rule;

    protected function setUp(): void {
        $this->rule = new ValidateDatetimeFormat();
    }

    // ---- 正常系 ----

    public function test_日時フォーマットはtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-01-15 12:30:00', ['Y-m-d H:i:s']));
    }

    public function test_複数フォーマットのいずれかに合致すればtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-01-15 12:30', ['Y-m-d H:i:s', 'Y-m-d H:i']));
    }

    public function test_Y_m_dフォーマットでもtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-01-15', ['Y-m-d']));
    }

    // ---- うるう年テスト ----

    public function test_うるう年2月29日はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('2024-02-29 00:00:00', ['Y-m-d H:i:s']));
    }

    public function test_非うるう年2月29日はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('2023-02-29 00:00:00', ['Y-m-d H:i:s']));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate('', ['Y-m-d H:i:s']));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null, ['Y-m-d H:i:s']));
    }

    // ---- 異常系 ----

    public function test_フォーマット不一致はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('2024/01/15 12:30:00', ['Y-m-d H:i:s']));
    }

    public function test_argsが空の場合は例外を投げる(): void {
        $this->expectException(ValidationRuleException::class);
        $this->rule->validate('2024-01-15 12:00:00', []);
    }

    public function test_整数はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(20240115, ['Y-m-d H:i:s']));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $msg = $this->rule->getErrorMessage();
        $this->assertStringContainsString(':title', $msg);
        $this->assertStringContainsString(':format', $msg);
    }
}

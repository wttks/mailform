<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateTelephone;
use PHPUnit\Framework\TestCase;

/**
 * ValidateTelephone のテスト
 * StrUtil::isTelephone() を使用
 * 国内: 0\d{9,11}（ハイフン除去後）
 * 国際: +[1-9]\d{6,14}（ハイフン除去後）
 */
class ValidateTelephoneTest extends TestCase {

    private ValidateTelephone $rule;

    protected function setUp(): void {
        $this->rule = new ValidateTelephone();
    }

    // ---- 正常系 ----

    public function test_10桁国内番号はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('0312345678'));
    }

    public function test_11桁国内番号はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('09012345678'));
    }

    public function test_ハイフン付き10桁はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('03-1234-5678'));
    }

    public function test_ハイフン付き11桁はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('090-1234-5678'));
    }

    public function test_国際番号はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('+819012345678'));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 桁数境界テスト ----

    public function test_9桁の国内番号はfalseを返す(): void {
        // 0 + 8桁 = 9桁 → 不正
        $this->assertFalse($this->rule->validate('012345678'));
    }

    public function test_12桁の国内番号はtrueを返す(): void {
        // 0 + 11桁 = 12桁 → コード上は 0\d{9,11} なので OK
        $this->assertTrue($this->rule->validate('012345678901'));
    }

    public function test_13桁の国内番号はfalseを返す(): void {
        // 0 + 12桁 = 13桁 → 不正
        $this->assertFalse($this->rule->validate('0123456789012'));
    }

    // ---- 異常系 ----

    public function test_1で始まる番号はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('1312345678'));
    }

    public function test_英字混じりはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('0312345abc'));
    }

    public function test_配列でも有効な番号の配列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(['0312345678', '09012345678']));
    }

    public function test_配列に無効な番号が含まれる場合はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(['0312345678', 'invalid']));
    }

    public function test_整数型はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(9012345678));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

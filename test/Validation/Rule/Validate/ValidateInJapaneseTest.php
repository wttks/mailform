<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateInJapanese;
use PHPUnit\Framework\TestCase;

/**
 * ValidateInJapanese のテスト
 * StrUtil::inJapanese() = /[\p{Hiragana}\p{Katakana}\p{Han}]/u を使用
 * ひらがな・カタカナ・漢字が1文字以上含まれていれば OK
 */
class ValidateInJapaneseTest extends TestCase {

    private ValidateInJapanese $rule;

    protected function setUp(): void {
        $this->rule = new ValidateInJapanese();
    }

    // ---- 正常系 ----

    public function test_ひらがなを含む文字列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('あいうえお'));
    }

    public function test_カタカナを含む文字列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('アイウエオ'));
    }

    public function test_漢字を含む文字列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('東京都'));
    }

    public function test_英字と日本語の混合はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('Hello世界'));
    }

    public function test_日本語1文字だけでもtrueを返す(): void {
        $this->assertTrue($this->rule->validate('あ'));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 異常系 ----

    public function test_英字のみはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('Hello'));
    }

    public function test_数字のみはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('12345'));
    }

    public function test_記号のみはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('!@#$%'));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

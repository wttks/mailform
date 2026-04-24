<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateFurigana;
use PHPUnit\Framework\TestCase;

/**
 * ValidateFurigana のテスト
 *
 * デフォルト(カタカナ): \p{Katakana}+ (スペース区切りも可)
 * 'hiragana' / 'ひらがな': \p{Hiragana}+ (スペース区切りも可)
 *
 * Unicode 境界テスト:
 *  ひらがな: U+3041(ぁ) ～ U+3096(ゖ)
 *  カタカナ: U+30A1(ァ) ～ U+30F6(ヶ)
 *
 * ※ \p{Hiragana} / \p{Katakana} は PHP の PCRE Unicode プロパティを使用。
 *   実際にどの文字がマッチするかは PCRE のバージョンに依存するため、
 *   スクリプト先頭・末尾の典型的な文字でテストする。
 */
class ValidateFuriganaTest extends TestCase {

    private ValidateFurigana $rule;

    protected function setUp(): void {
        $this->rule = new ValidateFurigana();
    }

    // ==============================
    // カタカナモード (デフォルト)
    // ==============================

    public function test_カタカナ文字列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('アイウエオ'));
    }

    public function test_カタカナ長音符付きはtrueを返す(): void {
        // ー(U+30FC) も \p{Katakana} に含まれる
        $this->assertTrue($this->rule->validate('コーヒー'));
    }

    public function test_カタカナスペース区切りはtrueを返す(): void {
        $this->assertTrue($this->rule->validate('ヤマダ タロウ'));
    }

    public function test_カタカナ先頭文字ァ_U30A1_はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('ァ'));
    }

    public function test_カタカナ末尾文字ヶ_U30F6_はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('ヶ'));
    }

    public function test_ひらがな混じりカタカナはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('アいウ'));
    }

    public function test_ひらがな文字列をカタカナモードで検証するとfalseを返す(): void {
        $this->assertFalse($this->rule->validate('あいうえお'));
    }

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    public function test_英字混じりはカタカナモードでfalseを返す(): void {
        $this->assertFalse($this->rule->validate('アaイ'));
    }

    // ==============================
    // ひらがなモード
    // ==============================

    public function test_ひらがな文字列はtrueを返す_hiragana(): void {
        $this->assertTrue($this->rule->validate('あいうえお', ['hiragana']));
    }

    public function test_ひらがなスペース区切りはtrueを返す(): void {
        $this->assertTrue($this->rule->validate('やまだ たろう', ['hiragana']));
    }

    public function test_ひらがな先頭文字ぁ_U3041_はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('ぁ', ['hiragana']));
    }

    public function test_ひらがな末尾文字ゖ_U3096_はtrueを返す(): void {
        // ゖ(U+3096) が \p{Hiragana} に含まれるかは PCRE 依存だが、テストしておく
        // 含まれない場合は失敗するので「既存バグ or PCRE 仕様」としてコメントに記載
        $this->assertTrue($this->rule->validate('ゖ', ['hiragana']));
    }

    public function test_ひらがなモードでカタカナはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('アイウ', ['hiragana']));
    }

    public function test_ひらがなmodeのエイリアス(): void {
        $this->assertTrue($this->rule->validate('あいう', ['ひらがな']));
    }

    public function test_ひらがな文字列に英字混じりはfalseを返す(): void {
        $this->assertFalse($this->rule->validate('あaい', ['hiragana']));
    }

    // ==============================
    // 配列入力
    // ==============================

    public function test_カタカナ配列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(['アイウ', 'エオカ']));
    }

    public function test_ひらがな配列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(['あいう', 'えおか'], ['hiragana']));
    }

    public function test_無効な要素を含む配列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(['アイウ', 'abc']));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $msg = $this->rule->getErrorMessage();
        $this->assertStringContainsString(':title', $msg);
        $this->assertStringContainsString(':type', $msg);
    }
}

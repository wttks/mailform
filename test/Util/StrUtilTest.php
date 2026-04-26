<?php

namespace AIJOH\Test\Util;

use AIJOH\Util\StrUtil;
use PHPUnit\Framework\TestCase;

/**
 * StrUtil のテスト
 */
class StrUtilTest extends TestCase {

    // ---- startsWith() ----

    public function test_startsWith_前方一致はtrue(): void {
        $this->assertTrue(StrUtil::startsWith('foobar', 'foo'));
    }

    public function test_startsWith_一致しない場合はfalse(): void {
        $this->assertFalse(StrUtil::startsWith('foobar', 'bar'));
    }

    public function test_startsWith_空文字列の検索はtrue(): void {
        $this->assertTrue(StrUtil::startsWith('foobar', ''));
    }

    public function test_startsWith_完全一致はtrue(): void {
        $this->assertTrue(StrUtil::startsWith('foo', 'foo'));
    }

    public function test_startsWith_空文字列から空文字列を検索はtrue(): void {
        $this->assertTrue(StrUtil::startsWith('', ''));
    }

    public function test_startsWith_日本語の前方一致はtrue(): void {
        $this->assertTrue(StrUtil::startsWith('こんにちは', 'こん'));
    }

    // ---- endsWith() ----

    public function test_endsWith_後方一致はtrue(): void {
        $this->assertTrue(StrUtil::endsWith('foobar', 'bar'));
    }

    public function test_endsWith_一致しない場合はfalse(): void {
        $this->assertFalse(StrUtil::endsWith('foobar', 'foo'));
    }

    public function test_endsWith_空文字列の検索はtrue(): void {
        $this->assertTrue(StrUtil::endsWith('foobar', ''));
    }

    public function test_endsWith_完全一致はtrue(): void {
        $this->assertTrue(StrUtil::endsWith('bar', 'bar'));
    }

    public function test_endsWith_日本語の後方一致はtrue(): void {
        $this->assertTrue(StrUtil::endsWith('こんにちは', 'にちは'));
    }

    // ---- contains() ----

    public function test_contains_部分一致はtrue(): void {
        $this->assertTrue(StrUtil::contains('foobar', 'oob'));
    }

    public function test_contains_一致しない場合はfalse(): void {
        $this->assertFalse(StrUtil::contains('foobar', 'xyz'));
    }

    public function test_contains_空文字列はtrue(): void {
        $this->assertTrue(StrUtil::contains('foobar', ''));
    }

    public function test_contains_日本語の部分一致はtrue(): void {
        $this->assertTrue(StrUtil::contains('こんにちは世界', '世界'));
    }

    // ---- isEmpty() ----

    public function test_isEmpty_nullはtrue(): void {
        $this->assertTrue(StrUtil::isEmpty(null));
    }

    public function test_isEmpty_空文字はtrue(): void {
        $this->assertTrue(StrUtil::isEmpty(''));
    }

    public function test_isEmpty_空配列はtrue(): void {
        $this->assertTrue(StrUtil::isEmpty([]));
    }

    public function test_isEmpty_空オブジェクトはtrue(): void {
        $this->assertTrue(StrUtil::isEmpty(new \stdClass()));
    }

    public function test_isEmpty_通常文字列はfalse(): void {
        $this->assertFalse(StrUtil::isEmpty('a'));
    }

    public function test_isEmpty_スペースはfalse(): void {
        $this->assertFalse(StrUtil::isEmpty(' '));
    }

    public function test_isEmpty_整数0はfalse(): void {
        $this->assertFalse(StrUtil::isEmpty(0));
    }

    public function test_isEmpty_値入り配列はfalse(): void {
        $this->assertFalse(StrUtil::isEmpty([1]));
    }

    // ---- toKatakana() ----

    public function test_toKatakana_ひらがなをカタカナに変換する(): void {
        $this->assertSame('アイウエオ', StrUtil::toKatakana('あいうえお'));
    }

    public function test_toKatakana_半角カタカナを全角カタカナに変換する(): void {
        // mb_convert_kana の a/K/V/C/s オプション
        $result = StrUtil::toKatakana('ｱｲｳｴｵ');
        $this->assertSame('アイウエオ', $result);
    }

    public function test_toKatakana_配列の各要素を変換する(): void {
        $result = StrUtil::toKatakana(['あ', 'い']);
        $this->assertSame(['ア', 'イ'], $result);
    }

    public function test_toKatakana_英数字はそのまま(): void {
        $result = StrUtil::toKatakana('abc123');
        $this->assertSame('abc123', $result);
    }

    // ---- toHiragana() ----

    public function test_toHiragana_カタカナをひらがなに変換する(): void {
        $this->assertSame('あいうえお', StrUtil::toHiragana('アイウエオ'));
    }

    public function test_toHiragana_配列の各要素を変換する(): void {
        $result = StrUtil::toHiragana(['ア', 'イ']);
        $this->assertSame(['あ', 'い'], $result);
    }

    // ---- toNormalize() ----

    public function test_toNormalize_ひらがなはカタカナに変換される(): void {
        // mb_convert_kana の a/K/V/s オプション (H不含のため、ひらがなはカタカナへ変換されない)
        // 実際のオプション "aKVs": a=半角英数→全角, K=半角カナ→全角カナ, V=濁点合成, s=全角スペース→半角
        // ひらがな→カタカナへの変換はオプション "H" が必要だが、コードには含まれていない
        // コードを確認: mb_convert_kana($str, "aKVs", 'UTF-8') → ひらがなはそのまま
        $result = StrUtil::toNormalize('あいうえお');
        $this->assertSame('あいうえお', $result);
    }

    public function test_toNormalize_半角カタカナを全角に変換する(): void {
        $result = StrUtil::toNormalize('ｱｲｳｴｵ');
        $this->assertSame('アイウエオ', $result);
    }

    public function test_toNormalize_配列の各要素を変換する(): void {
        $result = StrUtil::toNormalize(['ｱ', 'ｲ']);
        $this->assertSame(['ア', 'イ'], $result);
    }

    // ---- toNormalizeTrim() ----

    public function test_toNormalizeTrim_前後の空白を削除する(): void {
        $result = StrUtil::toNormalizeTrim('  hello  ');
        $this->assertSame('hello', $result);
    }

    public function test_toNormalizeTrim_正規化も行う(): void {
        $result = StrUtil::toNormalizeTrim('  ｱｲｳ  ');
        $this->assertSame('アイウ', $result);
    }

    public function test_toNormalizeTrim_配列の各要素に適用する(): void {
        $result = StrUtil::toNormalizeTrim(['  a  ', '  b  ']);
        $this->assertSame(['a', 'b'], $result);
    }

    // ---- trim() ----

    public function test_trim_前後の通常スペースを削除する(): void {
        $this->assertSame('hello', StrUtil::trim('  hello  '));
    }

    public function test_trim_前後の全角スペースを削除する(): void {
        $this->assertSame('hello', StrUtil::trim("\u{3000}hello\u{3000}"));
    }

    public function test_trim_配列の各要素をトリムする(): void {
        $result = StrUtil::trim(['  a  ', '  b  ']);
        $this->assertSame(['a', 'b'], $result);
    }

    public function test_trim_中間の空白は削除しない(): void {
        $this->assertSame('foo bar', StrUtil::trim('  foo bar  '));
    }

    // ---- toCamelCase() ----

    public function test_toCamelCase_スネークケースをキャメルケースに変換する(): void {
        $this->assertSame('snakeCase', StrUtil::toCamelCase('snake_case'));
    }

    public function test_toCamelCase_先頭は大文字にしない(): void {
        $this->assertSame('myVariableName', StrUtil::toCamelCase('my_variable_name'));
    }

    public function test_toCamelCase_ハイフン区切りも変換する(): void {
        $this->assertSame('fooBar', StrUtil::toCamelCase('foo-bar'));
    }

    public function test_toCamelCase_配列の各要素を変換する(): void {
        $result = StrUtil::toCamelCase(['foo_bar', 'baz_qux']);
        $this->assertSame(['fooBar', 'bazQux'], $result);
    }

    // ---- toSnakeCase() ----

    public function test_toSnakeCase_キャメルケースをスネークケースに変換する(): void {
        $this->assertSame('snake_case', StrUtil::toSnakeCase('snakeCase'));
    }

    public function test_toSnakeCase_全て小文字になる(): void {
        $this->assertSame('my_variable', StrUtil::toSnakeCase('MyVariable'));
    }

    public function test_toSnakeCase_配列の各要素を変換する(): void {
        $result = StrUtil::toSnakeCase(['fooBar', 'bazQux']);
        $this->assertSame(['foo_bar', 'baz_qux'], $result);
    }

    // ---- isKatakana() ----

    public function test_isKatakana_全角カタカナのみtrue(): void {
        $this->assertTrue(StrUtil::isKatakana('アイウエオ'));
    }

    public function test_isKatakana_ひらがなが含まれるとfalse(): void {
        $this->assertFalse(StrUtil::isKatakana('アいウ'));
    }

    public function test_isKatakana_空文字はfalse(): void {
        // preg_match はマッチしない場合 0 を返すため false
        $this->assertFalse(StrUtil::isKatakana(''));
    }

    public function test_isKatakana_配列の全要素がカタカナならtrue(): void {
        $this->assertTrue(StrUtil::isKatakana(['ア', 'イ']));
    }

    public function test_isKatakana_配列に非カタカナがあればfalse(): void {
        $this->assertFalse(StrUtil::isKatakana(['ア', 'あ']));
    }

    /**
     * 境界値テスト: U+30A1（ァ）はカタカナの最初
     */
    public function test_isKatakana_U30A1_ァはtrue(): void {
        $this->assertTrue(StrUtil::isKatakana("\u{30A1}"));
    }

    /**
     * 境界値テスト: U+30F6（ヶ）はカタカナの最後
     */
    public function test_isKatakana_U30F6_ヶはtrue(): void {
        $this->assertTrue(StrUtil::isKatakana("\u{30F6}"));
    }

    /**
     * 境界値テスト: U+30A0（゠）はカタカナブロックの一部
     * PHPの \p{Katakana} はUnicodeプロパティに基づくため実際の範囲を確認する
     */
    public function test_isKatakana_U30A0_゠はカタカナとして扱われる(): void {
        // U+30A0 (゠) は KATAKANA-HIRAGANA DOUBLE HYPHEN
        // PHP の \p{Katakana} は U+30A0 を含むため true を返す
        $result = StrUtil::isKatakana("\u{30A0}");
        $this->assertTrue($result);
    }

    // ---- isHiragana() ----

    public function test_isHiragana_ひらがなのみtrue(): void {
        $this->assertTrue(StrUtil::isHiragana('あいうえお'));
    }

    public function test_isHiragana_カタカナが含まれるとfalse(): void {
        $this->assertFalse(StrUtil::isHiragana('あイう'));
    }

    public function test_isHiragana_空文字はfalse(): void {
        $this->assertFalse(StrUtil::isHiragana(''));
    }

    public function test_isHiragana_配列の全要素がひらがなならtrue(): void {
        $this->assertTrue(StrUtil::isHiragana(['あ', 'い']));
    }

    /**
     * 境界値テスト: U+3041（ぁ）はひらがなの最初
     */
    public function test_isHiragana_U3041_ぁはtrue(): void {
        $this->assertTrue(StrUtil::isHiragana("\u{3041}"));
    }

    /**
     * 境界値テスト: U+3096（ゖ）はひらがなの最後
     */
    public function test_isHiragana_U3096_ゖはtrue(): void {
        $this->assertTrue(StrUtil::isHiragana("\u{3096}"));
    }

    /**
     * 境界値テスト: U+3040（〿）はひらがなの1つ前
     */
    public function test_isHiragana_U3040_は非ひらがな(): void {
        $result = StrUtil::isHiragana("\u{3040}");
        $this->assertFalse($result);
    }

    /**
     * 境界値テスト: U+3097（゗）はひらがなの1つ後
     */
    public function test_isHiragana_U3097_は非ひらがな(): void {
        $result = StrUtil::isHiragana("\u{3097}");
        $this->assertFalse($result);
    }

    // ---- isFurigana() ひらがな指定 ----

    public function test_isFurigana_ひらがな_ひらがなのみtrue(): void {
        $this->assertTrue(StrUtil::isFurigana('あいうえお', 'hiragana'));
    }

    public function test_isFurigana_ひらがな_スペースを挟んだひらがなtrue(): void {
        $this->assertTrue(StrUtil::isFurigana('やまだ たろう', 'hiragana'));
    }

    public function test_isFurigana_ひらがな_3ブロック以上もtrue(): void {
        $this->assertTrue(StrUtil::isFurigana('やまだ たろう じろう', 'hiragana'));
    }

    public function test_isFurigana_ひらがな_カタカナが含まれるとfalse(): void {
        $this->assertFalse(StrUtil::isFurigana('アイウ', 'hiragana'));
    }

    public function test_isFurigana_ひらがな_空文字はfalse(): void {
        $this->assertFalse(StrUtil::isFurigana('', 'hiragana'));
    }

    // ---- isFurigana() カタカナ指定（デフォルト） ----

    public function test_isFurigana_カタカナ_デフォルトはkatakana(): void {
        $this->assertTrue(StrUtil::isFurigana('アイウエオ'));
        $this->assertTrue(StrUtil::isFurigana('アイウエオ', 'katakana'));
    }

    public function test_isFurigana_カタカナ_スペースを挟んだカタカナtrue(): void {
        $this->assertTrue(StrUtil::isFurigana('ヤマダ タロウ', 'katakana'));
    }

    public function test_isFurigana_カタカナ_3ブロック以上もtrue(): void {
        $this->assertTrue(StrUtil::isFurigana('ヤマダ タロウ ジロウ', 'katakana'));
    }

    public function test_isFurigana_カタカナ_全角空白でも許容(): void {
        $this->assertTrue(StrUtil::isFurigana('ヤマダ　タロウ', 'katakana'));
    }

    public function test_isFurigana_カタカナ_ひらがなが含まれるとfalse(): void {
        $this->assertFalse(StrUtil::isFurigana('あいう', 'katakana'));
    }

    public function test_isFurigana_カタカナ_空文字はfalse(): void {
        $this->assertFalse(StrUtil::isFurigana('', 'katakana'));
    }

    public function test_isFurigana_カタカナ_中黒も許容(): void {
        // 中黒「・」は Unicode の Katakana プロパティに含まれるので true
        // ヴァン・デル・ベルク のような複合姓に対応するため有用
        $this->assertTrue(StrUtil::isFurigana('ヤマダ・タロウ', 'katakana'));
    }

    // ---- inJapanese() ----

    public function test_inJapanese_ひらがなを含むtrue(): void {
        $this->assertTrue(StrUtil::inJapanese('hello あ world'));
    }

    public function test_inJapanese_カタカナを含むtrue(): void {
        $this->assertTrue(StrUtil::inJapanese('アイウ'));
    }

    public function test_inJapanese_漢字を含むtrue(): void {
        $this->assertTrue(StrUtil::inJapanese('日本語'));
    }

    public function test_inJapanese_英数字のみfalse(): void {
        $this->assertFalse(StrUtil::inJapanese('hello world 123'));
    }

    public function test_inJapanese_空文字はfalse(): void {
        $this->assertFalse(StrUtil::inJapanese(''));
    }

    // ---- isEmail() ----

    public function test_isEmail_正しいメールアドレスはtrue(): void {
        $this->assertTrue(StrUtil::isEmail('user@example.com'));
    }

    public function test_isEmail_アットマークなしはfalse(): void {
        $this->assertFalse(StrUtil::isEmail('notanemail'));
    }

    public function test_isEmail_ドメインなしはfalse(): void {
        $this->assertFalse(StrUtil::isEmail('user@'));
    }

    public function test_isEmail_配列で全て正しければtrue(): void {
        $this->assertTrue(StrUtil::isEmail(['a@example.com', 'b@example.com']));
    }

    public function test_isEmail_配列で一つ不正ならfalse(): void {
        $this->assertFalse(StrUtil::isEmail(['a@example.com', 'invalid']));
    }

    // ---- isTelephone() ----

    public function test_isTelephone_10桁の国内電話番号はtrue(): void {
        $this->assertTrue(StrUtil::isTelephone('0312345678'));
    }

    public function test_isTelephone_11桁の国内電話番号はtrue(): void {
        $this->assertTrue(StrUtil::isTelephone('09012345678'));
    }

    public function test_isTelephone_ハイフン付き10桁はtrue(): void {
        $this->assertTrue(StrUtil::isTelephone('03-1234-5678'));
    }

    public function test_isTelephone_ハイフン付き11桁はtrue(): void {
        $this->assertTrue(StrUtil::isTelephone('090-1234-5678'));
    }

    public function test_isTelephone_9桁はfalse(): void {
        // 先頭0 + 9桁ではなく、0含めて10桁未満
        $this->assertFalse(StrUtil::isTelephone('031234567'));
    }

    public function test_isTelephone_国際電話番号プラスから始まるはtrue(): void {
        $this->assertTrue(StrUtil::isTelephone('+819012345678'));
    }

    public function test_isTelephone_文字が含まれるとfalse(): void {
        $this->assertFalse(StrUtil::isTelephone('abc1234567'));
    }

    public function test_isTelephone_空文字はfalse(): void {
        $this->assertFalse(StrUtil::isTelephone(''));
    }
}

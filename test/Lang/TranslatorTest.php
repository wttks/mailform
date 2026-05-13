<?php

namespace AIJOH\Test\Lang;

use AIJOH\Lang\Translator;
use PHPUnit\Framework\TestCase;

class TranslatorTest extends TestCase {

    protected function setUp() : void {
        Translator::reset();
    }

    protected function tearDown() : void {
        Translator::reset();
    }


    public function test_デフォルト_locale_は_ja(): void {
        $this->assertSame('ja', Translator::getLocale());
    }


    public function test_locale_ja_では翻訳せず原文を返す(): void {
        $this->assertSame(':titleは必須項目です。', Translator::translate(':titleは必須項目です。'));
    }


    public function test_locale_en_では同梱の翻訳を返す(): void {
        Translator::setLocale('en');
        $this->assertSame(':title is required.', Translator::translate(':titleは必須項目です。'));
    }


    public function test_locale_en_で未定義の文字列は原文を返す(): void {
        Translator::setLocale('en');
        $this->assertSame('未定義のメッセージ', Translator::translate('未定義のメッセージ'));
    }


    public function test_setMessages_で独自翻訳を追加できる(): void {
        Translator::setLocale('en');
        Translator::setMessages('en', [
            '独自メッセージ' => 'Custom message',
        ]);
        $this->assertSame('Custom message', Translator::translate('独自メッセージ'));
    }


    public function test_setMessages_は同梱翻訳を上書きできる(): void {
        Translator::setLocale('en');
        Translator::setMessages('en', [
            ':titleは必須項目です。' => ':title is mandatory.',
        ]);
        $this->assertSame(':title is mandatory.', Translator::translate(':titleは必須項目です。'));
    }


    public function test_reset_で初期状態に戻る(): void {
        Translator::setLocale('en');
        Translator::reset();
        $this->assertSame('ja', Translator::getLocale());
        $this->assertSame(':titleは必須項目です。', Translator::translate(':titleは必須項目です。'));
    }


    // ---- 動的ホワイトリスト方式の検証 ----

    public function test_setLocale_path_traversal_は拒否されデフォルト維持(): void {
        Translator::setLocale('../../../etc/passwd');
        $this->assertSame('ja', Translator::getLocale());
    }


    public function test_setLocale_スラッシュ含みは拒否(): void {
        Translator::setLocale('en/../ja');
        $this->assertSame('ja', Translator::getLocale());
    }


    public function test_setLocale_ドット含みは拒否(): void {
        Translator::setLocale('en.php');
        $this->assertSame('ja', Translator::getLocale());
    }


    public function test_setLocale_空文字列は拒否(): void {
        Translator::setLocale('');
        $this->assertSame('ja', Translator::getLocale());
    }


    public function test_setLocale_長すぎる文字列は拒否(): void {
        Translator::setLocale(str_repeat('a', 17));
        $this->assertSame('ja', Translator::getLocale());
    }


    public function test_setLocale_同梱されていない_locale_は拒否されデフォルト維持(): void {
        Translator::setLocale('zh_CN');  // 構文 OK だがファイル無し
        $this->assertSame('ja', Translator::getLocale());
    }


    public function test_setMessages_で先に登録すれば未同梱_locale_も使える(): void {
        Translator::setMessages('zh_CN', [
            ':titleは必須項目です。' => ':title 是必填项。',
        ]);
        Translator::setLocale('zh_CN');
        $this->assertSame('zh_CN', Translator::getLocale());
        $this->assertSame(':title 是必填项。', Translator::translate(':titleは必須項目です。'));
    }


    public function test_setLocale_先_setMessages_後の順序は拒否される_期待動作(): void {
        // setLocale 時点では未知 locale なので拒否される
        Translator::setLocale('zh_CN');
        $this->assertSame('ja', Translator::getLocale());
        // setMessages 後に再度 setLocale すれば成功
        Translator::setMessages('zh_CN', [ 'x' => 'y' ]);
        Translator::setLocale('zh_CN');
        $this->assertSame('zh_CN', Translator::getLocale());
    }


    public function test_setMessages_の_locale_も_path_traversal_は拒否(): void {
        Translator::setMessages('../evil', [ 'x' => 'y' ]);
        Translator::setLocale('../evil');
        $this->assertSame('ja', Translator::getLocale());
    }


    public function test_ハイフン入り_locale_も許可(): void {
        Translator::setMessages('en-GB', [
            ':titleは必須項目です。' => ':title is required (UK).',
        ]);
        Translator::setLocale('en-GB');
        $this->assertSame('en-GB', Translator::getLocale());
        $this->assertSame(':title is required (UK).', Translator::translate(':titleは必須項目です。'));
    }
}

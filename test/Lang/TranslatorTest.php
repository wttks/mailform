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
}

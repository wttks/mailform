<?php

namespace AIJOH\Test\Validation\Rule\Format;

use AIJOH\SecurityPayloads;
use AIJOH\Validation\Rule\Format\FormatNormalizeZwsp;
use PHPUnit\Framework\TestCase;

class FormatNormalizeZwspTest extends TestCase {

    private FormatNormalizeZwsp $format;

    protected function setUp() : void {
        $this->format = new FormatNormalizeZwsp();
    }


    public function test_ZWSP_を削除する() : void {
        $result = $this->format->format("あ\u{200B}\u{200B}\u{200B}い");
        $this->assertSame('あい', $result);
    }


    public function test_BOM_を削除する() : void {
        $result = $this->format->format("\u{FEFF}text");
        $this->assertSame('text', $result);
    }


    public function test_RTL_Override_を削除する() : void {
        $result = $this->format->format(SecurityPayloads::ENCODING_ATTACK['rtl_override']);
        $this->assertSame('txt.exe', $result);
    }


    public function test_in_japanese_潜伏攻撃を無害化する() : void {
        // 日本語に潜伏した ZWSP 列は ZWSP が削除されて素の日本語+ASCII になる
        $result = $this->format->format(SecurityPayloads::ENCODING_ATTACK['jp_with_zwsp']);
        $this->assertSame('あbuy_my_product_now', $result);
    }


    public function test_配列も再帰処理される() : void {
        $result = $this->format->format([
            'a' => "あ\u{200B}い",
            'b' => "\u{FEFF}c",
        ]);
        $this->assertSame([ 'a' => 'あい', 'b' => 'c' ], $result);
    }


    public function test_通常テキストは変更されない() : void {
        $result = $this->format->format("お問い合わせありがとうございます。");
        $this->assertSame('お問い合わせありがとうございます。', $result);
    }


    public function test_文字列でも配列でもない値はそのまま返す() : void {
        $this->assertSame(123, $this->format->format(123));
        $this->assertSame(null, $this->format->format(null));
    }
}

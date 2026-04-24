<?php

namespace AIJOH\Test\Util;

use AIJOH\Util\HtmlUtil;
use PHPUnit\Framework\TestCase;

/**
 * HtmlUtil のテスト
 */
class HtmlUtilTest extends TestCase {

    // ---- escape() ----

    public function test_escape_通常文字はそのまま(): void {
        $this->assertSame('hello', HtmlUtil::escape('hello'));
    }

    public function test_escape_山括弧がエスケープされる(): void {
        $this->assertSame('&lt;script&gt;', HtmlUtil::escape('<script>'));
    }

    public function test_escape_アンパサンドがエスケープされる(): void {
        $this->assertSame('a&amp;b', HtmlUtil::escape('a&b'));
    }

    public function test_escape_ダブルクォートがエスケープされる(): void {
        $this->assertSame('&quot;hello&quot;', HtmlUtil::escape('"hello"'));
    }

    public function test_escape_シングルクォートがエスケープされる(): void {
        $this->assertSame('&#039;hello&#039;', HtmlUtil::escape("'hello'"));
    }

    public function test_escape_XSS攻撃文字列がエスケープされる(): void {
        $input = '<img src=x onerror=alert(1)>';
        $result = HtmlUtil::escape($input);
        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringContainsString('&lt;img', $result);
    }

    public function test_escape_空文字はそのまま(): void {
        $this->assertSame('', HtmlUtil::escape(''));
    }

    public function test_escape_日本語文字はそのまま(): void {
        $this->assertSame('こんにちは', HtmlUtil::escape('こんにちは'));
    }
}

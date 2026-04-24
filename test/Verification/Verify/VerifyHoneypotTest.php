<?php

namespace AIJOH\Test\Verification\Verify;

use AIJOH\Http\Post;
use AIJOH\Verification\Verify\VerifyHoneypot;
use PHPUnit\Framework\TestCase;

/**
 * VerifyHoneypot のテスト
 */
class VerifyHoneypotTest extends TestCase {

    protected function setUp() : void {
        Post::reset();
    }

    protected function tearDown() : void {
        Post::reset();
    }

    // ---- form() ----

    public function test_form_デフォルト_name_の_input_を_非表示で生成(): void {
        $verify = new VerifyHoneypot();
        $html = $verify->form();
        $this->assertStringContainsString("name='_honeypot'", $html);
        $this->assertStringContainsString("type='text'", $html);
        $this->assertStringContainsString("tabindex='-1'", $html);
        $this->assertStringContainsString("autocomplete='off'", $html);
        $this->assertStringContainsString("aria-hidden='true'", $html);
        $this->assertStringContainsString("position:absolute", $html);
    }

    public function test_form_name_オプションで_input_の_name_を変更できる(): void {
        $verify = new VerifyHoneypot(['name' => 'website']);
        $html = $verify->form();
        $this->assertStringContainsString("name='website'", $html);
        $this->assertStringNotContainsString("name='_honeypot'", $html);
    }

    public function test_form_name_は_HTML_エスケープされる(): void {
        $verify = new VerifyHoneypot(['name' => '<script>']);
        $html = $verify->form();
        $this->assertStringNotContainsString("<script>", $html);
        $this->assertStringContainsString("&lt;script&gt;", $html);
    }

    // ---- verify() ----

    public function test_verify_honeypot_が_空なら_true_人間扱い(): void {
        Post::setForTest(['_honeypot' => '']);
        $verify = new VerifyHoneypot();
        $this->assertTrue($verify->verify());
    }

    public function test_verify_honeypot_が_未送信なら_true_人間扱い(): void {
        Post::setForTest([]);
        $verify = new VerifyHoneypot();
        $this->assertTrue($verify->verify());
    }

    public function test_verify_honeypot_に_値が入っていたら_false_ボット扱い(): void {
        Post::setForTest(['_honeypot' => 'spam value']);
        $verify = new VerifyHoneypot();
        $this->assertFalse($verify->verify());
    }

    public function test_verify_カスタム_name_でも_動作する(): void {
        Post::setForTest(['website' => 'http://spam.example.com']);
        $verify = new VerifyHoneypot(['name' => 'website']);
        $this->assertFalse($verify->verify());
    }

    public function test_verify_カスタム_name_で_空なら_true(): void {
        Post::setForTest(['website' => '']);
        $verify = new VerifyHoneypot(['name' => 'website']);
        $this->assertTrue($verify->verify());
    }

    // ---- getErrorMessage() ----

    public function test_getErrorMessage_は_不正な送信を検知しました(): void {
        $verify = new VerifyHoneypot();
        $this->assertSame('不正な送信を検知しました。', $verify->getErrorMessage());
    }

}

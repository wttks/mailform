<?php

namespace AIJOH\Test\Verification;

use AIJOH\Http\Post;
use AIJOH\Http\Session;
use AIJOH\Verification\Verification;
use PHPUnit\Framework\TestCase;

class VerificationTest extends TestCase {

    protected function setUp() : void {
        Post::reset();
        Session::reset();
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown() : void {
        Post::reset();
        Session::reset();
        $_SESSION = [];
        $_POST = [];
    }

    public function test_新形式_キー_true_で_Verify_が有効化される() : void {
        $v = new Verification(['honeypot' => true]);
        $html = $v->getFormTag();
        $this->assertStringContainsString("name='_honeypot'", $html);
    }

    public function test_新形式_キー_配列_で_Verify_に設定が渡る() : void {
        $v = new Verification(['honeypot' => ['name' => 'website']]);
        $html = $v->getFormTag();
        $this->assertStringContainsString("name='website'", $html);
    }

    public function test_新形式_キー_false_で_Verify_は無効化される() : void {
        $v = new Verification(['honeypot' => false, 'csrfToken' => true]);
        $html = $v->getFormTag();
        // honeypot は出ない
        $this->assertStringNotContainsString("_honeypot", $html);
        // csrfToken は出る
        $this->assertStringContainsString("_csrf_token", $html);
    }

    public function test_旧形式_文字列リスト_も互換維持() : void {
        $v = new Verification(['csrfToken', 'honeypot']);
        $html = $v->getFormTag();
        $this->assertStringContainsString("_csrf_token", $html);
        $this->assertStringContainsString("_honeypot", $html);
    }

    public function test_新旧形式の混在も動く() : void {
        $v = new Verification([
            'csrfToken',                              // 旧
            'honeypot' => ['name' => 'website'],      // 新
        ]);
        $html = $v->getFormTag();
        $this->assertStringContainsString("_csrf_token", $html);
        $this->assertStringContainsString("name='website'", $html);
    }

}

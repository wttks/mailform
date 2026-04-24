<?php

namespace AIJOH\Test\Verification\Verify;

use AIJOH\Http\Post;
use AIJOH\Http\Session;
use AIJOH\Verification\Verify\VerifyCsrfToken;
use PHPUnit\Framework\TestCase;

/**
 * VerifyCsrfToken のテスト
 */
class VerifyCsrfTokenTest extends TestCase {

    protected function setUp() : void {
        Post::reset();
        Session::reset();
        $_SESSION = [];
    }

    protected function tearDown() : void {
        Post::reset();
        Session::reset();
        $_SESSION = [];
    }

    // ---- form() ----

    public function test_form_input_を生成しトークンをセッションに保存する(): void {
        $verify = new VerifyCsrfToken();
        $html = $verify->form();
        $this->assertStringContainsString("name='_csrf_token'", $html);
        $this->assertStringContainsString("type='hidden'", $html);
        // セッションにもトークンが保存される
        $this->assertNotEmpty($_SESSION['form_csrf_token']);
        // form() の HTML 内のトークンとセッションのトークンが一致する
        $this->assertStringContainsString("value='" . $_SESSION['form_csrf_token'] . "'", $html);
    }

    public function test_form_既存トークンがあれば再生成しない(): void {
        $_SESSION['form_csrf_token'] = 'existing-token-value';
        $verify = new VerifyCsrfToken();
        $html = $verify->form();
        $this->assertStringContainsString("value='existing-token-value'", $html);
        $this->assertSame('existing-token-value', $_SESSION['form_csrf_token']);
    }

    public function test_form_key_オプションで_input_の_name_を変更できる(): void {
        $verify = new VerifyCsrfToken(['key' => 'my_token']);
        $html = $verify->form();
        $this->assertStringContainsString("name='my_token'", $html);
    }

    // ---- verify() ----

    public function test_verify_セッションと_POST_のトークンが一致すれば_true(): void {
        $_SESSION['form_csrf_token'] = 'matching-token';
        Post::setForTest(['_csrf_token' => 'matching-token']);
        $verify = new VerifyCsrfToken();
        $this->assertTrue($verify->verify());
    }

    public function test_verify_トークンが不一致なら_false(): void {
        $_SESSION['form_csrf_token'] = 'server-token';
        Post::setForTest(['_csrf_token' => 'wrong-token']);
        $verify = new VerifyCsrfToken();
        $this->assertFalse($verify->verify());
    }

    public function test_verify_セッションにトークンが無ければ_false(): void {
        Post::setForTest(['_csrf_token' => 'submitted-token']);
        $verify = new VerifyCsrfToken();
        $this->assertFalse($verify->verify());
    }

    public function test_verify_POST_にトークンが無ければ_false(): void {
        $_SESSION['form_csrf_token'] = 'server-token';
        Post::setForTest([]);
        $verify = new VerifyCsrfToken();
        $this->assertFalse($verify->verify());
    }

    public function test_verify_カスタム_key_でも動作する(): void {
        $_SESSION['form_csrf_token'] = 'token-x';
        Post::setForTest(['my_token' => 'token-x']);
        $verify = new VerifyCsrfToken(['key' => 'my_token']);
        $this->assertTrue($verify->verify());
    }

    // ---- getErrorMessage() ----

    public function test_getErrorMessage_は_トークンが一致しません(): void {
        $verify = new VerifyCsrfToken();
        $this->assertSame('トークンが一致しません。再度お試しください。', $verify->getErrorMessage());
    }

}

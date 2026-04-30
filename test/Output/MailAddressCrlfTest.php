<?php

namespace AIJOH\Test\Output;

use AIJOH\Output\Mailer\MailAddress;
use AIJOH\Output\Mailer\SendMailException;
use AIJOH\SecurityPayloads;
use PHPUnit\Framework\TestCase;

/**
 * MailAddress の CRLF インジェクション対策テスト。
 */
class MailAddressCrlfTest extends TestCase {

    // ---- 正常系 ----

    public function test_通常の値は受理される() : void {
        $a = new MailAddress('admin@example.com', '管理者');
        $this->assertSame('admin@example.com', $a->getAddress());
        $this->assertSame('管理者', $a->getName());
    }


    // ---- CRLF / NULL バイト ----

    public function test_address_に_CRLF_含むと例外() : void {
        $this->expectException(SendMailException::class);
        $this->expectExceptionMessage('制御文字');
        new MailAddress(SecurityPayloads::CRLF['mail_bcc_inject']);
    }


    public function test_address_に_LF_のみでも例外() : void {
        $this->expectException(SendMailException::class);
        new MailAddress(SecurityPayloads::CRLF['mail_lf_only']);
    }


    public function test_name_に_CRLF_含むと例外() : void {
        $this->expectException(SendMailException::class);
        $this->expectExceptionMessage('名前');
        new MailAddress('admin@example.com', SecurityPayloads::CRLF['name_inject']);
    }


    public function test_NULL_バイトを含むと例外() : void {
        $this->expectException(SendMailException::class);
        new MailAddress("admin@example.com\x00.evil");
    }


    // ---- assertNoControlChars 静的メソッドとして使える ----

    public function test_assertNoControlChars_静的呼び出し_OK() : void {
        // 例外を投げないことだけ確認
        MailAddress::assertNoControlChars('clean text', 'テスト');
        $this->assertTrue(true);
    }


    public function test_assertNoControlChars_静的呼び出し_NG() : void {
        $this->expectException(SendMailException::class);
        MailAddress::assertNoControlChars("attack\r\n", 'テスト');
    }


    // ---- メールアドレスフォーマット側のチェックは従来通り ----

    public function test_空文字列は例外() : void {
        $this->expectException(SendMailException::class);
        new MailAddress('');
    }


    public function test_不正なメールアドレスは例外() : void {
        $this->expectException(SendMailException::class);
        new MailAddress('not-an-email');
    }
}

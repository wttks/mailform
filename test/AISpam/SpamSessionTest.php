<?php

namespace AIJOH\Test\AISpam;

use AIJOH\AISpam\SpamSession;
use AIJOH\Http\Session;
use PHPUnit\Framework\TestCase;

class SpamSessionTest extends TestCase {

    protected function setUp() : void {
        Session::reset();
        $_SESSION = [];
    }

    protected function tearDown() : void {
        Session::reset();
        $_SESSION = [];
    }

    public function test_初期状態は_isBlocked_false() : void {
        $this->assertFalse(SpamSession::isBlocked());
    }

    public function test_block_すると_isBlocked_true() : void {
        SpamSession::block('test reason');
        $this->assertTrue(SpamSession::isBlocked());
    }

    public function test_block_は_reason_と_at_を保存する() : void {
        SpamSession::block('営業メール');
        $stored = $_SESSION['_aispam_blocked'];
        $this->assertSame('営業メール', $stored['reason']);
        $this->assertIsInt($stored['at']);
    }

    public function test_clear_で_isBlocked_false_に戻る() : void {
        SpamSession::block('reason');
        $this->assertTrue(SpamSession::isBlocked());
        SpamSession::clear();
        $this->assertFalse(SpamSession::isBlocked());
    }

}

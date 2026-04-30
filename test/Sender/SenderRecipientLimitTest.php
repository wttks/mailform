<?php

namespace AIJOH\Test\Sender;

use AIJOH\Sender\Sender;
use PHPUnit\Framework\TestCase;

/**
 * Sender::countRecipients のテスト（Email Pumping 対策の前提検証）。
 *
 * sendAll での実上限超過テストは Formatter / Send の連携が必要で
 * セットアップが重いため、ここでは countRecipients の純関数テストに絞る。
 */
class SenderRecipientLimitTest extends TestCase {

    public function test_to_1件は1() : void {
        $config = [
            '管理者向けメール' => [
                'from' => 'noreply@example.com',
                'to'   => 'admin@example.com',
                'subject' => 'x',
                'body'    => 'y',
            ],
        ];
        $this->assertSame(1, Sender::countRecipients($config));
    }


    public function test_to_cc_bcc_合算() : void {
        $config = [
            '管理者向けメール' => [
                'from' => 'noreply@example.com',
                'to'   => [ 'admin@example.com' ],
                'cc'   => [ 'cc1@example.com', 'cc2@example.com' ],
                'bcc'  => [ 'bcc1@example.com' ],
                'subject' => 'x',
                'body'    => 'y',
            ],
        ];
        $this->assertSame(4, Sender::countRecipients($config));
    }


    public function test_複数のSendMail設定を合算する() : void {
        $config = [
            '管理者向けメール' => [
                'from' => 'noreply@example.com',
                'to'   => [ 'admin@example.com' ],
                'cc'   => [ 'cc1@example.com' ],
                'subject' => 'x',
                'body'    => 'y',
            ],
            '顧客向けメール' => [
                'from' => 'noreply@example.com',
                'to'   => [ 'user@example.com' ],
                'subject' => 'x',
                'body'    => 'y',
            ],
        ];
        // admin 1 + cc 1 + user 1 = 3
        $this->assertSame(3, Sender::countRecipients($config));
    }


    public function test_max_recipients_per_request_キーは集計対象外() : void {
        $config = [
            'max_recipients_per_request' => 5,
            '管理者向けメール' => [
                'from' => 'noreply@example.com',
                'to'   => 'admin@example.com',
                'subject' => 'x',
                'body'    => 'y',
            ],
        ];
        $this->assertSame(1, Sender::countRecipients($config));
    }


    public function test_to_未指定は0() : void {
        $config = [
            '管理者向けメール' => [
                'from' => 'noreply@example.com',
                'subject' => 'x',
                'body'    => 'y',
            ],
        ];
        $this->assertSame(0, Sender::countRecipients($config));
    }


    public function test_配列ではない設定値はスキップ() : void {
        $config = [
            'max_recipients_per_request' => 5,
            'something_else' => 'string',
        ];
        $this->assertSame(0, Sender::countRecipients($config));
    }
}

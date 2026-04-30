<?php

namespace AIJOH\Test\Output;

use AIJOH\Output\Mailer\MailSendBase;
use AIJOH\Output\Mailer\MailSenderFactory;
use AIJOH\Output\Mailer\PHPMailSender;
use PHPUnit\Framework\TestCase;

class MailSenderFactoryTest extends TestCase {

    protected function setUp() : void {
        MailSenderFactory::reset();
    }


    protected function tearDown() : void {
        MailSenderFactory::reset();
    }


    public function test_設定空でデフォルト_PHPMailSender_が返る() : void {
        $sender = MailSenderFactory::create();
        $this->assertInstanceOf(PHPMailSender::class, $sender);
        $this->assertInstanceOf(MailSendBase::class, $sender);
    }


    public function test_type_phpmailer_明示でも_PHPMailSender() : void {
        $sender = MailSenderFactory::create([ 'type' => 'phpmailer' ]);
        $this->assertInstanceOf(PHPMailSender::class, $sender);
    }


    public function test_未対応_type_で例外() : void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("symfony");
        MailSenderFactory::create([ 'type' => 'symfony' ]);
    }


    public function test_register_で独自実装を登録できる() : void {
        $stub = new class extends MailSendBase {
            public function send() : bool { return true; }
        };
        MailSenderFactory::register('custom_stub', fn() => $stub);

        $sender = MailSenderFactory::create([ 'type' => 'custom_stub' ]);
        $this->assertSame($stub, $sender);
    }


    public function test_register_で_phpmailer_も上書きできる() : void {
        $stub = new class extends MailSendBase {
            public function send() : bool { return true; }
        };
        MailSenderFactory::register('phpmailer', fn() => $stub);

        $sender = MailSenderFactory::create([ 'type' => 'phpmailer' ]);
        $this->assertSame($stub, $sender);   // 登録した stub が優先される
    }


    public function test_reset_で登録解除される() : void {
        MailSenderFactory::register('custom', fn() => new PHPMailSender());
        $this->assertContains('custom', MailSenderFactory::availableTypes());

        MailSenderFactory::reset();
        $this->assertNotContains('custom', MailSenderFactory::availableTypes());
    }


    public function test_availableTypes_に_phpmailer_が含まれる() : void {
        $types = MailSenderFactory::availableTypes();
        $this->assertContains('phpmailer', $types);
    }
}

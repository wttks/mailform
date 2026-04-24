<?php

namespace AIJOH\Output\Mailer;

use AIJOH\Output\Mailer\Attachment\FileAttachment;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class PHPMailSender extends MailSendBase {
    
    
    /**
     * PHPMailerを構築する。
     * @return PHPMailer
     * @throws SendMailException 送信元が指定されていない場合
     * @throws Exception
     */
    private function buildPHPMailer() : PHPMailer {
        $mailer = new PHPMailer(true);
        $mailer->CharSet = $this->charset;
        $mailer->Encoding = "base64";
        $mailer->setLanguage('jp');
        
        if( $this->from === null ) {
            throw new SendMailException("送信元が指定されていません。");
        }
        
        $mailer->setFrom($this->from->getAddress(), $this->from->getName());
        $this->addMailList($mailer, "addAddress", $this->getTo());
        $this->addMailList($mailer, "addCC", $this->getCC());
        $this->addMailList($mailer, "addBCC", $this->getBcc());
        $this->addMailList($mailer, "addReplyTo",  $this->getReplyTo());
        
        
        $mailer->Subject = $this->getSubject();
        $mailer->Body = $this->getBody();
        
        $this->addAttachments($mailer);
        return $mailer;
    }
    
    
    /**
     * @param PHPMailer $mailer
     * @param string $type
     * @param MailAddress[] $list
     * @return void
     */
    private function addMailList( PHPMailer $mailer, string $type, array $list ) : void {
        if ( empty($list) ) {
            return;
        }

        foreach ( $list as $address ) {
            if( empty($address) ) {
                continue;
            }
            $mailer->{$type}($address->getAddress(), $address->getName());
        }
    }
    
    /**
     * 添付ファイルを追加する。
     * @param PHPMailer $mailer
     * @return void
     * @throws Exception
     */
    private function addAttachments( PHPMailer $mailer ) : void {
        foreach ( $this->getAttachments() as $attachment ) {
            if ( $attachment instanceof FileAttachment ) {
                $mailer->addAttachment($attachment->getPath(), $attachment->getName());
            }
        }
    }
    
    /**
     * メールを送信する。
     * @return bool
     * @throws SendMailException
     */
    #[\Override] public function send() : bool {
        try {
            $mailer = $this->buildPHPMailer();
            return $mailer->send();
        }catch(Exception $e){
            throw new SendMailException($e->getMessage(), $e->getCode());
        }
    }
}
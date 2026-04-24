<?php

namespace AIJOH\Sender\Send;

use AIJOH\Http\UploadFile;
use AIJOH\Output\Mailer\MailAddress;
use AIJOH\Output\Mailer\MailAddressParser;
use AIJOH\Output\Mailer\PHPMailSender;
use AIJOH\Output\Mailer\SendMailException;
use AIJOH\Validation\Exception\ValidationException;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validation;

class SendMailUser extends AbstractSendBase {
    
    private static array $validateConfig = [
        'from'     => [
            'format'  => [ 'normalize_trim' ],
            'rule'    => 'required',
            'message' => [
                'required' => '送信元のメールアドレスを入力してください。',
            ],
        ],
        'to'      => [
            'format'   => [ 'normalize_trim' ],
            'rule' => [ 'required', 'mail_address' ],
            'message'  => [
                'required' => '送信先のアドレスを指定してください。',
                'mail_address' => '送信先のアドレスを正しく指定してください。',
            ],
        ],
        'replyTo' => [
            'format'   => [ 'normalize_trim' ],
            'rule' => [ 'nullable', 'mail_address' ],
            'message' => [
                'mail_address' => '返信先のアドレスを正しく指定してください。',
            ]
        ],
        'subject' => [
            'rule' => [ 'required', 'string' ],
            'message' => [
                'required' => '件名を入力してください。',
            ]
        ],
        'body'    => [
            'rule' => [ 'required' , 'string' ],
            'message' => [
                'required' => '本文を入力してください。',
            ]
        ],
    ];
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * @param array $config
     * @return array
     * @throws ValidationException
     * @throws ValidationRuleException
     */
    #[\Override] protected function parseConfig( array $config ) : array {
        $validator = new Validation(self::$validateConfig);

        return $validator->validated($config);
    }
    
    
    /**
     * 送信先のアドレスを取得する。
     * @return string
     */
    protected function getToAddress() : MailAddress {
        $mailToKey = $this->config['to'] ?? '';
        return MailAddressParser::parse($mailToKey)[0];
    }
    
    /**
     * 送信先の名前を取得する。
     * @return string
     */
    protected function getToName() : string {
        $mailToNameKey = $this->config['name'] ?? '';
        return $this->format->getValue($mailToNameKey);
    }
    
    
    /**
     * データを送信します。
     * @return bool
     * @throws SendMailException
     */
    #[\Override] public function send() : bool {
        
        $subject = $this->getStringValue('subject');
        $body = $this->getStringValue('body');
        
        $to = $this->getToAddress();
        
        $mailer = new PHPMailSender();
        $mailer->setFromArray((array)( $this->config['from'] ?? [] ))
            ->addToAddress($to)
            ->addReplyToList($this->config['reply_to'] ?? [])
            ->setSubject($subject)
            ->setBody($body);
        
        /*
         * 添付ファイルは送信しない
         *
        $attachments = $this->format->getFormData()->getAttachmentList();
        foreach ( $attachments as $attachment ) {
            if( ! $attachment->exists() ){
                continue;
            }
            if ( $attachment instanceof UploadFile ) {
                $mailer->addAttachmentFile($attachment->getTmpName(), $attachment->getName());
            }
        }
        */
        
        return $mailer->send();
    }
}
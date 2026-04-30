<?php

namespace AIJOH\Sender\Send;

use AIJOH\Http\UploadFile;
use AIJOH\Output\Mailer\MailAddress;
use AIJOH\Output\Mailer\MailAddressParser;
use AIJOH\Output\Mailer\MailSenderFactory;
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
        'mailer'  => [
            'rule' => [ 'nullable' ],
        ],
        'attachments' => [
            'rule' => [ 'nullable' ],
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
        
        $mailer = MailSenderFactory::create($this->config['mailer'] ?? []);
        $mailer->setFromArray((array)( $this->config['from'] ?? [] ))
            ->addToAddress($to)
            ->addReplyToList($this->config['reply_to'] ?? [])
            ->setSubject($subject)
            ->setBody($body);

        // フォーム経由の添付（入力者がアップロードしたファイル）は顧客向けには転送しない。
        // 設置者が config 'attachments' で指定した固定ファイル（ホワイトペーパー等）を添付する。
        $this->addStaticAttachmentsTo($mailer);

        // before_user_send hook: 設置者が独自ヘッダ追加 / 添付差し替え等を行える
        $this->hooks?->dispatch('before_user_send', $mailer, $this->format->getFormData());

        return $mailer->send();
    }
}
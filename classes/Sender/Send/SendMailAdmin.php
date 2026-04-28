<?php

namespace AIJOH\Sender\Send;

use AIJOH\Http\UploadFile;
use AIJOH\Output\Mailer\MailAddress;
use AIJOH\Output\Mailer\MailAddressParser;
use AIJOH\Output\Mailer\PHPMailSender;
use AIJOH\Output\Mailer\SendMailException;
use AIJOH\Results\FormData;
use AIJOH\Validation\Exception\ValidationException;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validation;

/**
 * 管理者へメールを送信するクラス
 */
class SendMailAdmin extends AbstractSendBase {
    
    private static array $validateConfig = [
        'from'    => [
            'format'   => [ 'normalize_trim' ],
            'rule' => [ 'required', 'mail_address', 'max:1' ],
            'message'  => [
                'required'     => '送信元のアドレスを指定してください。',
                'mail_address' => '送信元のアドレスを正しく指定してください。',
                'max'          => '送信元のメールアドレスは1つまで指定できます。',
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
        'cc'      => [
            'format'   => [ 'normalize_trim' ],
            'rule' => [ 'nullable', 'mail_address' ],
            'message' => [
                'mail_address' => 'CCのアドレスを正しく指定してください。',
            ],
        ],
        'bcc'     => [
            'format'   => [ 'normalize_trim' ],
            'rule' => [ 'nullable', 'mail_address' ],
            'message' => [
                'mail_address' => 'BCCのアドレスを正しく指定してください。',
            ]
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
     *
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
     * 送信元メールアドレスを取得する。
     * @return MailAddress
     */
    public function getFrom() : MailAddress {
        try {
            $fromList = MailAddressParser::parse($this->config['from']);
        } catch ( SendMailException $e ) {
            error_log("送信元メールアドレスの解析に失敗しました。バリデーションを通しているので、ここには来ないはずです。");
            exit;
        }
        return $fromList[0];
    }
    
    
    /**
     * 送信元メール
     * @return array
     */
    public function getTo() : array {
        try {
            return MailAddressParser::parse($this->config['to']);
        } catch ( SendMailException $e ) {
            error_log("送信先メールアドレスの解析に失敗しました。バリデーションを通しているので、ここには来ないはずです。");
            exit;
        }
    }
    
    
    
    
    
    /**
     * データを送信します。
     * @return bool
     * @throws SendMailException
     */
    #[\Override] public function send() : bool {
        $from = (array)( $this->config['from'] ?? [] );
        $to = (array)( $this->config['to'] ?? [] );
        $cc = (array)( $this->config['cc'] ?? [] );
        $bcc = (array)( $this->config['bcc'] ?? [] );
        $replyTo = (array)( $this->config['replyTo'] ?? [] );
        
        
        $subject = $this->getStringValue('subject');
        $body = $this->getStringValue('body');
        
        $mailer = new PHPMailSender();
        $mailer->setFromArray($from)
            ->addToList($to)
            ->addCCList($cc)
            ->addBccList($bcc)
            ->addReplyToList($replyTo)
            ->setSubject($subject)
            ->setBody($body);
        
        $attachments = $this->format->getFormData()->getAttachmentList();
        foreach ( $attachments as $attachment ) {
            if ( $attachment instanceof UploadFile ) {
                $mailer->addAttachmentFile($attachment->getTmpName(), $attachment->getName());
            }
        }

        return $mailer->send();
    }
}
<?php

namespace AIJOH\Output\Mailer;

use AIJOH\Output\Mailer\Attachment\FileAttachment;
use AIJOH\Output\Mailer\Attachment\MailAttachment;
use AIJOH\Util\ArrayUtil;
use AIJOH\Util\StrUtil;

/**
 * メール送信の基底クラス
 */
abstract class MailSendBase {
    
    /**
     * 送信先のタイプ
     */
    protected const TO = "TO";
    
    /**
     * CCのタイプ
     */
    protected const CC = "CC";
    
    /**
     * BCCのタイプ
     */
    protected const BCC = "BCC";
    
    /**
     * REPLY TO のタイプ
     */
    protected const REPLY_TO = "REPLY_TO";
    
    /**
     * 送信元メールアドレス
     * @var MailAddress|null
     */
    protected ?MailAddress $from = null;
    
    /**
     * 送信先(TO,CC,BCC)のメールアドレスのリスト
     * @var MailAddress[]|null
     */
    protected ?array $addressList = null;
    
    /**
     * 送信先(TO,CC,BCC)のタイプ
     * @var string[]
     */
    protected array $sendType = [
        self::TO,
        self::CC,
        self::BCC,
        self::REPLY_TO,
    ];
    
    
    /**
     * 添付ファイルの一覧
     * @var MailAttachment[]
     */
    protected array $attachments = [];
    
    /**
     * 件名
     * @var string
     */
    protected string $subject = "";
    
    /**
     * 本文
     * @var string
     */
    protected string $body = "";
    
    /**
     * 文字コード
     * @var string
     */
    protected string $charset = "UTF-8";
    
    /**
     * 送信元メールアドレスを設定する。
     * @param MailAddress $from
     * @return self
     */
    public final function setFromAddress( MailAddress $from ) : self {
        $this->from = $from;
        return $this;
    }
    
    /**
     * 送信元メールアドレスを設定する。
     * @param string $address メールアドレス
     * @param string $name 名前
     * @return self
     * @throws SendMailException メールアドレスが不正な場合
     */
    public final function setFrom( string $address, string $name = "" ) : self {
        $this->from = new MailAddress($address, $name);
        return $this;
    }
    
    /**
     * 送信元のアドレスを設定する。
     * @param array $data
     * @return $this
     * @throws SendMailException
     */
    public final function setFromArray( array $data ) : self {
        $count = count($data);
        if ( $count === 0 ) {
            return $this;
        }
        if ( $count === 1 ) {
            $key = array_keys($data)[0];
            $value = array_values($data)[0];
            if ( is_string($key) ) {
                return $this->setFrom($key, $value);
            } else {
                return $this->setFrom($value);
            }
        }
        
        return $this->setFrom($data[0], $data[1]);
    }
    
    
    /**
     * 送信元メールアドレスを取得する。
     * @return MailAddress|null
     */
    public final function getFrom() : ?MailAddress {
        return $this->from;
    }
    
    /**
     * 送信先(TO,CC,BCC)のタイプが正しいかチェックを行う
     * @param string $type
     * @return string
     */
    protected final function validateSendType( string $type ) : string {
        $type = strtoupper($type);
        if ( ! in_array($type, $this->sendType) ) {
            throw new \InvalidArgumentException("送信先のタイプが不正です。");
        }
        return $type;
    }
    
    /**
     * 送信先(TO,CC,BCC)のメールアドレスのリストを追加する。
     * @param string $type
     * @param MailAddress $address
     * @return $this
     */
    protected final function addSendAddress( string $type, MailAddress $address ) : self {
        $type = $this->validateSendType($type);
        $this->addressList[ $type ][] = $address;
        return $this;
    }
    
    
    /**
     * 送信先(TO,CC,BCC)のメールアドレスのリストを追加する。
     * @param string $type 送信先のタイプ
     * @param array|MailAddress[] $addressList 追加するメールアドレスの一覧
     * @return self
     */
    protected final function addSendAddressAll( string $type, array $addressList ) : self {
        foreach ( $addressList as $address ) {
            if ( ! ( $address instanceof MailAddress ) ) {
                throw new \InvalidArgumentException("MailAddress型以外のデータが含まれています。");
            }
            $this->addressList[$type][] = $address;
        }
        return $this;
    }
    
    /**
     * 送信先(TO,CC,BCC)のメールアドレスのリストを追加する。
     * @param string $type 送信先のタイプ
     * @param string $address メールアドレス
     * @param string $name 名前
     * @return self
     * @throws SendMailException メールアドレスが不正な場合
     */
    protected final function addSend( string $type, string $address, string $name = "" ) : self {
        $this->addSendAddress($type, new MailAddress($address, $name));
        return $this;
    }
    
    /**
     * 送信先(TO,CC,BCC)のメールアドレスのリストを追加する。
     * @param string $type 送信先のタイプ
     * @param array $list メールアドレスのリスト
     * @return $this
     * @throws SendMailException メールアドレスが不正な場合
     */
    protected function addSendList( string $type, array $list ) : self {
        if ( empty($list) ) {
            return $this;
        }
        
        $addressList = MailAddressParser::parse($list);
        foreach ( $addressList as $address ) {
            $this->addSendAddress($type, $address);
        }
        return $this;
    }
    
    
    /**
     * 送信先(TO,CC,BCC)のメールアドレスのリストを取得する。
     * @param string $type
     * @return MailAddress[] 対象のメールアドレス。存在しない場合は空の配列。
     * @ @throws \InvalidArgumentException 取得するデータタイプが不正な場合
     */
    protected final function getSendAddress( string $type ) : array {
        $type = $this->validateSendType($type);
        return $this->addressList[ $type ] ?? [];
    }
    
    
    /**
     * 送信先(TO)のメールアドレスを追加する。
     * @param MailAddress $address
     * @return $this
     */
    public function addToAddress( MailAddress $address ) : self {
        $this->addSendAddress(self::TO, $address);
        return $this;
    }
    
    
    /**
     * 送信先(TO)のメールアドレスをまとめて追加する。
     * @param MailAddress[] $address
     * @return self
     */
    public function addToAll( array $address ) : self {
        return $this->addSendAddressAll(self::TO, $address);
    }
    
    /**
     * 送信先(TO)のメールアドレスを追加する。
     * @param string $address メールアドレス
     * @param string $name 名前
     * @return $this
     * @throws SendMailException メールアドレスが不正な場合
     */
    public function addTo( string $address, string $name = "" ) : self {
        $this->addSend(self::TO, $address, $name);
        return $this;
    }
    
    /**
     * 送信先(TO)のメールアドレスのリストを追加する。
     * @param array $list メールアドレスのリスト
     * @return $this
     */
    public function addToList( array $list ) : self {
        $this->addSendList(self::TO, $list);
        return $this;
        
    }
    
    /**
     * 送信先(TO)のメールアドレスを取得する。
     * @return MailAddress[] 対象のメールアドレス。存在しない場合は空の配列。
     */
    public function getTo() : array {
        return $this->getSendAddress(self::TO);
    }
    
    /**
     * 送信先(CC)のメールアドレスを追加。
     * @param MailAddress $address
     * @return $this
     */
    public function addCCAddress( MailAddress $address ) : self {
        $this->addSendAddress(self::CC, $address);
        return $this;
    }
    
    
    /**
     * 送信先(CC)のメールアドレスをまとめて追加する。
     * @param array|MailAddress[] $address メールアドレス一覧
     * @return self
     */
    public function addCCAll( array $address ) : self {
        return $this->addSendAddressAll(self::CC, $address);
    }
    
    /**
     * 送信先(CC)のメールアドレスを追加する。
     * @param string $address メールアドレス
     * @param string $name 名前
     * @return $this
     * @throws SendMailException メールアドレスが不正な場合
     */
    public function addCC( string $address, string $name = "" ) : self {
        $this->addSend(self::CC, $address, $name);
        return $this;
    }
    
    /**
     * 送信先(CC)のメールアドレスのリストを追加する。
     * @param array $list メールアドレスのリスト
     * @return $this
     */
    public function addCCList( array $list ) : self {
        $this->addSendList(self::CC, $list);
        return $this;
    }
    
    /**
     * 送信先(CC)のメールアドレスを取得する。
     * @return MailAddress[] 対象のメールアドレス。存在しない場合は空の配列。
     */
    public function getCC() : array {
        return $this->getSendAddress(self::CC);
    }
    
    /**
     * 送信先(BCC)のメールアドレスを追加する。
     * @param MailAddress $address
     * @return $this
     */
    public function addBccAddress( MailAddress $address ) : self {
        $this->addSendAddress(self::BCC, $address);
        return $this;
    }
    
    
    /**
     * 送信先(BCC)のメールアドレスを追加する。
     * @param array|MailAddress[] $list メールアドレスの一覧
     * @return self
     */
    public function addBccAll(array $list ) : self {
        return $this->addSendAddressAll(self::BCC, $list);
    }
    
    /**
     * 送信先(BCC)のメールアドレスを追加する。
     * @param string $address メールアドレス
     * @param string $name 名前
     * @return $this
     * @throws SendMailException メールアドレスが不正な場合
     */
    public function addBcc( string $address, string $name = "" ) : self {
        $this->addSend(self::BCC, $address, $name);
        return $this;
    }
    
    /**
     * 送信先(BCC)のメールアドレスのリストを追加する。
     * @param array $list メールアドレスのリスト
     * @return $this
     */
    public function addBccList( array $list ) : self {
        $this->addSendList(self::BCC, $list);
        return $this;
    }
    
    /**
     * 送信先(BCC)のメールアドレスを取得する。
     * @return MailAddress[] 対象のメールアドレス。存在しない場合は空の配列。
     */
    public function getBcc() : array {
        return $this->getSendAddress(self::BCC);
    }
    
    
    /**
     * ReplyToのメールアドレスを設定する。
     * @param MailAddress $address
     * @return $this
     */
    public function addReplyToAddress( MailAddress $address ) : self {
        $this->addSendAddress(self::REPLY_TO, $address);
        return $this;
    }
    
    /**
     * ReplyToをまとめて追加する。
     * @param array|MailAddress[] $address
     * @return self
     */
    public function addReplyToAll(array $address) : self {
        return $this->addSendAddressAll(self::REPLY_TO, $address);
    }
    
    
    /**
     * ReplyToのメールアドレスを設定する。
     * @param string $address
     * @param string $name
     * @return $this
     * @throws SendMailException メールアドレスが不正な場合
     */
    public function addReplyTo( string $address, string $name = "" ) : self {
        $this->addSend(self::REPLY_TO, $address, $name);
        return $this;
    }
    
    
    /**
     * ReplyToのメールアドレスのリストを追加する。
     * @param array $config 設定情報
     * @return $this
     * @throws SendMailException メールアドレスが不正な場合
     */
    public function addReplyToList( array $config ) : self {
        return $this->addSendList(self::REPLY_TO, $config);
    }
    
    /**
     * ReplyToのメールアドレスを取得する。
     * @return MailAddress[]
     */
    public function getReplyTo() : array {
        return $this->getSendAddress(self::REPLY_TO);
    }
    
    
    /**
     * 文字コードを設定する。
     * @param string $charset
     * @return $this
     */
    public function setCharset( string $charset ) : self {
        $this->charset = $charset;
        return $this;
    }
    
    /**
     * 件名を設定する。
     * @param string $subject
     * @return self
     * @throws SendMailException 件名に CRLF / NULL バイトが含まれている場合（ヘッダインジェクション対策）
     */
    public function setSubject( string $subject ) : self {
        MailAddress::assertNoControlChars($subject, '件名');
        $this->subject = $subject;
        return $this;
    }
    
    /**
     * 件名を取得する。
     * @return string 件名
     */
    public function getSubject() : string {
        return $this->subject;
    }
    
    /**
     * Bodyを設定する。
     * @param string $body
     * @return self
     */
    public function setBody( string $body ) : self {
        $this->body = $body;
        return $this;
    }
    
    /**
     * 本文を取得する。
     * @return string
     */
    public function getBody() : string {
        return $this->body;
    }
    
    
    /**
     * 添付ファイルを追加する。
     * @param MailAttachment $attachment
     * @return $this
     */
    public function addAttachment( MailAttachment $attachment ) : self {
        $this->attachments[] = $attachment;
        return $this;
    }
    
    /**
     * 指定した場所のファイルを元に添付ファイルを生成する。
     * @param string $path
     * @param string $name
     * @return $this
     */
    public function addAttachmentFile( string $path, string $name = "" ) : self {
        $attachment = new FileAttachment($path, $name);
        return $this->addAttachment($attachment);
    }
    
    /**
     * 添付ファイルの一覧を取得する。
     * @return MailAttachment[]
     */
    public function getAttachments() : array {
        return $this->attachments;
    }
    
    
    /**
     * メールの送信を行う。
     * @return bool
     * @throws SendMailException メール送信時の例外
     */
    public abstract function send() : bool;
    
    
}
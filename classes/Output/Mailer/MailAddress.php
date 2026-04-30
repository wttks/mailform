<?php

namespace AIJOH\Output\Mailer;

use AIJOH\Util\StrUtil;

class MailAddress {
    
    /**
     * メールアドレス
     * @var string
     */
    public string $address;
    
    /**
     * 名前
     * @var string
     */
    public string $name;
    
    /**
     * コンストラクタ
     * @param string $address メールアドレス
     * @param string $name 名前
     * @throws SendMailException メールアドレスが不正な場合
     */
    public function __construct( string $address, string $name = "" ) {
        self::assertNoControlChars($address, 'メールアドレス');
        self::assertNoControlChars($name, '名前');
        $this->validateEmail($address);

        $this->address = $address;
        $this->name = $name;
    }


    /**
     * CRLF / NULL バイト等の制御文字が含まれないか検証する。
     *
     * メールヘッダインジェクション対策: 攻撃者が `to` や `name` に
     * "\r\nBcc: attacker@evil.com" を注入して他人への送信を試みるのを
     * 前段で阻止する。PHPMailer 側でも検出するが、ここで早期に明確な
     * エラーで弾く（より分かりやすいメッセージで）。
     *
     * @throws SendMailException
     */
    public static function assertNoControlChars( string $value, string $fieldLabel = '値' ) : void {
        if ( preg_match('/[\\x00\\r\\n]/', $value) === 1 ) {
            throw new SendMailException(
                "{$fieldLabel}に制御文字 (改行・NULL) が含まれています。"
            );
        }
    }


    /**
     * メールアドレスのチェックを行う。
     * @param string $email メールアドレス
     * @return void
     * @throws SendMailException メールアドレスが不正な場合
     */
    private function validateEmail( string $email ) : void {
        if ( $email === "" ) {
            throw new SendMailException("メールアドレスが指定されていません。");
        }
        if ( ! StrUtil::isEmail($email) ) {
            throw new SendMailException("メールアドレスが不正です。不正なメールアドレス:" . $email);
        }
    }
    
    /**
     * 文字列に変換する。
     * @return string
     */
    public function __toString() : string {
        return $this->name . "<" . $this->address . ">";
    }
    
    
    /**
     * メールアドレスを取得する。
     * @return string
     */
    public function getAddress() : string {
        return $this->address;
    }
    
    /**
     * 名前を取得する。
     * @return string
     */
    public function getName() : string {
        return $this->name;
    }
    
}
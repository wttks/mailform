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
     * ASCII 制御文字 (NUL〜US, \x00-\x1f) が含まれないか検証する。
     *
     * メールヘッダインジェクション対策: 攻撃者が `to` / `name` / `subject` に
     * "\r\nBcc: attacker@evil.com" を注入して他人への送信を試みるのを
     * 前段で阻止する。CRLF (\r\n) と NUL (\x00) 以外にも、VT (\x0b) /
     * FF (\x0c) などの制御文字を含めて一律に弾く（メアド・名前・件名の
     * いずれにも正当な用途は無いため、防御深化として有効）。
     *
     * 日本語の表示名は UTF-8 で 0x80 以上のバイトになるためこの検査の影響は受けない。
     *
     * @throws SendMailException
     */
    public static function assertNoControlChars( string $value, string $fieldLabel = '値' ) : void {
        if ( preg_match('/[\\x00-\\x1f]/', $value) === 1 ) {
            throw new SendMailException(
                "{$fieldLabel}に制御文字が含まれています。"
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
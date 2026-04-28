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
        $this->validateEmail($address);
        
        $this->address = $address;
        $this->name = $name;
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
<?php

namespace AIJOH\Security;
/**
 * Google reCAPTCHA v2の設定・対応を行うクラスです。
 */
class Recaptcha2 {
    
    private string $siteKey;
    
    private string $secretKey;
    
    
    /**
     * reCAPTCHAの検証URL
     * @var string
     */
    private static string $recaptchaVerifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    
    
    public function __construct( string $siteKey, string $secretKey ) {
        $this->siteKey = $siteKey;
        $this->secretKey = $secretKey;
    }
    

    public function setField() : string {
        return '<div class="g-recaptcha" data-sitekey="' . $this->siteKey . '"></div>';
    }
    
    
}
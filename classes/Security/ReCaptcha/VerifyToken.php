<?php

namespace AIJOH\Security\ReCaptcha;

class VerifyToken {
    
    /**
     * reCAPTCHAの検証URL
     * @var string
     */
    private static string $recaptchaVerifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    
    /**
     * シークレットキー
     * @var string シークレットキー
     */
    private string $secretKey;
    
    /**
     * コンストラクタ
     * @param string $secretKey
     */
    public function __construct( string $secretKey ) {
        $this->secretKey = $secretKey;
    }
    
    /**
     * reCAPTCHAのトークンを検証する。
     * @return bool
     */
    public function verify( ) : bool {
        $token = $this->getToken();
        $resp = $this->getResponse($token);
        return $resp['success'] ?? false;
    }
    
    /**
     * reCAPTCHAのトークンを検証する。(v3対応)
     * @param float $score
     * @return bool
     */
    public function verifyScore( float $score = 0.5 ) : bool {
        $token = $this->getToken();
        $resp = $this->getResponse($token);
        return $resp['score'] >= $score;
    }
    
    private function getToken() : string {
        return filter_input(INPUT_POST,"g-recaptcha-response");
    }
    
    /**
     * reCapthcaのトークンを検証する。
     * @param string $token トークン
     * @return array|mixed
     */
    private function getResponse($token) {
        $data = [
            'secret' => $this->secretKey,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'],
        ];
        $ch = curl_init();
        curl_setopt_array($ch,[
            CURLOPT_URL =>  self::$recaptchaVerifyUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return ! empty($response) ? json_decode($response, true) : [];
    }
}
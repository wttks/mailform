<?php

namespace AIJOH\Verification\Verify;

trait ReCaptchaVerify {
    
    /**
     * reCaptchaのチェックURL
     * @var string
     */
    private static string $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    
    /**
     * シークレットキー
     * @var
     */
    private string $secretKey;
    
    
    private function verifySuccess() : bool {
        $response = $this->getReCaptchaResponse();
        if( empty($response) ){
            return false;
        }
        return $response['success'] ?? false;
    }
    
    /**
     * スコアをチェックする
     * @param float $score
     * @return bool
     */
    private function verifyScore( float $score ) : bool {
        $response = $this->getReCaptchaResponse();
        if( empty($response) ){
            return false;
        }
        
        return $response['score'] >= $score;
    }
    
    /**
     * reCaptchaのレスポンスを取得する
     * @return array|false
     */
    private function getReCaptchaResponse() : array|false {
        $token = filter_input(INPUT_POST, 'g-recaptcha-response');
        if ( empty($token) ) {
            return false;
        }
        $data = [
            'secret'   => $this->secretKey,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'],
        ];
        $results = $this->sendToken($data);
        return $results;
        
    }
    
    
    /**
     * トークンを送信した結果を取得する
     * @param array $data
     * @return array
     */
    public function sendToken( array $data ) : array {
        if ( is_callable('curl_init') ) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => self::$verifyUrl,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $result = curl_exec($ch);
            curl_close($ch);
            return json_decode($result, true);
        }
        
        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($data),
            ],
        ];
        $context = stream_context_create($options);
        $contents = file_get_contents(self::$verifyUrl, false, $context);
        return json_decode($contents, true);
    }
    
    
}
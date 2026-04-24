<?php

namespace AIJOH\Http;

/**
 * Class CsrfToken
 *
 * This class is used for generating and validating CSRF tokens.
 *
 * @package Your\Namespace
 */
class CsrfToken {
    
    private Session $session;
    
    private const KEY = 'csrf_token';
    
    /**
     * CsrfToken constructor.
     */
    private function __construct() {
        $this->session = Session::getInstance();
    }
    
    public static function getInstance() : CsrfToken {
        static $instance = null;
        if ( $instance === null ) {
            $instance = new CsrfToken();
        }
        return $instance;
    }
    
    /**
     * セッショントークンを取得する。
     * @return string|null
     */
    private function getSessionToken() : ?string {
        return $this->session->get(self::KEY);
    }
    
    
    /**
     * トークンを設定する・
     * @param string $token
     * @return void
     */
    private function setToken( string $token ) : void {
        $this->session->set(self::KEY, $token);
    }
    
    /**
     * トークンを取得する。
     * @return string
     */
    public function getToken() : string {
        $token = $this->getSessionToken();
        if ( $token === null ) {
            $token = $this->generateToken();
            $this->setToken($token);
        }
        return $token;
    }
    
    
    /**
     * トークンを生成する。
     * @return string
     */
    private function generateToken() : string {
        try {
            return bin2hex(random_bytes(32));
        } catch ( \Exception $e ) {
            return sha1(uniqid('', true));
        }
    }
    
    /**
     * トークンをチェックする。
     * @param string $token
     * @return bool
     */
    public function checkToken( string $token ) : bool {
        $sessionToken = $this->getSessionToken();
        if ( $sessionToken === null ) {
            return false;
        }
        
        $results = $sessionToken === $token;
        error_log("sessionToken:{$sessionToken} token:{$token} results:{$results}");
        return $results;
    }
    
}

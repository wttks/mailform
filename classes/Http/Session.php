<?php

namespace AIJOH\Http;

class Session {
    
    private static ?Session $instance = null;
    
    private function __construct() {
        // CLI / テスト環境では headers_sent でセッションを開始できないのでスキップする
        if ( session_status() !== PHP_SESSION_ACTIVE && ! headers_sent() ) {
            session_start();
        }
    }


    public static function getInstance() : Session {
        if ( self::$instance === null ) {
            self::$instance = new Session();
        }
        return self::$instance;
    }


    /**
     * シングルトンをリセットする。次回 getInstance で再生成される。
     * @internal テスト用
     */
    public static function reset() : void {
        self::$instance = null;
    }
    
    
    public function get( string $key, mixed $default = null ) : mixed {
        return $_SESSION[ $key ] ?? $default;
    }
    
    
    public function set( string $key, mixed $value ) : void {
        $_SESSION[ $key ] = $value;
    }
    
    
    public function remove( string $key ) : void {
        unset($_SESSION[ $key ]);
    }
    
    
    public function clear() : void {
        $_SESSION = [];
    }
    
    
    
    public function regenarate() : bool {
        return session_regenerate_id(true);
    }
    
    
    public function destroy() : void {
        $this->clear();
        session_destroy();
    }
}
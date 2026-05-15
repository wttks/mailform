<?php

namespace AIJOH\Http;

class Session {
    
    private static ?Session $instance = null;
    
    private function __construct() {
        // CLI / テスト環境では headers_sent でセッションを開始できないのでスキップする
        if ( session_status() !== PHP_SESSION_ACTIVE && ! headers_sent() ) {
            // セッション Cookie を安全な属性で発行する
            // - HttpOnly: JS からアクセス不可
            // - Secure:   HTTPS でのみ送信（リバースプロキシ越しは X-Forwarded-Proto も確認）
            // - SameSite=Lax: クロスサイトの GET 以外には送らない（CSRF 緩和）
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }


    /**
     * 現在のリクエストが HTTPS か判定する。
     * リバースプロキシ越しの X-Forwarded-Proto 判定は TrustedProxy に委譲。
     */
    private static function isHttps() : bool {
        return TrustedProxy::isHttps();
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
    
    
    
    /**
     * セッション ID を再生成する（セッション固定化対策）。
     */
    public function regenerate() : bool {
        if ( session_status() !== PHP_SESSION_ACTIVE ) {
            return false;
        }
        return session_regenerate_id(true);
    }
    
    
    public function destroy() : void {
        $this->clear();
        session_destroy();
    }
}
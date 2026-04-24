<?php

namespace AIJOH\Http;

class Session {
    
    private static ?Session $instance = null;
    
    private function __construct() {
        if( session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
    
    
    public static function getInstance() : Session {
        if ( self::$instance === null ) {
            self::$instance = new Session();
        }
        return self::$instance;
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
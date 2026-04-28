<?php

namespace AIJOH\Form\Draft;

/**
 * PHP の setcookie() と $_COOKIE を使った CookieIO 実装。
 * 同一リクエスト内で set 直後に get で読めるよう、$_COOKIE も同時更新する。
 */
class PhpCookieIO implements CookieIO {

    public function get( string $name ) : ?string {
        $value = $_COOKIE[ $name ] ?? null;
        return is_string($value) ? $value : null;
    }


    public function getAll() : array {
        $result = [];
        foreach ( $_COOKIE as $name => $value ) {
            if ( is_string($value) ) {
                $result[ (string) $name ] = $value;
            }
        }
        return $result;
    }


    public function set( string $name, string $value, array $options ) : void {
        setcookie($name, $value, $options);
        // 同一リクエスト内で読めるよう即時反映
        $_COOKIE[ $name ] = $value;
    }


    public function delete( string $name, array $options ) : void {
        $options['expires'] = 1;
        setcookie($name, '', $options);
        unset($_COOKIE[ $name ]);
    }
}

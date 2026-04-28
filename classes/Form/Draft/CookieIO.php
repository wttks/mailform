<?php

namespace AIJOH\Form\Draft;

/**
 * Cookie の読み書きを抽象化したインターフェース。
 * 本番では PhpCookieIO（setcookie / $_COOKIE 直接操作）、
 * テストでは ArrayCookieIO を注入して setcookie の output before headers 問題を回避する。
 */
interface CookieIO {

    public function get( string $name ) : ?string;

    /** @return array<string, string> name => value の連想配列 */
    public function getAll() : array;

    /**
     * @param array{expires?:int, path?:string, domain?:string, secure?:bool, httponly?:bool, samesite?:string} $options
     */
    public function set( string $name, string $value, array $options ) : void;

    /**
     * @param array{path?:string, domain?:string, secure?:bool, httponly?:bool, samesite?:string} $options
     */
    public function delete( string $name, array $options ) : void;
}

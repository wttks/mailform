<?php

namespace AIJOH\Form\Draft;

/**
 * テスト用の CookieIO 実装。
 * setcookie() を使わず内部の連想配列で Cookie を扱う。
 * set/delete の操作履歴を sets / deletes に蓄積し、検証可能にする。
 */
class ArrayCookieIO implements CookieIO {

    /** @var array<string, string> */
    public array $cookies = [];

    /** @var array<int, array{name:string, value:string, options:array}> */
    public array $sets = [];

    /** @var array<int, array{name:string, options:array}> */
    public array $deletes = [];


    /**
     * 初期 Cookie を渡してインスタンス化（リクエスト時の $_COOKIE 相当）。
     */
    public function __construct( array $initial = [] ) {
        foreach ( $initial as $name => $value ) {
            $this->cookies[ (string) $name ] = (string) $value;
        }
    }


    public function get( string $name ) : ?string {
        return $this->cookies[ $name ] ?? null;
    }


    public function getAll() : array {
        return $this->cookies;
    }


    public function set( string $name, string $value, array $options ) : void {
        $this->cookies[ $name ] = $value;
        $this->sets[] = [ 'name' => $name, 'value' => $value, 'options' => $options ];
    }


    public function delete( string $name, array $options ) : void {
        unset($this->cookies[ $name ]);
        $this->deletes[] = [ 'name' => $name, 'options' => $options ];
    }
}

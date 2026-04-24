<?php

namespace AIJOH\Util;

class ArrayUtil {
    
    /**
     * 配列($array)からキーで指定した値($key)を取得する。
     * 配列の中にキーが存在しない場合はデフォルト値($default)を返す。
     * キーはドット区切りで指定することで、多次元配列の値を取得する。
     * ドットで区切られたキーはglobパターンとして解釈される。
     *
     * @param array $array 配列
     * @param string $key キー
     * @param mixed|null $default デフォルト値
     * @return mixed
     */
    public static function get( array $array, string $key, mixed $default = null ) : mixed {
        $ans = self::getKeyValueList($array, $key);
        $count = count($ans);
        if( $count === 0 ){
            return $default;
        }else if( $count === 1 ){
            return array_values($ans)[0];
        }else {
            return array_values($ans);
        }
    }
    
    /**
     * データの設定を行う。
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set( array &$array, string $key, mixed $value ) : void {
        if ( $key === '' ) {
            return;
        }
        if ( ! StrUtil::contains($key, '.') ) {
            $array[ $key ] = $value;
            return;
        }
        
        $keyList = explode('.', $key);
        $lastKey = array_pop($keyList);
        $target = &$array;
        foreach ( $keyList as $key ) {
            if ( ! isset($target[ $key ]) ) {
                $target[ $key ] = [];
            }
            $target = &$target[ $key ];
        }
        $target[ $lastKey ] = $value;
    }
    
    
    /**
     * パターンにキーがマッチするかどうかを判別する。
     * @param string $pattern
     * @param string $key
     * @return bool
     */
    public static function matchKey(string $pattern,string $key) : bool {
        return fnmatch($pattern, $key);
    }
    

    
    /**
     * 配列($array)からキーで指定した値($key)に対応する値と、その値のキーを取得する。
     * キーはドット区切りで指定することで、多次元配列の値を取得する。
     * また、ドットで区切られたキーはfnmatchパターンとして解釈される。
     * 戻り値は、[キー => 値]の配列となる。
     * ※戻り値のキーの値は、多次元配列の場合.で結合された文字列となる
     * キーは*等を展開した値(*等の場合は対応した実際のキー値)となる。
     *
     * $a = [ 0 => [ 'a' => 'b' ], 1 => [ 'a' => 'c' ] ];
     * Arrays::getKeyValueList($a, '*.a');
     * の場合下記の様な配列が返る。
     * [ '0.a' => 'b' ,  '1.a' => 'c' ]
     *
     * @param array $array
     * @param string $key
     * @return array
     */
    public static function getKeyValueList( array $array, string $key ) : array {
        $results = [ '' => $array ];
        if ( $key === '' ) {
            return $results;
        }
        
        $keyList = explode('.', $key);
        return self::getArray($results, $keyList);
    }
    
    
    /**
     * @param array $array
     * @param array $keyList
     * @return array
     */
    private static function getArray( array $array, array $keyList ) : array {
        if ( count($keyList) === 0 ) {
            return $array;
        }
        
        $key = array_shift($keyList);
        $results = [];
        foreach ( $array as $basePath => $subArray ) {
            if ( ! is_array($subArray) ) {
                continue;
            }
            foreach ( $subArray as $subKey => $value ) {
                if ( fnmatch($key, $subKey) ) {
                    $path = $basePath !== '' ? $basePath . '.' . $subKey : $subKey;
                    $results[ $path ] = $value;
                }
            }
        }
        
        if ( empty($results) ) {
            return [];
        }
        return self::getArray($results, $keyList);
    }
    
    
    public static function isAllTrue( array $array, callable $callback ) : bool {
        foreach ( $array as $value ) {
            if ( ! $callback($value) ) {
                return false;
            }
        }
        return true;
    }
    
    
    /**
     * 配列の中が全て空か、全ての値が空の配列、nullまたは空文字で構成されている場合にtrueを返す。
     * @param array $array
     * @return bool
     */
    public static function isEmpty( array $array ) : bool {
        if ( count($array) === 0 ) {
            return true;
        }
        foreach ( $array as $value ) {
            if ( ! ObjectUtil::isEmpty($value) ) {
                return false;
            }
        }
        return true;
    }
    
    
    /**
     * 多次元配列を1次元の配列にする。
     * @param array $array
     * @return array
     */
    public static function flatten( array $array ) : array {
        $results = [];
        foreach($array as $value ){
            if( is_array($value) ){
                $results = array_merge($results, self::flatten($value));
            }else{
                $results[] = $value;
            }
        }
        return $results;
    }
    
    
    public static function isHash( array $array ) : bool {
        $i = 0;
        foreach ( $array as $key => $value ) {
            if ( $key !== $i ) {
                return true;
            }
            $i++;
        }
        return false;
    }
    
    
    /**
     * 配列の並び替えと重複排除を行う。
     * @param array $array 配列
     * @param int $flags
     * @return array
     */
    public static function sortUnique( array $array , int $flags = SORT_REGULAR ) : array {
        $results = array_unique($array, $flags);
        sort($results,$flags);
        return $results;
    }
    
    
    /**
     * 配列の中身を再帰的にマップする。
     * @param array $array
     * @param callable $callback
     * @return array
     */
    public static function arrayMapRecursive( array $array, callable $callback ) : array {
        $results = [];
        foreach ( $array as $key => $value ) {
            if ( is_array($value) ) {
                $results[ $key ] = self::arrayMapRecursive($value, $callback);
            } else {
                $results[ $key ] = $callback($value);
            }
        }
        return $results;
    }
    
    
    
    
    public static function hasPatternKey( string $key ) : bool {
        $specialChars = [ '*', '?', '[', ']' , '!' ];
        foreach ( $specialChars as $char ) {
            if ( StrUtil::contains($key, $char)){
                return true;
            }
        }
        return false;
    }
}
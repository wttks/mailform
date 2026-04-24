<?php

namespace AIJOH\Http;

use AIJOH\Util\ArrayUtil;

/**
 * POST投稿されたデータを取得するクラス
 *
 */
class Post {
    
    private array $post = [];
    
    public function __construct() {
        $this->post = $this->buildPost();
    }
    
    /**
     * シングルトンでインスタンスを取得する。
     * @return Post
     */
    public static function getInstance() : Post {
        static $instance = null;
        if ( $instance === null ) {
            $instance = new Post();
        }
        return $instance;
    }
    
    
    /**
     * 投稿されたデータを取得する。
     * @return array
     */
    private function buildPost() : array {
        $post = $_POST;
        if ( !empty($_FILES) ) {
            foreach ( $_FILES as $key => $value ) {
                $post[ $key ] = new UploadFile($key);
            }
        }
        return $post;
    }
    
    
    /**
     * 投稿されたデータを取得する。
     * @return array
     */
    public function getAll() : array {
        return $this->post;
    }
    
    /**
     * 指定した名前に対応するデータを取得する。
     * @param string $name
     * @param mixed|null $default
     * @return mixed
     */
    public function get( string $name, mixed $default = null ) : mixed {
        $results = ArrayUtil::get($this->post, $name, $default);
        return $results;
        
    }
    
    /**
     * 指定したキーに対応する値を取得する。
     * @param string $key
     * @return array
     */
    public function getKeyValueList( string $key ) : array {
        return ArrayUtil::getKeyValueList($this->post, $key);
    }
    
}
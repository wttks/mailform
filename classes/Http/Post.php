<?php

namespace AIJOH\Http;

use AIJOH\Util\ArrayUtil;

/**
 * POST投稿されたデータを取得するクラス
 *
 */
class Post {

    private static ?Post $instance = null;

    private array $post = [];

    /**
     * @param array|null $data 指定すると buildPost をスキップしてそのまま使う（テスト用想定）
     */
    public function __construct( ?array $data = null ) {
        $this->post = $data ?? $this->buildPost();
    }

    /**
     * シングルトンでインスタンスを取得する。
     * @return Post
     */
    public static function getInstance() : Post {
        return self::$instance ??= new Post();
    }

    /**
     * シングルトンをリセットする。次回 getInstance で $_POST から再構築される。
     * @internal テスト用
     */
    public static function reset() : void {
        self::$instance = null;
    }

    /**
     * 指定データを直接シングルトンに差し込む（$_POST を経由しない）。
     * @internal テスト用
     * @param array $data
     */
    public static function setForTest( array $data ) : void {
        self::$instance = new Post($data);
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
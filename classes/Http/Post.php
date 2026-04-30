<?php

namespace AIJOH\Http;

use AIJOH\Util\ArrayUtil;
use AIJOH\Validation\Exception\ValidationException;

/**
 * POST投稿されたデータを取得するクラス
 *
 */
class Post {

    private static ?Post $instance = null;

    private array $post = [];

    /**
     * @param array|null $data 指定すると buildPost をスキップしてそのまま使う（テスト用想定）
     * @throws ValidationException 入力に不正な UTF-8 バイト列が含まれている場合
     */
    public function __construct( ?array $data = null ) {
        if ( $data !== null ) {
            // テスト用: 検証スキップしてそのまま使う
            $this->post = $data;
            return;
        }
        $this->post = $this->buildPost();
        self::assertValidEncoding($this->post);
    }


    /**
     * POST データの UTF-8 妥当性を再帰的に検証する。
     * `_` プレフィックスのキー（`_csrf_token`, `_action` 等）は内部用なのでスキップ。
     * 不正バイト列を検出したら ValidationException を投げる。
     *
     * @throws ValidationException
     */
    private static function assertValidEncoding( array $data ) : void {
        $invalidFields = [];
        foreach ( $data as $key => $value ) {
            if ( is_string($key) && str_starts_with($key, '_') ) {
                continue;
            }
            if ( ! self::isValidUtf8Recursive($value) ) {
                $invalidFields[ (string) $key ] = "入力データに不正な文字コードが含まれています。";
            }
        }
        if ( ! empty($invalidFields) ) {
            error_log("[Post] invalid UTF-8 detected in fields: " . implode(', ', array_keys($invalidFields)));
            throw new ValidationException($invalidFields, "入力データに不正な文字コードが含まれています。");
        }
    }


    /**
     * 値が文字列なら UTF-8 妥当性を検査、配列なら再帰、それ以外はスキップ。
     * UploadFile 等のオブジェクトもスキップ。
     */
    private static function isValidUtf8Recursive( mixed $value ) : bool {
        if ( is_string($value) ) {
            return mb_check_encoding($value, 'UTF-8');
        }
        if ( is_array($value) ) {
            foreach ( $value as $v ) {
                if ( ! self::isValidUtf8Recursive($v) ) {
                    return false;
                }
            }
        }
        return true;
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
     *
     * **HPP（HTTP Parameter Pollution）注意**:
     * クライアントが `name=A&name[]=B` のような送信をすると配列が返ることがある。
     * 単一値前提の処理では呼び出し側で型チェック必須、または getString() を使うこと。
     *
     * @param string $name
     * @param mixed|null $default
     * @return mixed string / array / null など $_POST と同じ型
     */
    public function get( string $name, mixed $default = null ) : mixed {
        $results = ArrayUtil::get($this->post, $name, $default);
        return $results;
    }


    /**
     * 指定キーの値を string として取得する（HPP 対策の安全な変種）。
     * 配列値が来た場合は default を返す（攻撃者の HPP を構造的に防止）。
     *
     * 単一値を期待するロジック（CSRF token / _action / _step 等）で使う。
     */
    public function getString( string $name, string $default = '' ) : string {
        $value = ArrayUtil::get($this->post, $name, $default);
        if ( ! is_string($value) ) {
            return $default;
        }
        return $value;
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
<?php

namespace AIJOH\Http;

use AIJOH\Hook\HookRegistry;
use AIJOH\Results\FormData;
use AIJOH\Validation\Exception\ValidationException;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validation;
use AIJOH\Validation\Validator\Validator;

class Request {
    
    private static ?Request $instance = null;
    
    
    private function __construct() {
    }
    
    /**
     *
     * @return Request
     */
    public static function getInstance() : Request {
        if ( self::$instance === null ) {
            self::$instance = new Request();
        }
        return self::$instance;
    }
    
    /**
     * リクエストメソッドを取得する。
     * @return string リクエストメソッド
     */
    public function getMethod() : string {
        return $_SERVER['REQUEST_METHOD'];
    }
    
    /**
     * RequestがPostの場合にtrueを返す
     * @return bool
     */
    public function isPost() : bool {
        return $this->getMethod() === 'POST';
    }
    
    /**
     * RequestがGetの場合にtrueを返す
     * @return bool
     */
    public function isGet() : bool {
        return $this->getMethod() === 'GET';
    }
    
    
    public function post() : Post {
        return Post::getInstance();
    }
    
    
    /**
     * POSTのデータを返す。
     * @var callable|null $format フォーマット関数
     * @return array
     */
    public function getPostData($format = null) : array {
        $post = Post::getInstance();
        if( $format !== null ) {
            return $format($post->getAll());
        } else {
            return $post->getAll();
        }
    }
    
    
    protected function beforeFormat( array $data ) : array {
        return $data;
    }
    
    
    /**
     * Postで入力された値のバリデーションを行い、バリデーションを行った値を返す。
     * @param array $config バリデーションの設定情報
     * @param callable|null $beforeFormat バリデーション前にデータをフォーマットする関数
     * @param string|null $formClass 出力するフォームデータクラス
     * @param HookRegistry|null $hooks 動的バリデーション用の hook
     *        （validate_rules / validate.{field} / validate を発火させたい場合に渡す）
     * @return FormData
     * @throws ValidationException バリデーション例外
     * @throws ValidationRuleException バリデーションの設定が不正な場合の例外
     */
    public function validateForm( array $config,$beforeFormat = null, ?string $formClass = null, ?HookRegistry $hooks = null ) : FormData {
        $data = $this->getPostData($beforeFormat);
        $validation = new Validation($config);
        if ( $formClass !== null ) {
            $validation->setFormDataClass($formClass);
        }
        return $validation->validateFormData($data, $hooks);
    }
    
    
    /**
     * @param array $config
     * @return array
     * @throws ValidationException バリデーションに失敗した場合の例外
     * @throws ValidationRuleException バリデーションの設定が不正な場合の例外
     */
    public function validate( array $config ) : array {
        $data = $this->getPostData();
        $validation = new Validation($config);
        return $validation->validated($data);
    }
}
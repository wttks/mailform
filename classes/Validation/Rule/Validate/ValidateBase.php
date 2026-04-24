<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Util\ObjectUtil;
use AIJOH\Validation\Validator\Validator;

abstract class ValidateBase {
    
    /**
     * @param bool $isRequiredCheck 値が未入力の時にチェックを行うかどうかのフラグ
     */
    public function __construct( private readonly bool $isRequiredCheck = false ) {
    }
    
    
    /**
     * エラーメッセージ内で使用する変数名を返す。
     * インデックス順に args の各要素に対応する変数名を返す。
     * サブクラスでオーバーライドして名前付き変数を定義する。
     * @return array
     */
    public function getArgNames() : array {
        return [];
    }


    /**
     * チェック用の配列をエラーメッセージで使用するための文字列の配列に変換する。
     * getArgNames() で定義した名前をキーとして使用し、未定義分は数値インデックスをキーとする。
     * @param ?array $args
     * @return array
     */
    public function formatMessageArgs( ?array $args ) : array {
        if ( empty($args) ) {
            return [];
        }
        $names = $this->getArgNames();
        $results = [];
        foreach ( $args as $i => $value ) {
            $key = $names[ $i ] ?? (string)( $i + 1 );
            $results[ $key ] = $this->formatMessageValue($value);
        }
        return $results;
    }


    /**
     * チェックで使用するデータをエラーメッセージで表示するための文字列に変換する。
     * @param mixed $value
     * @return string
     */
    protected function formatMessageValue( mixed $value ) : string {
        return match ( true ) {
            is_string($value) => $value,
            is_numeric($value) => (string)$value,
            is_bool($value) => $value ? "true" : "false",
            is_array($value) => implode(",", $value),
            $value instanceof \DateTime || $value instanceof \DateTimeImmutable => $value->format("Y-m-d H:i:s"),
            is_object($value) => $value->toString(),
            default => "",
        };
    }
    
    /**
     * デフォルトのエラーメッセージを取得する。
     * @return string
     */
    public abstract function getErrorMessage() : string;
    
    
    /**
     * データのバリデーションを行う。
     * @param mixed $value バリデーション対象のデータ
     * @param string $name 現在バリデーションを実施しているデータのキー値
     * @param array $data バリデーション対象の全体のデータ
     * @param Validator|null $validator バリデーション全体のパラメータ
     * @return bool バリデーションに成功したら true 失敗したらfalseを返す。
     */
    public function validate( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if ( ! $this->isRequiredCheck ) {
            if ( $value instanceof UploadFile && ! $value->exists() ){
                return true;
            }

            if( ObjectUtil::isEmpty($value) ){
                return true;
            }
        }
        return $this->check($value, $args, $name, $data, $validator);
        
    }
    
    /**
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     */
    protected abstract function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool;
}
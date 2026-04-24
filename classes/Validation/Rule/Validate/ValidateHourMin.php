<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Validation\Validator\Validator;

/**
 * 時間のバリデーションを行う。
 */
class ValidateHourMin extends ValidateBase {
    
    public function __construct() {
        parent::__construct(false);
    }
    
    public function getErrorMessage() : string {
        return ':titleはHH:MMの形式で入力してください。';
    }
    
    /**
     * 日付のチェックを行う。
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     */
    protected function check(mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        return $this->checkHourTime($value);
    }
    
    
    /**
     * 指定した引数が時刻の形式(0:00～23:59)かどうかをチェックする。
     *
     * @param $value
     * @return bool
     */
    private function checkHourTime($value) : bool {
        if( is_string($value) ){
            $pattern = '/\A([0-1]?[0-9]|2[0-3]):[0-5]?[0-9]\z/';
            return preg_match($pattern, $value);
        }
        if( is_array($value) ){
            foreach( $value as $v ){
                if( !$this->checkHourTime($v) ){
                    return false;
                }
            }
            return true;
        }
        return false;
    }
}
<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Util\DataSize;
use AIJOH\Util\DateUtil;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validator\Validator;


abstract class ValidateBaseSizeCompare extends ValidateBaseSize {

    
    /**
     * 引数のチェックを行う。
     * @param array|null $args
     * @throws ValidationRuleException
     */
    private function checkArgs( ?array $args = [] ) : void {
        if ( empty($args) ) {
            throw new ValidationRuleException("引数を取得してください。");
        }
        if ( count($args) !== 1 ) {
            throw new ValidationRuleException("引数は1つのみ設定可能です。");
        }
    }
    
    /**
     * 数値が引数で指定した数値以上かチェックを行う。
     * @param float|int $value
     * @param array|null $args
     * @return int
     * @throws ValidationRuleException
     */
    protected function checkNumber( float|int $value, ?array $args = [] ) : int {
        $this->checkArgs($args);
        if ( ! is_numeric($value) ) {
            throw new ValidationRuleException("数値を指定してください。");
        }
        $threshold = $args[0];
        return $threshold <=> $value;
    }
    
    /**
     * 文字列が引数で指定した文字数以上かチェックを行う。
     * @param string $value 文字列
     * @param array|null $args
     * @return int
     * @throws ValidationRuleException
     */
    protected function checkString( string $value, ?array $args = [] ) : int {
        $this->checkArgs($args);
        $threshold = (int)$args[0];
        if ( $threshold < 0 ) {
            throw new ValidationRuleException("引数は0以上を指定してください。");
        }
        return $threshold <=> mb_strlen($value);
    }
    
    /**
     * ファイルのサイズが引数で指定したサイズ以上かチェックを行う。
     * @param UploadFile $value
     * @param array|null $args
     * @return int
     * @throws ValidationRuleException
     */
    protected function checkFile( UploadFile $value, ?array $args = [] ) : int {
        $this->checkArgs($args);
        $threshold = DataSize::toByte($args[0]);
        return $threshold <=> $value->getSize();
    }
    
    /**
     * 日付が引数で指定した日付以上かチェックを行う。(時間は対象外となる)
     * @param \DateTimeImmutable $value
     * @param array|null $args
     * @return int
     * @throws ValidationRuleException
     */
    protected function checkDate( \DateTimeImmutable $value, ?array $args = [] ) : int {
        $this->checkArgs($args);
        $threshold = DateUtil::toDateTimeImmutable($args[0]);
        return $threshold->format('Ymd') <=> $value->format('Ymd');
    }
    
    /**
     * 日付が引数で指定した日付以上かチェックを行う。(時間は対象外となる)
     * @param \DateTimeImmutable $value
     * @param array|null $args
     * @return int
     * @throws ValidationRuleException
     */
    protected function checkDatetime( \DateTimeImmutable $value, ?array $args = [] ) : int {
        $this->checkArgs($args);
        $threshold = DateUtil::toDateTimeImmutable($args[0]);
        return $threshold <=> $value;
    }
    
    /**
     * 配列の要素数が引数で指定した数以上かチェックを行う。
     * @param array $value
     * @param array|null $args
     * @return int
     * @throws ValidationRuleException
     */
    protected function checkArray( array $value, ?array $args = [] ) : int {
        $this->checkArgs($args);
        $min = (int)$args[0];
        if ( $min < 0 ) {
            throw new ValidationRuleException("引数は0以上を指定してください。");
        }
        return $min <=> count($value);
    }
    
    
    protected function checkMailAddress(array $value,?array $args = [] ) : int {
        $this->checkArgs($args);
        $min = (int)$args[0];
        if ( $min < 0 ) {
            throw new ValidationRuleException("引数は0以上を指定してください。");
        }
        return $min <=> count($value);
    }
}
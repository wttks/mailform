<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Util\DataSize;
use AIJOH\Util\DateUtil;
use AIJOH\Validation\Exception\ValidationRuleException;

class ValidateBetween  extends ValidateBaseSize {
    
    
    public function getArgNames() : array {
        return ['min', 'max'];
    }

    #[\Override] protected function getErrorMessageNumber() : string {
        return ":titleは:min以上:max以下の数値を指定してください。";
    }

    #[\Override] protected function getErrorMessageString() : string {
        return ":titleは:min文字以上:max文字以下で入力してください。";
    }

    #[\Override] protected function getErrorMessageDate() : string {
        return ":titleは:minより後:maxより前の日付を指定してください。";
    }

    #[\Override] protected function getErrorMessageDatetime() : string {
        return ":titleは:minより後:maxより前の日時を指定してください。";
    }

    #[\Override] protected function getErrorMessageArray() : string {
        return ":titleは:min個以上:max個以下で入力してください。";
    }

    #[\Override] protected function getErrorMessageFile() : string {
        return ":titleは:min以上:max以下のサイズのファイルを指定してください。";
    }
    
    /**
     * @param $result
     * @return bool
     */
    #[\Override] protected function judgeResults( $result ) : bool {
        return $result;
    }
    
    /**
     * 引数のチェックを行う。
     * @param array|null $args
     * @throws ValidationRuleException
     */
    private function checkArgs( ?array $args = [] ) : void {
        if ( empty($args) ) {
            throw new ValidationRuleException("引数を取得してください。");
        }
        if ( count($args) !== 2 ) {
            throw new ValidationRuleException("引数は2つのみ設定可能です。");
        }
    }
    
    /**
     * 数値が引数で指定した数値以上かチェックを行う。
     * @param float|int $value
     * @param array|null $args
     * @return int
     * @throws ValidationRuleException
     */
    protected function checkNumber( float|int $value, ?array $args = [] ) : bool {
        $this->checkArgs($args);
        if ( ! is_numeric($value) ) {
            throw new ValidationRuleException("数値を指定してください。");
        }
        $min = $args[0];
        $max = $args[1];
        return $min <= $value && $value <= $max;
    }
    
    /**
     * 文字列が引数で指定した文字数以上かチェックを行う。
     * @param string $value 文字列
     * @param array|null $args
     * @return int
     * @throws ValidationRuleException
     */
    protected function checkString( string $value, ?array $args = [] ) : bool {
        $this->checkArgs($args);
        $min = (int)$args[0];
        $max = (int)$args[1];
        $len = mb_strlen($value);
        return $min <= $len && $len <= $max;
    }
    
    /**
     * ファイルのサイズが引数で指定したサイズ以上かチェックを行う。
     * @param UploadFile $value
     * @param array|null $args
     * @return int
     * @throws ValidationRuleException
     */
    protected function checkFile( UploadFile $value, ?array $args = [] ) : bool {
        $this->checkArgs($args);
        $min = DataSize::toByte($args[0]);
        $max = DataSize::toByte($args[1]);
        $size = $value->getSize();
        return $min <= $size && $size <= $max;
    }
    
    /**
     * 日付が引数で指定した範囲内かチェックを行う。(時間は対象外となる)
     * @param \DateTimeImmutable $value
     * @param array|null $args
     * @return bool
     * @throws ValidationRuleException
     */
    protected function checkDate( \DateTimeImmutable $value, ?array $args = [] ) : bool {
        $this->checkArgs($args);
        $min = DateUtil::toDateTimeImmutable($args[0]);
        $max = DateUtil::toDateTimeImmutable($args[1]);
        $val = $value->format('Ymd');
        return $min->format('Ymd') <= $val && $val <= $max->format('Ymd');
    }

    /**
     * 日時が引数で指定した範囲内かチェックを行う。
     * @param \DateTimeImmutable $value
     * @param array|null $args
     * @return bool
     * @throws ValidationRuleException
     */
    protected function checkDatetime( \DateTimeImmutable $value, ?array $args = [] ) : bool {
        $this->checkArgs($args);
        $min = DateUtil::toDateTimeImmutable($args[0]);
        $max = DateUtil::toDateTimeImmutable($args[1]);
        return $min <= $value && $value <= $max;
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
        $max = (int)$args[1];
        if ( $max < 0 ) {
            throw new ValidationRuleException("引数は0以上を指定してください。");
        }
        $count = count($value);
        return $min <= $count && $count <= $max;
    }
}
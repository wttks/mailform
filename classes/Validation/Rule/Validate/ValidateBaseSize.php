<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Http\UploadFile;
use AIJOH\Output\Mailer\MailAddressParser;
use AIJOH\Output\Mailer\SendMailException;
use AIJOH\Util\StrUtil;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validator\Validator;

/**
 * @template T of mixed
 */
abstract class ValidateBaseSize extends ValidateBase {
    
    protected const TypeNumber = "number";
    
    protected const TypeString = "string";
    
    protected const TypeDatetime = "datetime";
    
    protected const TypeDate = "date";
    
    protected const TypeArray = "array";
    
    protected const TypeFile = "file";
    
    
    protected const TypeMailAddress = "mail_address";
    
    protected const TypeOther = "other";
    
    
    /**
     * 最後にチェックしたデータのタイプを保持する。
     * @var string
     */
    protected $type = "";
    
    /**
     * エラーメッセージを取得する。
     * ※ このメソッドは validate() の呼び出し後にのみ使用可能。
     * @return string
     * @throws ValidationRuleException validate() を呼ばずに呼び出した場合
     */
    public function getErrorMessage() : string {
        if ( $this->type === "" ) {
            throw new ValidationRuleException("getErrorMessage() は validate() の呼び出し後にのみ使用できます。");
        }
        $method = "getErrorMessage" . ucfirst($this->type);
        if ( ! method_exists($this, $method) ) {
            throw new ValidationRuleException("{$method}メソッドが存在しません。");
        }
        return $this->$method();
    }
    
    /**
     * エラーメッセージを取得する。
     * @return string
     */
    protected abstract function getErrorMessageNumber() : string;
    
    /**
     * 文字列のエラーメッセージを返す。
     * @return string
     */
    protected abstract function getErrorMessageString() : string;
    
    /**
     * 日付のエラーメッセージを返す。
     * @return string
     */
    protected abstract function getErrorMessageDate() : string;
    
    protected abstract function getErrorMessageDatetime() : string;
    
    /**
     * 配列チェック時のエラーメッセージを返す。
     * @return string
     */
    protected abstract function getErrorMessageArray() : string;
    
    /**
     * ファイルのチェックの際のエラーメッセージを返す。
     * @return string
     */
    protected abstract function getErrorMessageFile() : string;
    
    
    /**
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     * @throws ValidationRuleException ルールの記述が間違っている場合に発生する。
     */
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        $results = $this->checkByRule($value, $args, $name, $data, $validator);
        if ( ! is_null($results) ) {
            return $results;
        }
        
        return $this->checkByValueType($value, $args, $name, $data, $validator);
    }
    
    
    /**
     * 入力値をルールベースで型を判断して
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool|null
     * @throws ValidationRuleException  ルールの記述が間違っている場合に発生する。
     */
    protected function checkByRule( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : ?bool {
        if ( $validator === null ) {
            return null;
        }
        $myRule = $validator->getValidatorOne($name);
        if ( $myRule === null ) {
            return null;
        }
        
        // 数値の比較を行う。
        if ( $myRule->hasRule('int') || $myRule->hasRule('numeric') ) {
            return $this->checkType(self::TypeNumber, $value, $args);
        }
        
        // 文字列の比較を行う。
        if ( $myRule->hasRule('string') ) {
            return $this->checkType(self::TypeString, $value, $args);
        }
        
        // アップロードファイルの比較を行う。
        if ( $myRule->hasRule('file') ) {
            return $this->checkType(self::TypeFile, $value, $args);
        }
        
        // 日付の比較を行う。
        if ( $myRule->hasRule('date') || $myRule->hasRule('datetime') ) {
            try {
                $date = new \DateTimeImmutable($value);
            } catch ( \Exception $e ) {
                throw new ValidationRuleException("日付を指定してください。");
            }
            return $this->checkType($myRule->hasRule('date') ? self::TypeDate : self::TypeDatetime, $date, $args);
        }
        
        // メールアドレスの数の比較を行う。
        if ( $myRule->hasRule('mail_address') ) {
            try {
                $addressList = MailAddressParser::parse($value);
                return $this->checkType(self::TypeArray, $addressList, $args);
            }catch(SendMailException $se){
                // 既にメールアドレスのバリデーションは行っているので、ここには来ないはず。
                throw new ValidationRuleException("メールアドレスのバリデーションにバグがあります。");
            }
        }
        
        // 日付の比較を行う。
        if ( $myRule->hasRule('date_format') || $myRule->hasRule('datetime_format') ) {
            $config = $myRule->getRule("date_format") ?? $myRule->getRule("datetime_format");
            if ( $config === null ) {
                throw new ValidationRuleException("date_formatを指定した場合には、日付フォーマットを指定してください。");
            }
            
            $dateFormat = $config->getArgs();
            $date = self::createDateTimeImmutableFromFormat($dateFormat, $value);
            if ( $date === false ) {
                throw new ValidationRuleException("日付を指定してください。");
            }
            
            // 同じ項目のチェックにdate_formatがある場合は、 日付チェックの日時のフォーマットは date_format形式で記述出来る。
            $args = array_map(function( $value ) use ( $dateFormat ) {
                if ( is_string($value) ) {
                    $date = self::createDateTimeImmutableFromFormat($value, $dateFormat);
                    return $date !== false ? $date : $value;
                }
                return $value;
                
            }, $args);
            return $this->checkType($myRule->hasRule('date_format') ? self::TypeDate : self::TypeDatetime, $date, $args);
        }
        
        return $this->checkByOtherRule($value, $args, $name, $data, $validator);
    }
    
    
    private function createDateTimeImmutableFromFormat($value , array $dateFormatList) : \DateTimeImmutable|false {
        foreach ( $dateFormatList as $dateFormat ) {
            $date = \DateTimeImmutable::createFromFormat($dateFormat, $value);
            if ( $date !== false ) {
                return $date;
            }
        }
        return false;
    }
    
    
    /**
     * このメソッドをオーバーライドして、基本型以外のデータ型のチェックを行う。
     * このメソッドをオーバーライドした場合、checkByValueTypeは呼ばれない。
     * チェックを行わない場合は、nullを返す。
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool|null
     */
    protected function checkByOtherRule(mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null): ?bool {
        return null;
    }
    
    
    /**
     * データ型を元にチェックを行う。
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     * @throws ValidationRuleException
     */
    protected function checkByValueType( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        return match ( true ) {
            is_numeric($value) => $this->checkType(self::TypeNumber, $value, $args),
            is_string($value) => $this->checkType(self::TypeString, $value, $args),
            $value instanceof UploadFile => $this->checkType(self::TypeFile, $value, $args),
            $value instanceof \DateTimeImmutable => $this->checkType(self::TypeDate, $value, $args),
            $value instanceof \DateTime => $this->checkType(self::TypeDate, \DateTimeImmutable::createFromMutable($value), $args),
            is_array($value) => $this->checkType(self::TypeArray, $value, $args),
            default => $this->checkOther($value, $args, $name, $data, $validator),
        };
    }
    
    
    protected function checkOther( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        return false;
    }
    
    /**
     * 型を指定してチェックを行う。
     * @param string $type
     * @param mixed $value
     * @param array|null $args
     * @return bool
     * @throws ValidationRuleException
     */
    protected function checkType( string $type, mixed $value, ?array $args = [] ) : bool {
        $this->type = $type;
        $methodName = "check" . ucfirst(StrUtil::toCamelCase($type));
        if ( ! method_exists($this, $methodName) ) {
            throw new ValidationRuleException("{$methodName}メソッドが存在しません。");
        }
        $result = $this->$methodName($value, $args);
        return $this->judgeResults($result);
    }
    
    
    /**
     * @param T $result
     * @return bool
     */
    protected abstract function judgeResults( $result ) : bool;
}
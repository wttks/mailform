<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Util\ArrayUtil;
use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validator\Validator;

/**
 * 他フィールドの値と一致することを確認するルール。
 *
 * 使用例:
 *   'email_confirm' => [
 *       'rule' => 'required|email|same:email',
 *       'message' => ['same' => 'メールアドレスが一致しません。'],
 *   ],
 *
 * 引数:
 *   same:<他フィールド名>
 */
class ValidateSame extends ValidateBase {

    public function getArgNames() : array {
        return ['field'];
    }


    public function getErrorMessage() : string {
        return ':titleが:fieldと一致しません。';
    }


    /**
     * @param mixed $value
     * @param array|null $args
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     * @throws ValidationRuleException
     */
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        $otherKey = $args[0] ?? '';
        if ( $otherKey === '' ) {
            throw new ValidationRuleException("same の引数には比較対象のフィールド名を指定してください。");
        }
        $otherValue = ArrayUtil::get($data, $otherKey, null);
        // どちらも null/空文字なら一致扱い（個別の required で弾かれる想定）
        if ( ($value === null || $value === '') && ($otherValue === null || $otherValue === '') ) {
            return true;
        }
        return (string) $value === (string) $otherValue;
    }

}

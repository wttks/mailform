<?php

namespace AIJOH\Validation\Rule\Validate;

use AIJOH\Validation\Exception\ValidationRuleException;
use AIJOH\Validation\Validator\Validator;

/**
 * 値が許可リストに含まれることを確認するルール。
 *
 * ラジオボタン / チェックボックス / セレクトボックスのサーバ側検証用。
 *
 * 使用例:
 *   // ラジオ・セレクト（単一値）
 *   'gender' => [
 *       'title' => '性別',
 *       'rule'  => 'required|in_list:male,female,other',
 *   ],
 *
 *   // チェックボックス（配列値: 全要素が許可リストに含まれていること）
 *   'interests' => [
 *       'title' => '興味のある分野',
 *       'rule'  => 'array|in_list:web,mobile,ai,security',
 *   ],
 *
 * 引数:
 *   in_list:<値1>,<値2>,...
 *
 * 値が配列の場合は全要素を判定する（チェックボックス対応）。
 * 比較は文字列キャストで行う（フォーム値は文字列で来るため）。
 */
class ValidateInList extends ValidateBase {

    public function getArgNames() : array {
        // エラーメッセージで :allowed として全引数を表示
        return [];
    }


    public function getErrorMessage() : string {
        return ':titleは指定された値の中から選択してください。';
    }


    /**
     * 全引数をカンマ区切りで :allowed プレースホルダに展開する。
     */
    public function formatMessageArgs( ?array $args ) : array {
        return [
            'allowed' => implode(',', array_map('strval', $args ?? [])),
        ];
    }


    /**
     * @param mixed $value
     * @param array|null $args 許可値のリスト
     * @param string $name
     * @param array $data
     * @param Validator|null $validator
     * @return bool
     * @throws ValidationRuleException
     */
    protected function check( mixed $value, ?array $args = [], string $name = "", array $data = [], ?Validator $validator = null ) : bool {
        if ( empty($args) ) {
            throw new ValidationRuleException("in_list の引数には許可値のリストを指定してください。");
        }
        $allowed = array_map('strval', $args);

        // 配列値（チェックボックス）: 全要素が許可リストに含まれていること
        if ( is_array($value) ) {
            foreach ( $value as $item ) {
                if ( ! in_array((string) $item, $allowed, true) ) {
                    return false;
                }
            }
            return true;
        }

        return in_array((string) $value, $allowed, true);
    }
}

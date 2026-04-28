<?php

namespace AIJOH\Validation\Compose;

/**
 * 複合フィールド結合の抽象基底。
 * 複数の入力フィールドの値を1つの結合値にまとめる。
 *
 * 例: birthday_year/birthday_month/birthday_day → birthday (1990-01-01)
 */
abstract class ComposeBase {

    /**
     * 元フィールド名のリスト
     * @return string[]
     */
    abstract public function getSourceFields() : array;


    /**
     * 結合値を計算する。元フィールドのいずれかが空なら null を返し、
     * Validation 側で結合フィールドの値を更新しないようにする。
     * @param array $data
     * @return string|null
     */
    abstract public function apply( array $data ) : ?string;

}

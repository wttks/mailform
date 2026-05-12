<?php

namespace AIJOH\Config;

/**
 * 設定配列を深くマージするユーティリティ。
 *
 * array_merge_recursive と異なり、リスト（数値キー配列）は **置換**、
 * 連想配列（map）は再帰的にマージする。これは設定マージとして自然な挙動:
 *
 *   merge(
 *       ['ai_spam' => ['enabled' => false, 'fields' => ['name']]],
 *       ['ai_spam' => ['enabled' => true]],   // enabled だけ上書き
 *   )
 *   => ['ai_spam' => ['enabled' => true,  'fields' => ['name']]]
 *
 * 連想配列の判定は ArrayUtil::isHash() と同じ「全キーが連続する数値か」を見る。
 */
class ConfigMerger {

    /**
     * 複数の設定配列を順番にマージする（後勝ち）。
     *
     * @param array ...$sources マージする配列。後の引数が優先。
     * @return array
     */
    public static function merge( array ...$sources ) : array {
        $result = [];
        foreach ( $sources as $source ) {
            $result = self::mergePair($result, $source);
        }
        return $result;
    }


    /**
     * 2 つの配列を1段マージする。
     */
    private static function mergePair( array $base, array $override ) : array {
        // どちらかがリスト（数値キーのみ）なら override で完全置換
        if ( self::isList($base) || self::isList($override) ) {
            return $override;
        }

        $result = $base;
        foreach ( $override as $key => $value ) {
            if ( is_array($value) && isset($result[$key]) && is_array($result[$key]) ) {
                $result[$key] = self::mergePair($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }


    /**
     * 連続した整数キーから始まる「リスト」か判定する。
     * 空配列は連想配列扱い（マージ可能）。
     */
    private static function isList( array $arr ) : bool {
        if ( $arr === [] ) {
            return false;
        }
        return array_is_list($arr);
    }

}

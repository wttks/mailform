<?php

namespace AIJOH\Util;

class StrUtil {
    
    /**
     * 文字列が指定した文字列で始まるかどうかをチェックする。
     *
     * PHP 8 の str_starts_with と同じ仕様 ( 空文字列 needle は true ) を維持しつつ、
     * PHP 7.4 でも安全に動くポータブル実装 ( strncmp ベース )。旧実装は
     * version_compare 分岐内で strpos($str, '') を呼んでおり、PHP 7.4 で
     * Empty needle warning を出すデッドコードがあった ( mailform74 同期で表面化 )。
     *
     * @param string $str チェックする文字列
     * @param string $search 検索する文字列
     * @return bool 指定した文字列で始まる場合はtrueを返す。
     */
    public static function startsWith( string $str, string $search ) : bool {
        if ( $search === '' ) {
            return true;
        }
        return strncmp($str, $search, strlen($search)) === 0;
    }


    /**
     * 文字列が指定した文字列で終わるかどうかをチェックする。
     *
     * PHP 8 の str_ends_with と同じ仕様 ( 空文字列 needle は true ) を維持しつつ、
     * PHP 7.4 でも安全に動くポータブル実装 ( substr_compare ベース )。
     *
     * @param string $str チェックする文字列
     * @param string $search 検索する文字列
     * @return bool 指定した文字列で終わる場合はtrueを返す。
     */
    public static function endsWith( string $str, string $search ) : bool {
        if ( $search === '' ) {
            return true;
        }
        $searchLen = strlen($search);
        if ( $searchLen > strlen($str) ) {
            return false;
        }
        return substr_compare($str, $search, -$searchLen) === 0;
    }


    /**
     * 文字列が指定した文字列を含むかどうかをチェックする。
     *
     * PHP 8 の str_contains と同じ仕様 ( 空文字列 needle は true )。
     *
     * @param string $str
     * @param string $search
     * @return bool
     */
    public static function contains( string $str, string $search ) : bool {
        if ( $search === '' ) {
            return true;
        }
        return strpos($str, $search) !== false;
    }
    
    
    /**
     * 文字列が空の文字列化どうかをチェックする。
     * @param mixed $value チェックする値
     * @return bool
     */
    public static function isEmpty( mixed $value ) : bool {
        if ( is_null($value) ) {
            return true;
        }
        if ( is_string($value) ) {
            return strlen($value) === 0;
        }
        if ( is_array($value) ) {
            return count($value) === 0;
        }
        if ( is_object($value) ) {
            return count((array)$value) === 0;
        }
        return false;
    }
    
    
    /**
     * テキストをカタカナに変換する。
     * @param string|string[] $str
     * @return string|string[]
     */
    public static function toKatakana( string|array $str ) : string|array {
        if ( is_string($str) ) {
            return mb_convert_kana($str, "aKVCs", 'UTF-8');
        }
        
        return array_map(fn( $value ) => self::toKatakana($value), $str);
    }
    
    
    /**
     * テキストをカタカナに変換する。
     * @param string|string[] $str
     * @return string|string[]
     */
    public static function toHiragana( string|array $str ) : string|array {
        if ( is_string($str) ) {
            return mb_convert_kana($str, "asHcV", 'UTF-8');
        }
        
        return array_map(fn( $value ) => self::toHiragana($value), $str);
    }
    
    
    /**
     * テキストの正規化を行う。
     * @param string|string[] $str
     * @return string|string[]
     */
    public static function toNormalize( string|array $str ) : string|array {
        if ( is_string($str) ) {
            return mb_convert_kana($str, "aKVs", 'UTF-8');
        }
        return array_map(fn( $value ) => self::toNormalize($value), $str);
    }
    
    
    /**
     * テキストの正規化を行い、テキストの前後の空白を削除する。
     * @param string|array $str 文字列
     * @return string|array
     */
    public static function toNormalizeTrim( string|array $str ) : string|array {
        if ( is_string($str) ) {
            return trim(self::toNormalize($str));
        }
        return array_map(fn( $value ) => self::toNormalizeTrim($value), $str);
    }
    
    
    /**
     * テキストの前後の空白を削除する。
     * @param string|array $str
     * @return string|array
     */
    public static function trim( string|array $str ) : string|array {
        if ( is_string($str) ) {
            return preg_replace('/\A[\p{Cc}\p{Cf}\p{Z}]++|[\p{Cc}\p{Cf}\p{Z}]++\z/u', '', $str);
        }
        return array_map(fn( $value ) => self::trim($value), $str);
    }


    /**
     * Unicode Format カテゴリ（\p{Cf}）の文字を**全部**削除する。
     * ZWSP (U+200B) / ZWNJ (U+200C) / ZWJ (U+200D) / RTL Override (U+202E) /
     * BOM (U+FEFF) などの「見えない / 表示を変える」制御文字が対象。
     *
     * 攻撃シナリオ:
     * - 「あ\u{200B}\u{200B}\u{200B}買って」のように日本語に潜伏
     * - in_japanese 判定を通過しつつ実際の表示は別文字
     *
     * normalize_trim と組み合わせて使うと前後 + 中間の Cf 文字を全削除できる。
     *
     * @param string|array $str
     * @return string|array
     */
    public static function stripFormatChars( string|array $str ) : string|array {
        if ( is_string($str) ) {
            return preg_replace('/\p{Cf}/u', '', $str);
        }
        return array_map(fn( $value ) => self::stripFormatChars($value), $str);
    }
    
    
    
    /**
     * スネークケースからキャメルケースに変換する。
     * 先頭の文字については大文字に変換しない
     * 例: snake_case -> snakeCase
     * @param string|string[] $str スネークケースの文字列
     * @return string|array キャメルケールの文字列
     */
    public static function toCamelCase( string|array $str ) : string|array {
        if ( is_string($str) ) {
            $list = mb_split('[_-]', $str);
            return $list[0] . implode('', array_map('ucfirst', array_slice($list, 1)));
        }
        return array_map(fn( $value ) => self::toCamelCase($value), $str);
    }
    
    
    /**
     * キャメルケースからスネークケースに変換する
     * @param string|string[] $str
     * @return string
     */
    public static function toSnakeCase( string|array $str ) : string|array {
        if ( is_string($str) ) {
            return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $str));
        }
        return array_map(fn( $value ) => self::toSnakeCase($value), $str);
    }
    
    
    /**
     * 文字列が全てカタカナかどうかを判別する。
     * @param string $str
     * @return bool
     */
    public static function isKatakana( string|array $str ) : bool {
        if ( is_string($str) ) {
            return preg_match('/\A\p{Katakana}+\z/u', $str);
        }
        return ArrayUtil::isAllTrue($str, fn( $value ) => self::isKatakana($value));
    }
    
    
    /**
     * 文字列が全てひらがなかどうかを判別する。
     * @param string|array $str
     * @return bool
     */
    public static function isHiragana( string|array $str ) : bool {
        if ( is_string($str) ) {
            return preg_match('/\A\p{Hiragana}+\z/u', $str);
        }
        return ArrayUtil::isAllTrue($str, fn( $value ) => self::isHiragana($value));
    }
    
    
    /**
     * フリガナのチェックを行う（ひらがな or カタカナ）。
     *
     * 単純な「全部ひらがな」「全部カタカナ」とは別概念で、
     * 「フリガナとして妥当か（指定の文字種のみで構成、空白で複数ブロック許容）」を見る。
     *
     * @param string|array $str
     * @param string $type 'hiragana' または 'katakana'（デフォルト）
     * @return bool
     */
    public static function isFurigana( string|array $str, string $type = 'katakana' ) : bool {
        if ( is_string($str) ) {
            return self::isFuriganaString($str, $type);
        }
        return ArrayUtil::isAllTrue($str, fn( $value ) => self::isFurigana($value, $type));
    }


    /**
     * 文字列がフリガナとして妥当かを判定する。
     * 指定の文字種が「空白で区切られて複数ブロック」並んでも許容する
     * （氏 名、ヴァン デル ベルク 等のミドルネーム含む複合姓に対応）。
     */
    private static function isFuriganaString( string $str, string $type ) : bool {
        $pattern = $type === 'hiragana' ? '\p{Hiragana}' : '\p{Katakana}';
        $regExp = '/\A' . $pattern . '+(?:\p{Zs}+' . $pattern . '+)*\z/u';
        return (bool)preg_match($regExp, $str);
    }
    
    
    /**
     * 文字列中に日本語の文字が含まれているかチェックを行う。
     * @param string $str
     * @return bool
     */
    public static function inJapanese( string $str ) : bool {
        return (bool)preg_match('/[\p{Hiragana}\p{Katakana}\p{Han}]/u', $str);
    }
    
    
    /**
     * 文字列がEmailアドレスかチェックを行う。
     * @param string|array $email
     * @return bool
     */
    public static function isEmail( string|array $email ) : bool {
        if ( is_string($email) ) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        }
        return ArrayUtil::isAllTrue($email, fn( $value ) => self::isEmail($value));
    }
    
    
    /**
     * 指定した文字列が電話番号かチェックを行う。
     * @param string $value
     * @return bool
     */
    public static function isTelephone( string $value ) : bool {
        if ( is_string($value) ) {
            $telephone = str_replace('-', '', $value);
            if ( mb_substr($value, 0, 1) === '+' ) {
                // 国際電話番号対応
                return preg_match('/\A\+[1-9]\d{6,14}\z/', $telephone);
            }
            // 国内電話番号 10桁～11桁 念のため 12桁まで対応
            return preg_match('/\A0\d{9,11}\z/', $telephone);
        }
        return ArrayUtil::isAllTrue($value, fn( $value ) => self::isTelephone($value));
    }
    
    
}
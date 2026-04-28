<?php

namespace AIJOH\Util;

use InvalidArgumentException;
use DateTimeImmutable;

class DateUtil {
    
    /**
     * 曜日の一覧(0:日曜日～6:土曜日)
     * @var string[]
     */
    public static $weekDayList = [
        '日', '月', '火', '水', '木', '金', '土',
    ];
    
    
    public static $weeDayEnglishListShort = [
        'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat',
    ];
    
    
    public static $weeDayEnglishList = [
        'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
    ];
    

    
    /**
     * 文字列が曜日の一覧に含まれるかチェックする。
     * @param $val
     * @return bool
     */
    public static function isWeekDay( $val ) : bool {
        if ( is_numeric($val) ) {
            return $val >= 0 && $val <= 6;
        }
        $val = strtolower($val);
        if( str_ends_with($val,'曜日') ){
            $val = str_replace('曜日', '', $val);
        }
        return in_array($val, self::$weekDayList, true) ||
            in_array($val, self::$weeDayEnglishListShort, true) ||
            in_array($val, self::$weeDayEnglishList, true);
    }
    
    
    /**
     * 曜日に対応する数値を取得する(0:日曜日～6:土曜日)
     * @param $val
     * @return int|false
     */
    public static function getWeekDayNumber( $val ) : int|false {
        if ( is_numeric($val) ) {
            if ( $val >= 0 && $val <= 6 ) {
                return (int)$val;
            }
            return false;
        }
        $val = strtolower($val);
        if( str_ends_with($val,'曜日') ){
            $val = str_replace('曜日', '', $val);
        }
        $index = array_search($val, self::$weekDayList, true);
        if ( $index !== false ) {
            return $index;
        }
        $index = array_search($val, self::$weeDayEnglishListShort, true);
        if ( $index !== false ) {
            return $index;
        }
        $index = array_search($val, self::$weeDayEnglishList, true);
        if ( $index !== false ) {
            return $index;
        }
        return false;
    }
    
    
    /**
     * 日付記述方法をチェックして、合致するフォーマットがあれば対応するDateTimeImmutableのクラスを返す。
     * @param array $formatList 日付フォーマットの配列
     * @param string $date
     * @return \DateTimeImmutable|null
     */
    public static function formatDate( array $formatList, string $date ) : ?\DateTimeImmutable {
        foreach ( $formatList as $format ) {
            $dateTime = \DateTimeImmutable::createFromFormat($format, $date);
            if ( $dateTime === false ) {
                continue;
            }
            $errors = \DateTimeImmutable::getLastErrors();
            $warningCount = ( $errors['warning_count'] ?? 0 ) + ( $errors['error_count'] ?? 0 );
            if ( $warningCount > 0 ) {
                continue;
            }
            return $dateTime;
        }
        return null;
    }
    
    
    public static function getWeekDayJapan() : array {
        return self::$weekDayList;
    }
    
    public static function getShortWeekDayEnglishList() : array {
        return self::$weeDayEnglishListShort;
    }
    
    public static function getWeekDayEnglishList() : array {
        return self::$weeDayEnglishList;
    }
    
    
    public static function getMonthOptionList() : array {
        return range(1, 12);
    }
    
    
    public static function getDayOptionList() : array {
        return range(1, 31);
    }
    
    
    public static function getYearOptionList( int $minAge, int $maxAge ) : array {
        $nowYear = date('Y');
        $min = $nowYear - $maxAge;
        $max = $nowYear - $minAge;
        return range($max, $min);
    }
    
    /**
     * 日付の範囲の指定が正しいかチェックを行う。
     * 日付の範囲は下記のいずれかでの記述となる。
     * 日付の指定方法は下記のいずれかでの記述となる。
     * 2024/02/01
     * 2024-02-01
     * 20240201
     *
     * 範囲の指定は下記のいずれかの文字が指定可能
     * ～〜-
     *
     * 上記の組み合わせで日付の範囲を指定する。
     * 2024/02/01-2024/02/10
     * 2024/02/01～2024/02/10
     * 2024/02/01〜2024/02/10
     * ※開始日又は終了日が指定されていない場合は今日の日付を開始日又は終了日とする。
     * ※範囲の文字が記載されていない場合は、その日だけの配列を返す。
     *
     * @param string $text
     * @return bool
     */
    public static function checkDateRange( string $text ) : bool {
        return self::parseDateRange($text) !== false;
    }
    
    
    /**
     * 範囲の最初と最後の時間を取得する。
     * @param string $text
     * @return array|false
     */
    public static function parseDateRange( string $text ) {
        $dateFormatList = [ 'Ymd', 'Y/m/d', 'Y-m-d' ];
        $rangeSign = "[～〜-]";
        $dateRegex = '(\d{4}[\/-]?\d{1,2}[\/-]?\d{1,2})';
        
        if ( preg_match("/\A$dateRegex\z/u", $text, $matches) ) {
            $date = self::formatDate($dateFormatList, $matches[1]);
            if ( $date !== null ) {
                return [ $date, $date ];
            }
            return false;
        }
        
        if ( preg_match("/\A{$rangeSign}{$dateRegex}\z/u", $text, $matches) ) {
            $end = self::formatDate($dateFormatList, $matches[1]);
            if ( $end !== null ) {
                return [ new \DateTimeImmutable(), $end ];
            }
            return false;
        }
        
        if ( preg_match("/\A{$dateRegex}{$rangeSign}\z/u", $text, $matches) ) {
            $start = self::formatDate($dateFormatList, $matches[1]);
            if ( $start !== null ) {
                return [ $start, new \DateTimeImmutable() ];
            }
            return false;
        }
        
        if ( preg_match("/\A{$dateRegex}{$rangeSign}{$dateRegex}\z/u", $text, $matches) ) {
            $start = self::formatDate($dateFormatList, $matches[1]);
            $end = self::formatDate($dateFormatList, $matches[2]);
            if ( $start !== null && $end !== null ) {
                return [ $start, $end ];
            }
            return false;
        }
        return false;
    }
    
    
    /**
     * 文字列のを元にDateTimeImmutableクラスの配列に変換する。
     * 2019/10/10-2019/10/20 の場合2019/10/10～2019/10/20日までの配列を返す。
     *
     * @param string $text
     * @return array|false
     */
    public static function getDateRange( string $text ) {
        $results = self::parseDateRange($text);
        if ( $results === false ) {
            return false;
        }
        return self::createDateList($results[0], $results[1]);
    }
    
    /**
     * 指定した日付の範囲の日付の配列を生成する。
     * @param DateTimeImmutable $start
     * @param DateTimeImmutable $end
     * @return array
     */
    public static function createDateList( \DateTimeImmutable $start, \DateTimeImmutable $end ) : array {
        $dateList = [];
        for ( $date = $start ; $date <= $end ; $date = $date->modify('+1 day') ) {
            $dateList[] = $date;
        }
        return $dateList;
    }
    
    /**
     * 文字列等の日付をDateTimeImmutableクラスに変換する。
     * @param string|\DateTime|\DateTimeImmutable $date
     * @return \DateTimeImmutable
     * @throws \InvalidArgumentException 日付の変換に失敗した場合
     */
    public static function toDateTimeImmutable( $date ) : \DateTimeImmutable {
        if ( $date instanceof \DateTimeImmutable ) {
            return $date;
        }
        if ( $date instanceof \DateTime ) {
            return \DateTimeImmutable::createFromMutable($date);
        }
        
        if ( is_string($date) ) {
            try {
                return new \DateTimeImmutable($date);
            } catch ( \Exception $e ) {
                throw new InvalidArgumentException('日付の変換に失敗しました。');
            }
        }
        
        if ( is_int($date) ) {
            return new \DateTimeImmutable('@' . $date);
        }
        
        
        throw new InvalidArgumentException('日付の変換に失敗しました。');
    }
    
}
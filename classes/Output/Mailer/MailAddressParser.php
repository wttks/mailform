<?php

namespace AIJOH\Output\Mailer;

use AIJOH\Util\ArrayUtil;
use AIJOH\Util\StrUtil;

/**
 * メールアドレスが記述された文字列や配列を解析する。
 */
class MailAddressParser {
    
    /**
     * 記述された内容を元にメールアドレスの一覧を生成する。
     * @param mixed $address
     * @return array|MailAddress[]
     * @throws SendMailException
     */
    public static function parse( mixed $address ) : array {
        if ( is_string($address) ) {
            $address = new MailAddress($address);
            return [ $address ];
        }
        if ( is_array($address) ) {
            return self::parseArray($address);
        }
        if ( $address instanceof MailAddress ) {
            return [ $address ];
        }
        throw new SendMailException("メールアドレスが不正です。不正なメールアドレス:" . $address);
    }
    
    
    /**
     * 配列のデータからMailAddressのリストを作成する。
     * 記述方法は下記の十通り
     * [メールアドレス,名前,メールアドレス,名前,メールアドレス,名前]
     * [メールアドレス => 名前,メールアドレス => 名前,メールアドレス => 名前]
     * [[メールアドレス,名前],[メールアドレス,名前],[メールアドレス,名前]]
     *
     *  ※配列の場合はメールアドレス、名前の順番で記述する。(逆はエラーとなる)
     * @param array $addressList
     * @return array|MailAddress[]
     * @throws SendMailException
     */
    private static function parseArray( array $addressList ) : array {
        // 連想配列の場合
        if ( ArrayUtil::isHash($addressList) ) {
            return self::parseHash($addressList);
        }
        
        $results = [];
        // 配列の場合
        for ( $i = 0, $count = count($addressList) ; $i < $count ; ++$i ) {
            $address = $addressList[ $i ];
            
            if ( $address instanceof MailAddress ) {
                $results[] = $address;
                continue;
            }
            
            // 配列の場合はネスト
            if ( is_array($address) ) {
                array_push($results, ...self::parseArray($address));
                continue;
            }
            if ( ! is_string($address) ) {
                throw new SendMailException("メールアドレスが不正です。不正なメールアドレス:" . $address);
            }
            
            // 文字列の場合
            $next = $addressList[ $i + 1 ] ?? null;
            
            
            
            if ( ! is_string($next) ) {
                $results[] = new MailAddress($address);
                continue;
            }
            
            // メールアドレス、名前の場合(名前がメールアドレスと同じ場合含む)
            if ( $address === $next || ! StrUtil::isEmail($next) ) {
                $results[] = new MailAddress($address, $next);
                ++$i;
                continue;
            }
            
            
            // メールアドレスのみの場合(次の要素が存在しないか、異なるメールアドレスの場合)
            $results[] = new MailAddress($address);
        }
        
        return $results;
    }
    
    /**
     * 連想配列のデータからMailAddressのリストを作成する。
     * 記述方法は下記の通り
     * [ 'メールアドレス' => '名前' ]
     * [ 'キー名' => 'メールアドレス' ]
     * [ 'キー名' => [ 'メールアドレス' , '名前' ]
     *
     * @param array $addressList アドレスの一覧
     * @return array|MailAddress[] メールアドレスの一覧
     * @throws SendMailException メールアドレスの記述が不正な場合
     */
    private static function parseHash( array $addressList ) : array {
        $results = [];
        
        
        $pass = false;
        foreach ( $addressList as $key => $address ) {
            if ( $pass ) {
                $pass = false;
                continue;
            }
            
            
            if ( $address instanceof MailAddress ) {
                $results[] = $address;
                continue;
            }
            
            // キーが文字列の場合
            if ( is_string($key) ) {
                if ( StrUtil::isEmail($key) ) {
                    $results[] = new MailAddress($key, $address);
                    continue;
                }
                if ( StrUtil::isEmail($address) ) {
                    $results[] = new MailAddress($address, $key);
                    continue;
                }
                throw new SendMailException("メールアドレスが不正です。不正なメールアドレス:" . $key . " " . $address);
            }
            
            if ( is_string($address) ){
                $next = is_int($key) ? ($addressList[ $key + 1 ] ?? null) : null;
                [ $address, $pass ] = self::buildMailAddress($address, $next);
                $results[] = $address;
                continue;
            }
            
            if ( is_array($address) ) {
                array_push($results, ...self::parseArray($address));
                continue;
            }
            
            throw new SendMailException("メールアドレスの形式が不明です。不明はメールアドレス:" . $address);
            
        }
        return $results;
    }
    
    /**
     * @param string $address
     * @param mixed $next
     * @return array| [MailAddress, bool] メールアドレスと次の値をパスするかどうかのboolean値を返す。
     * @throws SendMailException
     */
    private static function buildMailAddress( string $address, mixed $next ) : array {
        if ( ! is_string($next) ) {
            return [ new MailAddress($address), false ];
        }
        
        if ( $address === $next || ! StrUtil::isEmail($next) ) {
            return [ new MailAddress($address, $next), true ];
        } else {
            return [ new MailAddress($address), false ];
        }
        
    }
}
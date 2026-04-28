<?php

namespace AIJOH\AISpam;

use AIJOH\Http\Response;
use AIJOH\Http\Session;

/**
 * AI スパム判定でブロックされたセッションのフラグ管理。
 *
 * 一度スパム判定されたセッションは Cookie を捨てない限り
 * 全エンドポイントで即拒否される。
 */
class SpamSession {

    private const KEY = '_aispam_blocked';


    /**
     * セッションを spam マークする。
     */
    public static function block( string $reason ) : void {
        Session::getInstance()->set(self::KEY, [
            'reason' => $reason,
            'at'     => time(),
        ]);
    }


    /**
     * セッションが spam マークされているかを返す。
     */
    public static function isBlocked() : bool {
        return Session::getInstance()->get(self::KEY) !== null;
    }


    /**
     * spam マークされていれば即拒否レスポンスを返して exit する。
     */
    public static function abortIfBlocked( string $message ) : void {
        if ( self::isBlocked() ) {
            Response::jsonResults(false, $message);
        }
    }


    /**
     * spam マークを解除する（管理操作・テスト用）。
     */
    public static function clear() : void {
        Session::getInstance()->remove(self::KEY);
    }

}

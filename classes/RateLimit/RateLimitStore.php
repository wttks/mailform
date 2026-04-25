<?php

namespace AIJOH\RateLimit;

/**
 * レート制限カウンタの永続化を抽象化する。
 *
 * 実装は時間窓内のタイムスタンプ列を保持し、
 * - countWithin() で「直近 $windowSec 秒以内の件数」を返す
 * - record() で現在時刻を1件追記する
 * を提供すればよい。
 */
abstract class RateLimitStore {

    /**
     * 直近 $windowSec 秒以内に $key で記録された件数を返す。
     */
    abstract public function countWithin( string $key, int $windowSec ) : int;


    /**
     * 現在時刻を $key のカウンタに追記する。
     */
    abstract public function record( string $key ) : void;


    /**
     * 現在時刻（unix epoch、秒）を返す。テスト時に上書きできるよう abstract に分離。
     */
    public function now() : int {
        return time();
    }

}

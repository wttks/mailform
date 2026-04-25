<?php

namespace AIJOH\AISpam;

/**
 * スパム判定結果。
 */
final class SpamJudgement {

    public function __construct(
        /** スパム判定結果。true = スパム、false = 通常 */
        public readonly bool $isSpam,
        /** スパムスコア（0.0〜1.0）。値が大きいほどスパムらしい */
        public readonly float $score,
        /** 判定理由（ログ・デバッグ用） */
        public readonly string $reason,
    ) {}


    /**
     * 通常（スパムでない）判定の結果を返す。Fail Open 用。
     */
    public static function clean( string $reason = '' ) : self {
        return new self(false, 0.0, $reason);
    }


    /**
     * スパム判定の結果を返す。
     */
    public static function spam( float $score, string $reason ) : self {
        return new self(true, $score, $reason);
    }

}

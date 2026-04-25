<?php

namespace AIJOH\RateLimit;

/**
 * レート制限のカウントキー種別。
 *
 * 設定 (config.php の rate_limit.endpoints[*].key) で使う。
 */
enum RateLimitKeyType : string {

    /** クライアント IP アドレス */
    case Ip = 'ip';

    /** PHP セッション ID */
    case Session = 'session';

}

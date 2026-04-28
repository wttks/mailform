<?php

namespace AIJOH\RateLimit;

/**
 * レート制限のエンドポイント識別子。
 *
 * 設定 (config.php の rate_limit.endpoints) のキーと
 * RateLimit::checkOrAbort() の引数で使う。
 */
enum RateLimitEndpoint : string {

    /** フォーム送信エンドポイント (form.php) */
    case Submit = 'submit';

    /** リアルタイムバリデーションエンドポイント (validate.php) */
    case Validate = 'validate';

}

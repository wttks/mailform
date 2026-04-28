<?php
/**
 * レート制限の設定。
 *
 * 送信エンドポイント (submit) とリアルタイム検証 (validate) で
 * それぞれ別の上限を設定できる。複数ルールはすべて評価され、
 * どれかに引っかかれば拒否される。
 *
 * whitelist_ips に開発機 / Docker 内部レンジを入れて開発時の連投を許容。
 */
return [
    'enabled'       => true,
    'storage_dir'   => sys_get_temp_dir() . '/mailform_ratelimit',
    'whitelist_ips' => ['127.0.0.1', '::1', '172.20.0.0/16'],
    'endpoints' => [
        'submit' => [
            ['key' => 'ip',      'limit' => 5,  'window' => 60],
            ['key' => 'ip',      'limit' => 30, 'window' => 3600],
            ['key' => 'session', 'limit' => 3,  'window' => 60],
        ],
        'validate' => [
            ['key' => 'ip',      'limit' => 60, 'window' => 60],
            ['key' => 'session', 'limit' => 30, 'window' => 60],
        ],
    ],
];

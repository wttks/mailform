<?php
/**
 * AI スパム判定の設定。
 *
 * fields に挙げたフィールドだけが AI に送られる（プライバシー配慮）。
 * デフォルトは無効。運用環境ごとに provider と api_key を整えて
 * enabled を true に切り替える想定。
 */
return [
    'enabled'       => false,
    'fields'        => ['name', 'furigana', 'address'],
    'threshold'     => 0.7,
    'cache'         => true,
    'cache_dir'     => sys_get_temp_dir() . '/mailform_aispam_cache',
    'cache_ttl'     => 86400 * 7,
    'block_message' => '送信内容に問題が検出されました。お手数ですがお電話でお問い合わせください。',
];

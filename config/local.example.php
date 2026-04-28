<?php
/**
 * 環境別設定の上書き例。
 *
 * 本ファイルを config.local.php としてコピーし、本番環境向けに値を上書きする。
 * config.local.php は .gitignore で git 管理外にする想定。
 *
 * config.php の base 設定と深くマージされる:
 *   - 連想配列は再帰マージ（足りないキーは base から残る）
 *   - リスト（数値キー配列）は完全置換
 */
return [
    // ホワイトリストを空にして本番でレート制限を完全有効化
    'rate_limit' => [
        'whitelist_ips' => [],
    ],

    // AI スパム判定を有効化
    'ai_spam' => [
        'enabled' => true,
    ],

    // AI クライアントを API ベースに切り替え
    'ai' => [
        'provider' => 'claude_api',
        'api_key'  => getenv('CLAUDE_API_KEY') ?: '',
    ],
];

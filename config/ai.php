<?php
/**
 * 汎用 AI クライアント設定。
 * スパム判定だけでなく将来の AI 機能でも使う。
 *
 * provider: 'claude_api' | 'openai_api' | 'gemini_api' | 'claude_cli'
 *   - claude_cli は API キー不要（PHP 実行ユーザーから claude コマンドが PATH 上で実行可能であること）
 *   - 本番環境では claude_api 等の API ベースを推奨
 */
return [
    'provider' => 'claude_cli',
    'api_key'  => getenv('CLAUDE_API_KEY') ?: '',
    'model'    => 'claude-haiku-4-5',
    'timeout'  => 30,
];

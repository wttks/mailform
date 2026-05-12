<?php

namespace AIJOH\Config;

use AIJOH\AI\AIProvider;
use AIJOH\Form\Draft\DraftConsent;
use AIJOH\Form\Draft\DraftManager;
use AIJOH\RateLimit\RateLimitEndpoint;
use AIJOH\RateLimit\RateLimitKeyType;

/**
 * フォーム設定のスキーマ検証。
 *
 * loadFormConfig 等の起動直後に呼び出し、不整合があれば早期に例外を投げる。
 */
class ConfigValidator {

    /**
     * 設定全体を検証する。問題があれば ConfigException を投げる。
     *
     * @param array $config フォーム設定
     * @throws ConfigException
     */
    public static function validate( array $config ) : void {
        self::validateRequired($config);
        self::validateAi($config);
        self::validateAiSpam($config);
        self::validateRateLimit($config);
        self::validateDraft($config);
        self::validateRedirectUrls($config);
        self::validateSender($config);
        self::validateDevBypass($config);
    }


    /**
     * dev_bypass セクションの検証。
     *
     * enabled=true のときに限り bypass / match を必須で検証する。
     * IP ホワイトリストの代替として「特定の入力値が一致したら防御層をバイパス」する設定。
     */
    private static function validateDevBypass( array $config ) : void {
        if ( ! isset($config['dev_bypass']) ) {
            return;
        }
        $dev = $config['dev_bypass'];
        if ( ! is_array($dev) ) {
            throw new ConfigException("dev_bypass は配列である必要があります。");
        }
        if ( isset($dev['enabled']) && ! is_bool($dev['enabled']) ) {
            throw new ConfigException("dev_bypass.enabled は bool である必要があります。");
        }
        if ( empty($dev['enabled']) ) {
            return;
        }

        // bypass: 'rate_limit' / 'ai_spam' のいずれかの文字列を含む非空配列
        if ( ! isset($dev['bypass']) ) {
            throw new ConfigException("dev_bypass.bypass は必須です（バイパスする防御層を指定）。");
        }
        if ( ! is_array($dev['bypass']) || empty($dev['bypass']) ) {
            throw new ConfigException("dev_bypass.bypass は非空の配列である必要があります。");
        }
        $validLayers = [ 'rate_limit', 'ai_spam' ];
        foreach ( $dev['bypass'] as $i => $layer ) {
            if ( ! is_string($layer) || ! in_array($layer, $validLayers, true) ) {
                throw new ConfigException(
                    "dev_bypass.bypass[{$i}] は " . implode(' / ', $validLayers) . " のいずれかである必要があります。"
                    . " 現在: '" . (is_scalar($layer) ? (string) $layer : gettype($layer)) . "'"
                );
            }
        }

        // match: Closure または配列
        if ( ! isset($dev['match']) ) {
            throw new ConfigException("dev_bypass.match は必須です（バイパス判定の条件）。");
        }
        $match = $dev['match'];
        if ( ! ( $match instanceof \Closure ) && ! is_array($match) ) {
            throw new ConfigException("dev_bypass.match は Closure または配列である必要があります。");
        }
        if ( is_array($match) ) {
            if ( empty($match) ) {
                throw new ConfigException("dev_bypass.match (配列形式) は非空である必要があります。");
            }
            foreach ( $match as $field => $values ) {
                if ( ! is_string($field) ) {
                    throw new ConfigException("dev_bypass.match のキーはフィールド名（文字列）である必要があります。");
                }
                if ( ! is_string($values) && ! is_array($values) ) {
                    throw new ConfigException("dev_bypass.match.{$field} は文字列または文字列配列である必要があります。");
                }
                if ( is_array($values) ) {
                    foreach ( $values as $i => $v ) {
                        if ( ! is_string($v) ) {
                            throw new ConfigException("dev_bypass.match.{$field}[{$i}] は文字列である必要があります。");
                        }
                    }
                }
            }
        }

        // expires_at: 任意、文字列で解析可能な日付
        if ( isset($dev['expires_at']) ) {
            if ( ! is_string($dev['expires_at']) ) {
                throw new ConfigException("dev_bypass.expires_at は 'YYYY-MM-DD' 形式の文字列である必要があります。");
            }
            if ( strtotime($dev['expires_at']) === false ) {
                throw new ConfigException("dev_bypass.expires_at が日付として解析できません: '{$dev['expires_at']}'");
            }
        } else {
            // enabled=true で期限なし → 長期放置リスク
            error_log(
                "[mailform] WARN: dev_bypass.enabled=true ですが expires_at が未設定です。"
                . " 長期間放置されると意図せず開発者用バックドアが残ります。"
                . " expires_at に近い日付（例: '2026-12-31'）を設定することを推奨します。"
            );
        }
    }


    /**
     * sender セクションの検証。max_recipients_per_request の値検証等。
     */
    private static function validateSender( array $config ) : void {
        $sender = $config['sender'] ?? null;
        if ( ! is_array($sender) ) {
            return;
        }
        if ( isset($sender['max_recipients_per_request']) ) {
            $max = $sender['max_recipients_per_request'];
            if ( ! ( is_int($max) && $max > 0 ) ) {
                throw new ConfigException(
                    "sender.max_recipients_per_request は正の整数である必要があります。"
                );
            }
        }
        // 各 SendMail 設定（管理者向け / 顧客向け 等）の mailer / attachments を検証
        foreach ( $sender as $key => $sendConfig ) {
            if ( $key === 'max_recipients_per_request' ) {
                continue;
            }
            if ( ! is_array($sendConfig) ) {
                continue;
            }
            self::validateSenderMailer((string) $key, $sendConfig);
            self::validateSenderAttachments((string) $key, $sendConfig);
        }
    }


    /**
     * 各 SendMail 設定の mailer 値を検証する。
     */
    private static function validateSenderMailer( string $sendKey, array $sendConfig ) : void {
        if ( ! isset($sendConfig['mailer']) ) {
            return;
        }
        $mailer = $sendConfig['mailer'];
        if ( ! is_array($mailer) ) {
            throw new ConfigException("sender.{$sendKey}.mailer は配列である必要があります。");
        }
        if ( isset($mailer['type']) && ! is_string($mailer['type']) ) {
            throw new ConfigException("sender.{$sendKey}.mailer.type は文字列である必要があります。");
        }
        // type の値検証は Factory がランタイムで行う（独自登録 register() 後の実行時にも対応するため）
    }


    /**
     * 各 SendMail 設定の attachments 値を検証する。
     */
    private static function validateSenderAttachments( string $sendKey, array $sendConfig ) : void {
        if ( ! isset($sendConfig['attachments']) ) {
            return;
        }
        $attachments = $sendConfig['attachments'];
        // Closure (動的) は実行時評価なので config 検証はスキップ
        if ( is_callable($attachments) ) {
            return;
        }
        if ( ! is_array($attachments) ) {
            throw new ConfigException(
                "sender.{$sendKey}.attachments は配列または Closure である必要があります。"
            );
        }
        foreach ( $attachments as $i => $item ) {
            if ( is_string($item) ) {
                continue;
            }
            if ( is_array($item) ) {
                if ( ! isset($item['path']) || ! is_string($item['path']) ) {
                    throw new ConfigException(
                        "sender.{$sendKey}.attachments[{$i}] は path キーに文字列が必要です。"
                    );
                }
                if ( isset($item['name']) && ! is_string($item['name']) ) {
                    throw new ConfigException(
                        "sender.{$sendKey}.attachments[{$i}].name は文字列である必要があります。"
                    );
                }
                continue;
            }
            throw new ConfigException(
                "sender.{$sendKey}.attachments[{$i}] は文字列 or 配列 ['path' => ..., 'name' => ...] である必要があります。"
            );
        }
    }


    /**
     * complete_url / confirm_url の値検証（Open Redirect 対策）。
     *
     * mailform は config に hardcoded された URL しかリダイレクト先にできないため、
     * 攻撃者が動的にリダイレクト先を決める経路は通常存在しない。ただし設置者が
     * config に危険な URL を書いてしまうのを防ぐため、起動時に値を検証する。
     *
     * - `javascript:` `data:` `vbscript:` `file:` 等のスキームは ConfigException
     * - 絶対 URL でホスト指定がある場合、現在のリクエストホストと違えば WARN ログ
     */
    private static function validateRedirectUrls( array $config ) : void {
        foreach ( [ 'complete_url', 'confirm_url' ] as $key ) {
            $url = $config[ $key ] ?? '';
            if ( ! is_string($url) || $url === '' ) {
                continue;
            }
            self::assertSafeRedirectUrl($url, $key);
        }
    }


    /**
     * リダイレクト URL が安全か検証する。
     * @throws ConfigException 危険スキームの場合
     */
    private static function assertSafeRedirectUrl( string $url, string $configKey ) : void {
        // 危険スキーム: javascript:, data:, vbscript:, file:, ftp:, jar: 等
        $dangerousSchemes = [ 'javascript', 'data', 'vbscript', 'file', 'jar', 'view-source' ];
        $lower = strtolower(trim($url));
        foreach ( $dangerousSchemes as $scheme ) {
            if ( str_starts_with($lower, $scheme . ':') ) {
                throw new ConfigException(
                    "{$configKey} に危険なスキーム ({$scheme}:) が含まれています: '{$url}'"
                );
            }
        }
        // プロトコル相対 URL `//host/path` も外部ホスト向けの可能性があるので警告
        if ( str_starts_with($url, '//') ) {
            error_log("[ConfigValidator] WARN: {$configKey} がプロトコル相対 URL です。"
                . "外部ホストにリダイレクトされる可能性があります: {$url}");
            return;
        }
        // 絶対 URL でホストが現在のリクエストと違うなら WARN
        if ( preg_match('/^https?:\/\//i', $url) === 1 ) {
            $configHost = parse_url($url, PHP_URL_HOST);
            $requestHost = $_SERVER['HTTP_HOST'] ?? '';
            if ( $configHost && $requestHost && strcasecmp($configHost, $requestHost) !== 0 ) {
                error_log("[ConfigValidator] WARN: {$configKey} のホストがリクエストと異なります。"
                    . "外部リダイレクト: config={$configHost}, request={$requestHost}");
            }
        }
    }


    /**
     * 必須キーが存在するか。
     */
    private static function validateRequired( array $config ) : void {
        foreach ( ['validation', 'sender'] as $key ) {
            if ( ! isset($config[$key]) ) {
                throw new ConfigException("設定キー '{$key}' は必須です。");
            }
            if ( ! is_array($config[$key]) ) {
                throw new ConfigException("設定キー '{$key}' は配列である必要があります。");
            }
        }
    }


    /**
     * ai セクションの検証。provider が enum 値である必要がある。
     */
    private static function validateAi( array $config ) : void {
        if ( ! isset($config['ai']) ) {
            return;  // ai 自体は任意
        }
        $ai = $config['ai'];
        $provider = $ai['provider'] ?? null;
        if ( $provider === null || $provider === '' ) {
            return;  // provider 未設定は ai_spam 側でチェック
        }
        if ( AIProvider::tryFrom((string)$provider) === null ) {
            $valid = implode(', ', array_map(fn($e) => "'{$e->value}'", AIProvider::cases()));
            throw new ConfigException("ai.provider が不正です: '{$provider}'。有効な値: {$valid}");
        }
    }


    /**
     * ai_spam セクションの検証。enabled=true の場合は ai.provider 必須など。
     */
    private static function validateAiSpam( array $config ) : void {
        $aiSpam = $config['ai_spam'] ?? [];
        if ( empty($aiSpam['enabled']) ) {
            return;
        }
        // ai_spam 有効時は ai.provider 必須
        $providerName = $config['ai']['provider'] ?? '';
        if ( $providerName === '' ) {
            throw new ConfigException("ai_spam.enabled=true ですが ai.provider が設定されていません。");
        }
        $provider = AIProvider::tryFrom((string)$providerName);
        if ( $provider === null ) {
            // 既に validateAi で弾かれているはずだが念のため
            throw new ConfigException("ai_spam を有効化する前に ai.provider を有効な値にしてください。");
        }
        // API ベースの provider は api_key 必須
        if ( $provider->requiresApiKey() && empty($config['ai']['api_key']) ) {
            throw new ConfigException("ai.provider='{$provider->value}' は api_key が必要です。");
        }

        // fail_mode の値検証
        $failMode = $aiSpam['fail_mode'] ?? 'block';
        if ( ! in_array($failMode, [ 'block', 'allow', 'silent_block' ], true) ) {
            throw new ConfigException(
                "ai_spam.fail_mode は 'block' / 'allow' / 'silent_block' のいずれかである必要があります。現在: '{$failMode}'"
            );
        }

        // max_input_bytes
        if ( isset($aiSpam['max_input_bytes']) && ! ( is_int($aiSpam['max_input_bytes']) && $aiSpam['max_input_bytes'] > 0 ) ) {
            throw new ConfigException("ai_spam.max_input_bytes は正の整数である必要があります。");
        }

        // cache_secret はキャッシュ有効時に必須（HMAC キャッシュキー用）
        if ( ! empty($aiSpam['cache']) ) {
            $secret = (string) ( $aiSpam['cache_secret'] ?? '' );
            if ( $secret === '' ) {
                throw new ConfigException(
                    "ai_spam.cache=true のときは ai_spam.cache_secret が必須です。"
                    . " env('AI_CACHE_SECRET') 等で gitignore された設定から読み込んでください。"
                    . " 起動時生成は再起動毎にキャッシュ全滅 → API コスト増を引き起こすため許可していません。"
                );
            }
            if ( strlen($secret) < 16 ) {
                throw new ConfigException(
                    "ai_spam.cache_secret は最低 16 バイト必要です。32 バイト以上を推奨します。"
                );
            }
        }

        // extra_blocked_tokens は配列のみ許可
        if ( isset($aiSpam['extra_blocked_tokens']) && ! is_array($aiSpam['extra_blocked_tokens']) ) {
            throw new ConfigException("ai_spam.extra_blocked_tokens は配列である必要があります。");
        }
    }


    /**
     * draft セクションの検証。
     * セクション自体は任意（無ければ機能 OFF）。あれば fields と encryption_key 必須。
     */
    private static function validateDraft( array $config ) : void {
        if ( ! isset($config['draft']) ) {
            return;
        }
        $draft = $config['draft'];
        if ( ! is_array($draft) ) {
            throw new ConfigException("draft は配列である必要があります。");
        }

        // fields: 必須、非空配列、各要素は文字列
        if ( ! isset($draft['fields']) ) {
            throw new ConfigException("draft.fields は必須です（保存対象フィールドのホワイトリスト）。");
        }
        if ( ! is_array($draft['fields']) || empty($draft['fields']) ) {
            throw new ConfigException("draft.fields は非空の配列である必要があります。");
        }
        foreach ( $draft['fields'] as $i => $field ) {
            if ( ! is_string($field) ) {
                throw new ConfigException("draft.fields[{$i}] は文字列である必要があります。");
            }
        }

        // encryption_key: 必須、32 バイト
        if ( ! isset($draft['encryption_key']) ) {
            throw new ConfigException("draft.encryption_key は必須です（sodium 用 32 バイト鍵）。");
        }
        if ( ! is_string($draft['encryption_key']) ) {
            throw new ConfigException("draft.encryption_key は文字列である必要があります。");
        }
        if ( strlen($draft['encryption_key']) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) {
            throw new ConfigException(
                "draft.encryption_key は " . SODIUM_CRYPTO_SECRETBOX_KEYBYTES
                . " バイトである必要があります。現在: " . strlen($draft['encryption_key']) . " バイト。"
            );
        }

        // 数値系のチェック
        foreach ( [ 'compress', 'max_bytes', 'split', 'ttl' ] as $k ) {
            if ( isset($draft[$k]) && ! ( is_int($draft[$k]) && $draft[$k] >= 0 ) ) {
                throw new ConfigException("draft.{$k} は 0 以上の整数である必要があります。");
            }
        }

        // cookie セクション
        if ( isset($draft['cookie']) ) {
            if ( ! is_array($draft['cookie']) ) {
                throw new ConfigException("draft.cookie は配列である必要があります。");
            }
            $path = $draft['cookie']['path'] ?? '/';
            if ( $path === '/' ) {
                error_log(
                    "[mailform] WARN: draft.cookie.path が '/' のため、draft Cookie が全エンドポイントに送信されます。"
                    . "/<form-path>/ に絞ることを推奨します。"
                );
            }
        }

        // consent セクション
        if ( isset($draft['consent']) ) {
            self::validateDraftConsent($draft['consent']);
        }

        // blocked_fields セクション
        if ( isset($draft['blocked_fields']) && ! is_array($draft['blocked_fields']) ) {
            throw new ConfigException("draft.blocked_fields は配列である必要があります。");
        }

        // 危険フィールド名のチェック（強制除外されるが警告は出す）
        $dangerous = DraftManager::detectDangerousFields($draft['fields'], $draft['blocked_fields'] ?? []);
        if ( ! empty($dangerous) ) {
            error_log(
                "[mailform] WARN: draft.fields に password / credit-card 系フィールドが含まれています: "
                . implode(', ', $dangerous)
                . "。これらは draft 保存対象から強制除外されます。"
            );
        }
    }


    /**
     * draft.consent セクションの検証。
     */
    private static function validateDraftConsent( $consent ) : void {
        if ( ! is_array($consent) ) {
            throw new ConfigException("draft.consent は配列である必要があります。");
        }

        $validModes = [
            DraftConsent::MODE_BUILTIN,
            DraftConsent::MODE_CALLBACK,
            DraftConsent::MODE_DISABLED,
        ];
        if ( isset($consent['mode']) && ! in_array($consent['mode'], $validModes, true) ) {
            throw new ConfigException(
                "draft.consent.mode は " . implode(' / ', $validModes) . " のいずれかである必要があります。"
                . " 現在: '" . (string) $consent['mode'] . "'"
            );
        }

        $validBehaviors = [
            DraftConsent::BEHAVIOR_OPT_IN,
            DraftConsent::BEHAVIOR_OPT_OUT,
        ];
        if ( isset($consent['behavior']) && ! in_array($consent['behavior'], $validBehaviors, true) ) {
            throw new ConfigException(
                "draft.consent.behavior は " . implode(' / ', $validBehaviors) . " のいずれかである必要があります。"
                . " 現在: '" . (string) $consent['behavior'] . "'"
            );
        }

        // callback モードは check_js 必須
        if ( ( $consent['mode'] ?? null ) === DraftConsent::MODE_CALLBACK && empty($consent['check_js']) ) {
            throw new ConfigException(
                "draft.consent.mode='callback' のときは check_js を指定してください"
                . "（CMP の同意状態を返す JS 式、例: window.OnetrustActiveGroups?.includes('C0003')）。"
            );
        }
    }


    /**
     * rate_limit セクションの検証。endpoints のキーと key 種別が enum 値である必要がある。
     */
    private static function validateRateLimit( array $config ) : void {
        $rl = $config['rate_limit'] ?? null;
        if ( $rl === null || empty($rl['enabled']) ) {
            return;
        }
        $endpoints = $rl['endpoints'] ?? [];
        if ( ! is_array($endpoints) ) {
            throw new ConfigException("rate_limit.endpoints は配列である必要があります。");
        }
        $validEndpoints = array_map(fn($e) => $e->value, RateLimitEndpoint::cases());
        $validKeys      = array_map(fn($e) => $e->value, RateLimitKeyType::cases());

        foreach ( $endpoints as $name => $rules ) {
            if ( ! in_array((string)$name, $validEndpoints, true) ) {
                throw new ConfigException(
                    "rate_limit.endpoints のキー '{$name}' は不正です。有効な値: " . implode(', ', $validEndpoints)
                );
            }
            if ( ! is_array($rules) ) continue;
            foreach ( $rules as $i => $rule ) {
                $key = $rule['key'] ?? null;
                if ( $key !== null && ! in_array((string)$key, $validKeys, true) ) {
                    throw new ConfigException(
                        "rate_limit.endpoints.{$name}[{$i}].key '{$key}' は不正です。有効な値: " . implode(', ', $validKeys)
                    );
                }
            }
        }
    }

}

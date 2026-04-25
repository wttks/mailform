<?php

namespace AIJOH\Config;

use AIJOH\AI\AIProvider;
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

<?php

namespace AIJOH\Test\Config;

use AIJOH\Config\ConfigException;
use AIJOH\Config\ConfigValidator;
use PHPUnit\Framework\TestCase;

class ConfigValidatorTest extends TestCase {

    private function validBase() : array {
        return [
            'validation' => [],
            'sender'     => [],
        ];
    }


    private function validDraftKey() : string {
        return str_repeat("\x00", SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    // ---- 必須キー ----

    public function test_validation_欠落で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("'validation' は必須");
        ConfigValidator::validate(['sender' => []]);
    }

    public function test_sender_欠落で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("'sender' は必須");
        ConfigValidator::validate(['validation' => []]);
    }

    public function test_必須キー揃っていれば_例外なし() : void {
        ConfigValidator::validate($this->validBase());
        $this->assertTrue(true);
    }

    // ---- ai ----

    public function test_ai_provider_未設定でも_ai_spam_無効なら_OK() : void {
        ConfigValidator::validate($this->validBase() + ['ai' => []]);
        $this->assertTrue(true);
    }

    public function test_ai_provider_が_不正な値で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('ai.provider が不正');
        ConfigValidator::validate($this->validBase() + [
            'ai' => ['provider' => 'unknown_provider'],
        ]);
    }

    public function test_ai_provider_が_有効な_enum_値ならOK() : void {
        foreach ( ['claude_api', 'claude_cli', 'openai_api', 'gemini_api'] as $p ) {
            ConfigValidator::validate($this->validBase() + ['ai' => ['provider' => $p, 'api_key' => 'k']]);
        }
        $this->assertTrue(true);
    }

    // ---- ai_spam ----

    public function test_ai_spam_有効_かつ_provider_未設定で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('ai.provider が設定されていません');
        ConfigValidator::validate($this->validBase() + [
            'ai_spam' => ['enabled' => true],
        ]);
    }

    public function test_ai_spam_有効_かつ_API_provider_で_api_key_欠落で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("api_key が必要");
        ConfigValidator::validate($this->validBase() + [
            'ai'      => ['provider' => 'claude_api'],  // api_key なし
            'ai_spam' => ['enabled' => true],
        ]);
    }

    public function test_ai_spam_有効_かつ_claude_cli_なら_api_key_不要(): void {
        ConfigValidator::validate($this->validBase() + [
            'ai'      => ['provider' => 'claude_cli'],
            'ai_spam' => ['enabled' => true],
        ]);
        $this->assertTrue(true);
    }

    public function test_ai_spam_無効なら_ai_設定が空でもOK() : void {
        ConfigValidator::validate($this->validBase() + [
            'ai_spam' => ['enabled' => false],
        ]);
        $this->assertTrue(true);
    }

    // ---- ai_spam: fail_mode / max_input_bytes / cache_secret ----

    public function test_ai_spam_fail_mode_が不正値で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('ai_spam.fail_mode');
        ConfigValidator::validate($this->validBase() + [
            'ai'      => ['provider' => 'claude_cli'],
            'ai_spam' => [ 'enabled' => true, 'fail_mode' => 'invalid' ],
        ]);
    }

    public function test_ai_spam_fail_mode_silent_block_は_OK() : void {
        ConfigValidator::validate($this->validBase() + [
            'ai'      => ['provider' => 'claude_cli'],
            'ai_spam' => [ 'enabled' => true, 'fail_mode' => 'silent_block' ],
        ]);
        $this->assertTrue(true);
    }

    public function test_ai_spam_max_input_bytes_が0以下で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('max_input_bytes');
        ConfigValidator::validate($this->validBase() + [
            'ai'      => ['provider' => 'claude_cli'],
            'ai_spam' => [ 'enabled' => true, 'max_input_bytes' => 0 ],
        ]);
    }

    public function test_ai_spam_cache_有効で_cache_secret_欠落で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('cache_secret');
        ConfigValidator::validate($this->validBase() + [
            'ai'      => ['provider' => 'claude_cli'],
            'ai_spam' => [ 'enabled' => true, 'cache' => true, 'cache_dir' => '/tmp' ],
        ]);
    }

    public function test_ai_spam_cache_有効_かつ_cache_secret_短すぎで例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('16 バイト');
        ConfigValidator::validate($this->validBase() + [
            'ai'      => ['provider' => 'claude_cli'],
            'ai_spam' => [
                'enabled'      => true,
                'cache'        => true,
                'cache_dir'    => '/tmp',
                'cache_secret' => 'short',
            ],
        ]);
    }

    public function test_ai_spam_cache_有効_かつ_cache_secret_十分長で_OK() : void {
        ConfigValidator::validate($this->validBase() + [
            'ai'      => ['provider' => 'claude_cli'],
            'ai_spam' => [
                'enabled'      => true,
                'cache'        => true,
                'cache_dir'    => '/tmp',
                'cache_secret' => str_repeat('a', 32),
            ],
        ]);
        $this->assertTrue(true);
    }

    public function test_ai_spam_extra_blocked_tokens_配列以外で例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate($this->validBase() + [
            'ai'      => ['provider' => 'claude_cli'],
            'ai_spam' => [
                'enabled'              => true,
                'extra_blocked_tokens' => 'not-array',
            ],
        ]);
    }

    // ---- rate_limit ----

    public function test_rate_limit_endpoints_に_不正なキーで例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("rate_limit.endpoints のキー 'unknown'");
        ConfigValidator::validate($this->validBase() + [
            'rate_limit' => [
                'enabled' => true,
                'endpoints' => ['unknown' => []],
            ],
        ]);
    }

    public function test_rate_limit_rules_の_key_が_不正で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("'unknown_key' は不正");
        ConfigValidator::validate($this->validBase() + [
            'rate_limit' => [
                'enabled' => true,
                'endpoints' => [
                    'submit' => [['key' => 'unknown_key', 'limit' => 5, 'window' => 60]],
                ],
            ],
        ]);
    }

    public function test_rate_limit_有効_な_設定ならOK() : void {
        ConfigValidator::validate($this->validBase() + [
            'rate_limit' => [
                'enabled' => true,
                'endpoints' => [
                    'submit' => [['key' => 'ip', 'limit' => 5, 'window' => 60]],
                    'validate' => [['key' => 'session', 'limit' => 30, 'window' => 60]],
                ],
            ],
        ]);
        $this->assertTrue(true);
    }

    public function test_rate_limit_無効なら_endpoints_に何があっても_スルー() : void {
        ConfigValidator::validate($this->validBase() + [
            'rate_limit' => [
                'enabled' => false,
                'endpoints' => ['anything_goes' => []],
            ],
        ]);
        $this->assertTrue(true);
    }

    // ---- draft ----

    public function test_draft_セクション無しは_スルー() : void {
        ConfigValidator::validate($this->validBase());
        $this->assertTrue(true);
    }

    public function test_draft_最小設定で_OK() : void {
        ConfigValidator::validate($this->validBase() + [
            'draft' => [
                'fields'         => [ 'name', 'message' ],
                'encryption_key' => $this->validDraftKey(),
            ],
        ]);
        $this->assertTrue(true);
    }

    public function test_draft_fields欠落で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('draft.fields は必須');
        ConfigValidator::validate($this->validBase() + [
            'draft' => [ 'encryption_key' => $this->validDraftKey() ],
        ]);
    }

    public function test_draft_fields空配列で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('非空の配列');
        ConfigValidator::validate($this->validBase() + [
            'draft' => [
                'fields'         => [],
                'encryption_key' => $this->validDraftKey(),
            ],
        ]);
    }

    public function test_draft_fields要素が文字列以外で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('draft.fields[0]');
        ConfigValidator::validate($this->validBase() + [
            'draft' => [
                'fields'         => [ 123 ],
                'encryption_key' => $this->validDraftKey(),
            ],
        ]);
    }

    public function test_draft_encryption_key欠落で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('draft.encryption_key は必須');
        ConfigValidator::validate($this->validBase() + [
            'draft' => [ 'fields' => [ 'name' ] ],
        ]);
    }

    public function test_draft_encryption_key不正サイズで例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('バイトである必要');
        ConfigValidator::validate($this->validBase() + [
            'draft' => [
                'fields'         => [ 'name' ],
                'encryption_key' => 'short',
            ],
        ]);
    }

    public function test_draft_compressが負の整数で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('draft.compress');
        ConfigValidator::validate($this->validBase() + [
            'draft' => [
                'fields'         => [ 'name' ],
                'encryption_key' => $this->validDraftKey(),
                'compress'       => -1,
            ],
        ]);
    }

    public function test_draft_consent_modeが不正値で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('draft.consent.mode');
        ConfigValidator::validate($this->validBase() + [
            'draft' => [
                'fields'         => [ 'name' ],
                'encryption_key' => $this->validDraftKey(),
                'consent'        => [ 'mode' => 'invalid' ],
            ],
        ]);
    }

    public function test_draft_consent_behaviorが不正値で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('draft.consent.behavior');
        ConfigValidator::validate($this->validBase() + [
            'draft' => [
                'fields'         => [ 'name' ],
                'encryption_key' => $this->validDraftKey(),
                'consent'        => [ 'behavior' => 'maybe' ],
            ],
        ]);
    }

    public function test_draft_consent_callbackで_check_js欠落は例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("check_js");
        ConfigValidator::validate($this->validBase() + [
            'draft' => [
                'fields'         => [ 'name' ],
                'encryption_key' => $this->validDraftKey(),
                'consent'        => [ 'mode' => 'callback' ],
            ],
        ]);
    }

    public function test_draft_consent_callbackで_check_jsありは_OK() : void {
        ConfigValidator::validate($this->validBase() + [
            'draft' => [
                'fields'         => [ 'name' ],
                'encryption_key' => $this->validDraftKey(),
                'consent'        => [
                    'mode'     => 'callback',
                    'check_js' => 'window.OnetrustActiveGroups?.includes("C0003")',
                ],
            ],
        ]);
        $this->assertTrue(true);
    }

    public function test_draft_blocked_fields_配列以外で例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate($this->validBase() + [
            'draft' => [
                'fields'         => [ 'name' ],
                'encryption_key' => $this->validDraftKey(),
                'blocked_fields' => 'not-an-array',
            ],
        ]);
    }

    public function test_draft_全部入りでOK() : void {
        ConfigValidator::validate($this->validBase() + [
            'draft' => [
                'fields'         => [ 'name', 'email', 'message' ],
                'encryption_key' => $this->validDraftKey(),
                'compress'       => 512,
                'max_bytes'      => 7000,
                'split'          => 5,
                'ttl'            => 86400,
                'cookie'         => [
                    'name_prefix' => 'mailform_draft_contact',
                    'path'        => '/contact/',
                ],
                'consent'        => [
                    'mode'           => 'builtin',
                    'behavior'       => 'opt-in',
                    'policy_version' => '2026-04-28',
                ],
                'blocked_fields' => [ 'mynumber' ],
            ],
        ]);
        $this->assertTrue(true);
    }

    // ---- redirect URL ----

    public function test_complete_url_javascript_スキームで例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('javascript:');
        ConfigValidator::validate($this->validBase() + [
            'complete_url' => 'javascript:alert(1)',
        ]);
    }

    public function test_complete_url_data_スキームで例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate($this->validBase() + [
            'complete_url' => 'data:text/html,<script>alert(1)</script>',
        ]);
    }

    public function test_complete_url_vbscript_スキームで例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate($this->validBase() + [
            'complete_url' => 'vbscript:msgbox(1)',
        ]);
    }

    public function test_complete_url_file_URL_で例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate($this->validBase() + [
            'complete_url' => 'file:///etc/passwd',
        ]);
    }

    public function test_confirm_url_危険スキームで例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate($this->validBase() + [
            'confirm_url' => 'javascript:alert(1)',
        ]);
    }

    public function test_complete_url_相対パスはOK() : void {
        ConfigValidator::validate($this->validBase() + [
            'complete_url' => '/contact/thanks.html',
        ]);
        $this->assertTrue(true);
    }

    public function test_complete_url_同一ホストの絶対URLはOK() : void {
        $_SERVER['HTTP_HOST'] = 'example.com';
        ConfigValidator::validate($this->validBase() + [
            'complete_url' => 'https://example.com/thanks',
        ]);
        $this->assertTrue(true);
    }

    public function test_complete_url_未設定はスルー() : void {
        ConfigValidator::validate($this->validBase());
        $this->assertTrue(true);
    }

    // ---- sender.max_recipients_per_request ----

    public function test_max_recipients_per_request_正の整数はOK() : void {
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [ 'max_recipients_per_request' => 5 ],
        ]);
        $this->assertTrue(true);
    }

    public function test_max_recipients_per_request_0で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('max_recipients_per_request');
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [ 'max_recipients_per_request' => 0 ],
        ]);
    }

    public function test_max_recipients_per_request_負の整数で例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [ 'max_recipients_per_request' => -1 ],
        ]);
    }

    public function test_max_recipients_per_request_文字列で例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [ 'max_recipients_per_request' => '5' ],
        ]);
    }

}

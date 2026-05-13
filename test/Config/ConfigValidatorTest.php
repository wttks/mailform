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

    // ---- sender.<key>.mailer ----

    public function test_sender_mailer_配列でないと例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('mailer は配列');
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [
                '管理者向けメール' => [ 'mailer' => 'invalid' ],
            ],
        ]);
    }

    public function test_sender_mailer_type_文字列でないと例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('mailer.type');
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [
                '管理者向けメール' => [ 'mailer' => [ 'type' => 123 ] ],
            ],
        ]);
    }

    public function test_sender_mailer_未指定はOK() : void {
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [
                '管理者向けメール' => [ 'to' => 'a@b.com' ],
            ],
        ]);
        $this->assertTrue(true);
    }

    public function test_sender_mailer_type_phpmailer_OK() : void {
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [
                '管理者向けメール' => [ 'mailer' => [ 'type' => 'phpmailer' ] ],
            ],
        ]);
        $this->assertTrue(true);
    }

    // ---- sender.<key>.attachments ----

    public function test_sender_attachments_文字列のみの配列でOK() : void {
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [
                '管理者向けメール' => [ 'attachments' => [ '/path/a.pdf', '/path/b.pdf' ] ],
            ],
        ]);
        $this->assertTrue(true);
    }

    public function test_sender_attachments_path_name_配列でOK() : void {
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [
                '管理者向けメール' => [
                    'attachments' => [
                        [ 'path' => '/path/a.pdf', 'name' => '案内.pdf' ],
                    ],
                ],
            ],
        ]);
        $this->assertTrue(true);
    }

    public function test_sender_attachments_Closureは検証スキップ() : void {
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [
                '管理者向けメール' => [
                    'attachments' => fn() => [ '/path/a.pdf' ],
                ],
            ],
        ]);
        $this->assertTrue(true);
    }

    public function test_sender_attachments_配列以外で例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [
                '管理者向けメール' => [ 'attachments' => 'not-array' ],
            ],
        ]);
    }

    public function test_sender_attachments_path_キー欠落で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('path');
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [
                '管理者向けメール' => [
                    'attachments' => [ [ 'name' => 'x' ] ],
                ],
            ],
        ]);
    }

    public function test_sender_attachments_path_文字列でなくて例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [
                '管理者向けメール' => [
                    'attachments' => [ [ 'path' => 123 ] ],
                ],
            ],
        ]);
    }

    public function test_sender_attachments_name_文字列でなくて例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('name');
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [
                '管理者向けメール' => [
                    'attachments' => [ [ 'path' => '/x', 'name' => 123 ] ],
                ],
            ],
        ]);
    }

    public function test_sender_attachments_要素が文字列_配列以外で例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate([
            'validation' => [],
            'sender'     => [
                '管理者向けメール' => [ 'attachments' => [ 123 ] ],
            ],
        ]);
    }


    // ---- dev_bypass ----

    public function test_dev_bypass_未設定でもOK() : void {
        ConfigValidator::validate($this->validBase());
        $this->assertTrue(true);
    }


    public function test_dev_bypass_enabled_false_なら他の検証はスキップ() : void {
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [ 'enabled' => false ],
        ]);
        $this->assertTrue(true);
    }


    public function test_dev_bypass_配列以外で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('dev_bypass は配列');
        ConfigValidator::validate($this->validBase() + [ 'dev_bypass' => 'string' ]);
    }


    public function test_dev_bypass_enabled_が_bool以外で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('dev_bypass.enabled は bool');
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [ 'enabled' => 'true' ],
        ]);
    }


    public function test_dev_bypass_enabled_true_で_bypass_未設定なら例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('dev_bypass.bypass は必須');
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'match'   => [ 'email' => [ 'qa@example.com' ] ],
                'expires_at' => '2099-12-31',
            ],
        ]);
    }


    public function test_dev_bypass_bypass_が_空配列で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('dev_bypass.bypass は非空');
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'bypass'  => [],
                'match'   => [ 'email' => [ 'qa@example.com' ] ],
                'expires_at' => '2099-12-31',
            ],
        ]);
    }


    public function test_dev_bypass_bypass_に_未定義の層で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('dev_bypass.bypass[0]');
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'bypass'  => [ 'unknown_layer' ],
                'match'   => [ 'email' => [ 'qa@example.com' ] ],
                'expires_at' => '2099-12-31',
            ],
        ]);
    }


    public function test_dev_bypass_match_未設定で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('dev_bypass.match は必須');
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'bypass'  => [ 'rate_limit' ],
                'expires_at' => '2099-12-31',
            ],
        ]);
    }


    public function test_dev_bypass_match_が_Closure_でも配列でもなければ例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('dev_bypass.match は Closure または配列');
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'bypass'  => [ 'rate_limit' ],
                'match'   => 'string',
                'expires_at' => '2099-12-31',
            ],
        ]);
    }


    public function test_dev_bypass_match_配列が空で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('dev_bypass.match (配列形式) は非空');
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'bypass'  => [ 'rate_limit' ],
                'match'   => [],
                'expires_at' => '2099-12-31',
            ],
        ]);
    }


    public function test_dev_bypass_match_の値が文字列でも配列でもなければ例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('dev_bypass.match.email');
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'bypass'  => [ 'rate_limit' ],
                'match'   => [ 'email' => 123 ],
                'expires_at' => '2099-12-31',
            ],
        ]);
    }


    public function test_dev_bypass_expires_at_が文字列以外で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('dev_bypass.expires_at');
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'bypass'  => [ 'rate_limit' ],
                'match'   => [ 'email' => [ 'qa@example.com' ] ],
                'expires_at' => 20991231,
            ],
        ]);
    }


    public function test_dev_bypass_expires_at_が解析不能で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('日付として解析できません');
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'bypass'  => [ 'rate_limit' ],
                'match'   => [ 'email' => [ 'qa@example.com' ] ],
                'expires_at' => 'invalid-date-format',
            ],
        ]);
    }


    public function test_dev_bypass_正しい設定なら例外なし_リスト形式() : void {
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'bypass'  => [ 'rate_limit', 'ai_spam' ],
                'match'   => [ 'email' => [ 'qa@example.com', 'staff@example.com' ] ],
                'expires_at' => '2099-12-31',
            ],
        ]);
        $this->assertTrue(true);
    }


    public function test_dev_bypass_正しい設定なら例外なし_Closure形式() : void {
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'bypass'  => [ 'rate_limit' ],
                'match'   => fn( array $d ) : bool => false,
                'expires_at' => '2099-12-31',
            ],
        ]);
        $this->assertTrue(true);
    }


    public function test_dev_bypass_match_の単一値文字列でもOK() : void {
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'bypass'  => [ 'rate_limit' ],
                'match'   => [ 'email' => 'qa@example.com' ],  // 配列でなく文字列
                'expires_at' => '2099-12-31',
            ],
        ]);
        $this->assertTrue(true);
    }


    public function test_dev_bypass_expires_at_未設定なら_WARNログ出力() : void {
        // error_log は実際にログに出る。例外無しの動作確認のみ行う。
        ConfigValidator::validate($this->validBase() + [
            'dev_bypass' => [
                'enabled' => true,
                'bypass'  => [ 'rate_limit' ],
                'match'   => [ 'email' => [ 'qa@example.com' ] ],
                // expires_at 未設定
            ],
        ]);
        $this->assertTrue(true);
    }


    // ---- lang ----

    public function test_lang_未設定でもOK() : void {
        ConfigValidator::validate($this->validBase());
        $this->assertTrue(true);
    }


    public function test_lang_文字列以外で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('lang は文字列');
        ConfigValidator::validate($this->validBase() + [ 'lang' => 123 ]);
    }


    public function test_lang_空文字列はOK_Translator側でデフォルト維持() : void {
        ConfigValidator::validate($this->validBase() + [ 'lang' => '' ]);
        $this->assertTrue(true);
    }


    public function test_lang_path_traversal_で例外() : void {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('lang は');
        ConfigValidator::validate($this->validBase() + [ 'lang' => '../../etc/passwd' ]);
    }


    public function test_lang_スラッシュ含みで例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate($this->validBase() + [ 'lang' => 'en/ja' ]);
    }


    public function test_lang_ドット含みで例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate($this->validBase() + [ 'lang' => 'en.php' ]);
    }


    public function test_lang_長すぎる値で例外() : void {
        $this->expectException(ConfigException::class);
        ConfigValidator::validate($this->validBase() + [ 'lang' => str_repeat('a', 17) ]);
    }


    public function test_lang_正常値ならOK() : void {
        foreach ( [ 'ja', 'en', 'zh_CN', 'pt_BR', 'en-GB' ] as $lang ) {
            ConfigValidator::validate($this->validBase() + [ 'lang' => $lang ]);
        }
        $this->assertTrue(true);
    }
}

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

}

<?php

namespace AIJOH\Test\AI;

use AIJOH\AI\AIClientFactory;
use AIJOH\AI\Client\ClaudeApiClient;
use AIJOH\AI\Client\ClaudeCliClient;
use AIJOH\AI\Client\GeminiApiClient;
use AIJOH\AI\Client\OpenAiApiClient;
use PHPUnit\Framework\TestCase;

class AIClientFactoryTest extends TestCase {

    public function test_claude_api_で_ClaudeApiClient_が返る() : void {
        $client = AIClientFactory::create(['provider' => 'claude_api', 'api_key' => 'k']);
        $this->assertInstanceOf(ClaudeApiClient::class, $client);
    }

    public function test_claude_cli_で_ClaudeCliClient_が返る() : void {
        $client = AIClientFactory::create(['provider' => 'claude_cli']);
        $this->assertInstanceOf(ClaudeCliClient::class, $client);
    }

    public function test_openai_api_で_OpenAiApiClient_が返る() : void {
        $client = AIClientFactory::create(['provider' => 'openai_api', 'api_key' => 'k']);
        $this->assertInstanceOf(OpenAiApiClient::class, $client);
    }

    public function test_gemini_api_で_GeminiApiClient_が返る() : void {
        $client = AIClientFactory::create(['provider' => 'gemini_api', 'api_key' => 'k']);
        $this->assertInstanceOf(GeminiApiClient::class, $client);
    }

    public function test_不明なプロバイダで例外() : void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('不明な AI プロバイダ: unknown');
        AIClientFactory::create(['provider' => 'unknown']);
    }

}

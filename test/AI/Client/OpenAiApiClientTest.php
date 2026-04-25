<?php

namespace AIJOH\Test\AI\Client;

require_once __DIR__ . '/CapturingPostJson.php';

use AIJOH\AI\AIMessage;
use AIJOH\AI\AIRequest;
use AIJOH\AI\Client\OpenAiApiClient;
use PHPUnit\Framework\TestCase;

class FakeOpenAiClient extends OpenAiApiClient { use CapturingPostJson; }

class OpenAiApiClientTest extends TestCase {

    public function test_system_は_messages_先頭に置かれる_かつ_response_format_指定() : void {
        $client = new FakeOpenAiClient(['api_key' => 'sk-openai', 'model' => 'gpt-5-nano']);
        $client->fakeResponse = [
            'choices' => [['message' => ['content' => '{"is_spam":false,"score":0.1,"reason":"通常"}']]],
        ];
        $req = new AIRequest('sys', [AIMessage::user('hi')], 100, true);
        $resp = $client->send($req);

        $this->assertSame('Bearer sk-openai', $client->capturedHeaders['Authorization']);
        $this->assertSame([
            ['role' => 'system', 'content' => 'sys'],
            ['role' => 'user', 'content' => 'hi'],
        ], $client->capturedBody['messages']);
        $this->assertSame(['type' => 'json_object'], $client->capturedBody['response_format']);
        $this->assertSame(['is_spam' => false, 'score' => 0.1, 'reason' => '通常'], $resp->jsonData);
    }

    public function test_jsonMode_off_では_response_format_は無い() : void {
        $client = new FakeOpenAiClient(['api_key' => 'k']);
        $client->fakeResponse = ['choices' => [['message' => ['content' => 'hello']]]];
        $client->send(new AIRequest('', [AIMessage::user('x')], 50, false));
        $this->assertArrayNotHasKey('response_format', $client->capturedBody);
    }

}

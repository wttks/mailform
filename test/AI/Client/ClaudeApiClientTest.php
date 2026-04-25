<?php

namespace AIJOH\Test\AI\Client;

require_once __DIR__ . '/CapturingPostJson.php';

use AIJOH\AI\AIClientException;
use AIJOH\AI\AIMessage;
use AIJOH\AI\AIRequest;
use AIJOH\AI\Client\ClaudeApiClient;
use PHPUnit\Framework\TestCase;

class FakeClaudeClient extends ClaudeApiClient { use CapturingPostJson; }

class ClaudeApiClientTest extends TestCase {

    public function test_リクエスト構築_と_レスポンス解析() : void {
        $client = new FakeClaudeClient(['api_key' => 'sk-test', 'model' => 'claude-haiku-4-5']);
        $client->fakeResponse = [
            'content' => [['text' => '{"is_spam":true,"score":0.9,"reason":"営業"}']],
        ];

        $req = new AIRequest('sys', [AIMessage::user('hello')], 256, true);
        $resp = $client->send($req);

        $this->assertSame('https://api.anthropic.com/v1/messages', $client->capturedUrl);
        $this->assertSame('sk-test', $client->capturedHeaders['x-api-key']);
        $this->assertSame('2023-06-01', $client->capturedHeaders['anthropic-version']);
        $this->assertSame('claude-haiku-4-5', $client->capturedBody['model']);
        $this->assertSame('sys', $client->capturedBody['system']);
        $this->assertSame([['role' => 'user', 'content' => 'hello']], $client->capturedBody['messages']);
        $this->assertSame(256, $client->capturedBody['max_tokens']);

        $this->assertSame('{"is_spam":true,"score":0.9,"reason":"営業"}', $resp->text);
        $this->assertSame(['is_spam' => true, 'score' => 0.9, 'reason' => '営業'], $resp->jsonData);
    }

    public function test_api_key_未設定で例外() : void {
        $client = new FakeClaudeClient([]);
        $this->expectException(AIClientException::class);
        $client->send(new AIRequest('s', [AIMessage::user('x')]));
    }

}

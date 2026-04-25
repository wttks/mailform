<?php

namespace AIJOH\Test\AI\Client;

require_once __DIR__ . '/CapturingPostJson.php';

use AIJOH\AI\AIMessage;
use AIJOH\AI\AIRequest;
use AIJOH\AI\Client\GeminiApiClient;
use PHPUnit\Framework\TestCase;

class FakeGeminiClient extends GeminiApiClient { use CapturingPostJson; }

class GeminiApiClientTest extends TestCase {

    public function test_リクエスト構築_と_responseMimeType_指定() : void {
        $client = new FakeGeminiClient(['api_key' => 'gem-key', 'model' => 'gemini-2.5-flash']);
        $client->fakeResponse = [
            'candidates' => [[
                'content' => ['parts' => [['text' => '{"is_spam":true,"score":0.8,"reason":"宣伝"}']]],
            ]],
        ];
        $req = new AIRequest('sys', [AIMessage::user('foo'), AIMessage::assistant('bar')], 200, true);
        $resp = $client->send($req);

        $this->assertStringContainsString('models/gemini-2.5-flash:generateContent', $client->capturedUrl);
        $this->assertStringContainsString('?key=gem-key', $client->capturedUrl);
        $this->assertSame([
            ['role' => 'user',  'parts' => [['text' => 'foo']]],
            ['role' => 'model', 'parts' => [['text' => 'bar']]],
        ], $client->capturedBody['contents']);
        $this->assertSame(['parts' => [['text' => 'sys']]], $client->capturedBody['systemInstruction']);
        $this->assertSame('application/json', $client->capturedBody['generationConfig']['responseMimeType']);
        $this->assertSame(200, $client->capturedBody['generationConfig']['maxOutputTokens']);
        $this->assertSame(['is_spam' => true, 'score' => 0.8, 'reason' => '宣伝'], $resp->jsonData);
    }

}

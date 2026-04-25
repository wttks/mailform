<?php

namespace AIJOH\Test\AISpam;

use AIJOH\AI\AIClient;
use AIJOH\AI\AIClientException;
use AIJOH\AI\AIRequest;
use AIJOH\AI\AIResponse;
use AIJOH\AISpam\AISpamDetector;
use PHPUnit\Framework\TestCase;

/**
 * モック AIClient
 */
class FakeAIClient extends AIClient {
    public ?AIResponse $fakeResponse = null;
    public ?AIClientException $fakeException = null;
    public int $sendCount = 0;
    public ?AIRequest $lastRequest = null;

    public function send( AIRequest $request ) : AIResponse {
        $this->sendCount++;
        $this->lastRequest = $request;
        if ( $this->fakeException !== null ) throw $this->fakeException;
        return $this->fakeResponse ?? new AIResponse('', null);
    }
}


class AISpamDetectorTest extends TestCase {

    private string $cacheDir;

    protected function setUp() : void {
        AISpamDetector::reset();
        $this->cacheDir = sys_get_temp_dir() . '/mailform_aispam_test_' . uniqid();
    }

    protected function tearDown() : void {
        AISpamDetector::reset();
        $files = glob($this->cacheDir . '/*') ?: [];
        foreach ( $files as $f ) @unlink($f);
        @rmdir($this->cacheDir);
    }

    private function configure( bool $enabled = true, array $fields = ['name'], bool $cache = false, float $threshold = 0.7 ) : void {
        AISpamDetector::configure(
            ['provider' => 'claude_cli'],
            [
                'enabled'   => $enabled,
                'fields'    => $fields,
                'threshold' => $threshold,
                'cache'     => $cache,
                'cache_dir' => $this->cacheDir,
                'cache_ttl' => 86400,
            ]
        );
    }

    private function setFakeResponse( array $jsonData ) : FakeAIClient {
        $client = new FakeAIClient();
        $client->fakeResponse = new AIResponse(json_encode($jsonData), $jsonData);
        AISpamDetector::setClientForTest($client);
        return $client;
    }

    // ---- disabled / enabled ----

    public function test_disable_すると_常に_clean(): void {
        $this->configure(true);
        $this->setFakeResponse(['is_spam' => true, 'score' => 0.95, 'reason' => 'spam']);
        AISpamDetector::disable();

        $j = AISpamDetector::judge(['name' => 'spam content']);
        $this->assertFalse($j->isSpam);
    }

    public function test_enabled_false_でも_常に_clean(): void {
        $this->configure(false);
        $this->setFakeResponse(['is_spam' => true, 'score' => 0.95, 'reason' => 'spam']);

        $j = AISpamDetector::judge(['name' => 'spam content']);
        $this->assertFalse($j->isSpam);
    }

    public function test_対象フィールドが空なら_clean(): void {
        $this->configure(true, ['memo']);
        $client = $this->setFakeResponse(['is_spam' => true, 'score' => 1.0, 'reason' => 'x']);

        $j = AISpamDetector::judge(['name' => 'something', 'memo' => '']);
        $this->assertFalse($j->isSpam);
        $this->assertSame(0, $client->sendCount);  // AI 呼ばれない
    }

    // ---- 判定 ----

    public function test_is_spam_true_でスパム判定(): void {
        $this->configure(true);
        $this->setFakeResponse(['is_spam' => true, 'score' => 0.9, 'reason' => '営業メール']);

        $j = AISpamDetector::judge(['name' => 'foo']);
        $this->assertTrue($j->isSpam);
        $this->assertSame(0.9, $j->score);
        $this->assertSame('営業メール', $j->reason);
    }

    public function test_score_が_threshold_以上なら_スパム判定(): void {
        $this->configure(true, ['name'], false, 0.7);
        $this->setFakeResponse(['is_spam' => false, 'score' => 0.8, 'reason' => 'borderline']);

        $j = AISpamDetector::judge(['name' => 'foo']);
        $this->assertTrue($j->isSpam);
    }

    public function test_score_が_threshold_未満なら_clean(): void {
        $this->configure(true, ['name'], false, 0.7);
        $this->setFakeResponse(['is_spam' => false, 'score' => 0.5, 'reason' => 'normal']);

        $j = AISpamDetector::judge(['name' => 'foo']);
        $this->assertFalse($j->isSpam);
    }

    // ---- Fail Open ----

    public function test_AIClientException_は_Fail_Open(): void {
        $this->configure(true);
        $client = new FakeAIClient();
        $client->fakeException = new AIClientException('network fail');
        AISpamDetector::setClientForTest($client);

        $j = AISpamDetector::judge(['name' => 'foo']);
        $this->assertFalse($j->isSpam);
        $this->assertStringContainsString('api error', $j->reason);
    }

    public function test_jsonData_が_null_なら_Fail_Open(): void {
        $this->configure(true);
        $client = new FakeAIClient();
        $client->fakeResponse = new AIResponse('not json', null);  // jsonData = null
        AISpamDetector::setClientForTest($client);

        $j = AISpamDetector::judge(['name' => 'foo']);
        $this->assertFalse($j->isSpam);
    }

    // ---- キャッシュ ----

    public function test_キャッシュ有効時_同じ内容で_AI_を再呼び出ししない(): void {
        $this->configure(true, ['name'], true);
        $client = $this->setFakeResponse(['is_spam' => false, 'score' => 0.1, 'reason' => 'ok']);

        AISpamDetector::judge(['name' => 'hello']);
        AISpamDetector::judge(['name' => 'hello']);
        AISpamDetector::judge(['name' => 'hello']);

        $this->assertSame(1, $client->sendCount);
    }

    public function test_キャッシュ無効時_毎回_AI_を呼ぶ(): void {
        $this->configure(true, ['name'], false);
        $client = $this->setFakeResponse(['is_spam' => false, 'score' => 0.1, 'reason' => 'ok']);

        AISpamDetector::judge(['name' => 'hello']);
        AISpamDetector::judge(['name' => 'hello']);

        $this->assertSame(2, $client->sendCount);
    }

    public function test_異なる内容なら_AI_を呼ぶ(): void {
        $this->configure(true, ['name'], true);
        $client = $this->setFakeResponse(['is_spam' => false, 'score' => 0.1, 'reason' => 'ok']);

        AISpamDetector::judge(['name' => 'a']);
        AISpamDetector::judge(['name' => 'b']);

        $this->assertSame(2, $client->sendCount);
    }

    // ---- リクエスト構築 ----

    public function test_AI_リクエストは_jsonMode_true_で送られる(): void {
        $this->configure(true);
        $client = $this->setFakeResponse(['is_spam' => false, 'score' => 0.1, 'reason' => 'x']);

        AISpamDetector::judge(['name' => 'hello']);
        $this->assertTrue($client->lastRequest->jsonMode);
        $this->assertNotEmpty($client->lastRequest->system);
    }

}

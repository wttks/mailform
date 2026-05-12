<?php

namespace AIJOH\Test\AISpam;

use AIJOH\AI\AIClient;
use AIJOH\AI\AIClientException;
use AIJOH\AI\AIRequest;
use AIJOH\AI\AIResponse;
use AIJOH\AISpam\AISpamDetector;
use AIJOH\Http\DevBypass;
use AIJOH\SecurityPayloads;
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
        DevBypass::reset();
        $this->cacheDir = sys_get_temp_dir() . '/mailform_aispam_test_' . uniqid();
    }

    protected function tearDown() : void {
        AISpamDetector::reset();
        DevBypass::reset();
        $files = glob($this->cacheDir . '/*') ?: [];
        foreach ( $files as $f ) @unlink($f);
        @rmdir($this->cacheDir);
    }

    private function configure(
        bool $enabled = true,
        array $fields = ['name'],
        bool $cache = false,
        float $threshold = 0.7,
        string $failMode = 'allow',  // 既存テストとの互換性のため、デフォルトは allow
    ) : void {
        AISpamDetector::configure(
            ['provider' => 'claude_cli'],
            [
                'enabled'      => $enabled,
                'fields'       => $fields,
                'threshold'    => $threshold,
                'cache'        => $cache,
                'cache_dir'    => $this->cacheDir,
                'cache_ttl'    => 86400,
                'cache_secret' => str_repeat('a', 32),  // テスト用 HMAC シード
                'fail_mode'    => $failMode,
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


    public function test_リクエストは_user_input_XML_境界で送られる(): void {
        $this->configure(true);
        $client = $this->setFakeResponse(['is_spam' => false, 'score' => 0.1, 'reason' => 'x']);

        AISpamDetector::judge(['name' => '田中', 'email' => 'a@b.com']);
        // user メッセージに <user_input> 境界タグが含まれる
        $userMessage = $client->lastRequest->messages[0]->content;
        $this->assertStringContainsString('<user_input>', $userMessage);
        $this->assertStringContainsString('</user_input>', $userMessage);
        // CDATA で値を囲んでいる
        $this->assertStringContainsString('<![CDATA[', $userMessage);
    }


    // ---- プロンプトインジェクション対策 ----

    public function test_入力中の_XML_境界タグは全角化される(): void {
        $this->configure(true, ['name']);
        $client = $this->setFakeResponse(['is_spam' => false, 'score' => 0.1, 'reason' => 'x']);

        AISpamDetector::judge(['name' => SecurityPayloads::PROMPT_INJECTION['xml_close']]);
        $userMessage = $client->lastRequest->messages[0]->content;
        // 攻撃者が </user_input> を入れても、構造の </user_input> と区別される
        // mailform 自身が出力する 1 個の </user_input> しか含まれない
        $this->assertSame(1, substr_count($userMessage, '</user_input>'));
    }


    public function test_入力中の_CDATA_終端はエスケープされる(): void {
        $this->configure(true, ['name']);
        $client = $this->setFakeResponse(['is_spam' => false, 'score' => 0.1, 'reason' => 'x']);

        AISpamDetector::judge(['name' => SecurityPayloads::PROMPT_INJECTION['cdata_terminate']]);
        $userMessage = $client->lastRequest->messages[0]->content;
        // 入力中の ]]> は ]]&gt; にエスケープされ、構造の終端 ]]> 1 個のみ
        $this->assertSame(1, substr_count($userMessage, ']]>'));
    }


    public function test_モデル特殊トークンは無害化されてから送られる(): void {
        $this->configure(true, ['name']);
        $client = $this->setFakeResponse(['is_spam' => false, 'score' => 0.1, 'reason' => 'x']);

        AISpamDetector::judge(['name' => SecurityPayloads::PROMPT_INJECTION['openai_token']]);
        $userMessage = $client->lastRequest->messages[0]->content;
        $this->assertStringNotContainsString('<|im_start|>', $userMessage);
        $this->assertStringNotContainsString('<|im_end|>', $userMessage);
    }


    // ---- max_input_bytes ----

    public function test_max_input_bytes_を超えた入力は拒否される_block_モード(): void {
        AISpamDetector::configure(
            ['provider' => 'claude_cli'],
            [
                'enabled'         => true,
                'fields'          => ['name'],
                'fail_mode'       => 'block',
                'max_input_bytes' => 100,
                'cache_secret'    => str_repeat('a', 32),
            ]
        );
        $client = $this->setFakeResponse(['is_spam' => false, 'score' => 0.0, 'reason' => 'ok']);

        $j = AISpamDetector::judge(['name' => str_repeat('a', 200)]);
        $this->assertTrue($j->isSpam);  // block モードでスパム判定
        $this->assertSame(0, $client->sendCount);  // AI 呼ばれない
    }


    public function test_max_input_bytes_を超えた入力_allow_モードは通過(): void {
        AISpamDetector::configure(
            ['provider' => 'claude_cli'],
            [
                'enabled'         => true,
                'fields'          => ['name'],
                'fail_mode'       => 'allow',
                'max_input_bytes' => 100,
                'cache_secret'    => str_repeat('a', 32),
            ]
        );
        $client = $this->setFakeResponse(['is_spam' => false, 'score' => 0.0, 'reason' => 'ok']);

        $j = AISpamDetector::judge(['name' => str_repeat('a', 200)]);
        $this->assertFalse($j->isSpam);  // allow モードで通過
        $this->assertSame(0, $client->sendCount);  // AI 呼ばれない（早期拒否）
    }


    // ---- fail_mode ----

    public function test_fail_mode_block_は_AI_失敗時にスパム判定(): void {
        $this->configure(true, ['name'], false, 0.7, 'block');
        $client = new FakeAIClient();
        $client->fakeException = new AIClientException('network fail');
        AISpamDetector::setClientForTest($client);

        $j = AISpamDetector::judge(['name' => 'foo']);
        $this->assertTrue($j->isSpam);
        $this->assertSame(1.0, $j->score);
    }


    public function test_fail_mode_silent_block_は_スパム判定するが_reason_を曖昧化(): void {
        $this->configure(true, ['name'], false, 0.7, 'silent_block');
        $client = new FakeAIClient();
        $client->fakeException = new AIClientException('detailed network error');
        AISpamDetector::setClientForTest($client);

        $j = AISpamDetector::judge(['name' => 'foo']);
        $this->assertTrue($j->isSpam);
        $this->assertSame('rejected', $j->reason);  // 曖昧化されている
        $this->assertStringNotContainsString('detailed', $j->reason);
    }


    public function test_fail_mode_allow_は従来通り_Fail_Open(): void {
        $this->configure(true, ['name'], false, 0.7, 'allow');
        $client = new FakeAIClient();
        $client->fakeException = new AIClientException('network fail');
        AISpamDetector::setClientForTest($client);

        $j = AISpamDetector::judge(['name' => 'foo']);
        $this->assertFalse($j->isSpam);
    }


    public function test_jsonData_null_は_block_モードでスパム判定(): void {
        $this->configure(true, ['name'], false, 0.7, 'block');
        $client = new FakeAIClient();
        $client->fakeResponse = new AIResponse('not json', null);
        AISpamDetector::setClientForTest($client);

        $j = AISpamDetector::judge(['name' => 'foo']);
        $this->assertTrue($j->isSpam);
    }


    // ---- HMAC キャッシュキー ----

    public function test_異なる_cache_secret_は別キャッシュとして扱われる(): void {
        // secret A でキャッシュ
        AISpamDetector::configure(
            ['provider' => 'claude_cli'],
            [
                'enabled'      => true,
                'fields'       => ['name'],
                'cache'        => true,
                'cache_dir'    => $this->cacheDir,
                'cache_secret' => str_repeat('A', 32),
                'fail_mode'    => 'allow',
            ]
        );
        $client = $this->setFakeResponse(['is_spam' => false, 'score' => 0.1, 'reason' => 'a']);
        AISpamDetector::judge(['name' => 'hello']);
        $this->assertSame(1, $client->sendCount);

        // 同じ入力でも secret を変えれば再 fetch される
        AISpamDetector::configure(
            ['provider' => 'claude_cli'],
            [
                'enabled'      => true,
                'fields'       => ['name'],
                'cache'        => true,
                'cache_dir'    => $this->cacheDir,
                'cache_secret' => str_repeat('B', 32),
                'fail_mode'    => 'allow',
            ]
        );
        $client2 = $this->setFakeResponse(['is_spam' => false, 'score' => 0.1, 'reason' => 'b']);
        AISpamDetector::judge(['name' => 'hello']);
        $this->assertSame(1, $client2->sendCount);  // キャッシュヒットせず再 fetch
    }


    // ---- reason の切り詰め ----

    public function test_AI_応答の_reason_は_200_文字に切り詰められる(): void {
        $this->configure(true, ['name'], false, 0.7, 'allow');
        $longReason = str_repeat('あ', 500);
        $this->setFakeResponse(['is_spam' => false, 'score' => 0.1, 'reason' => $longReason]);

        $j = AISpamDetector::judge(['name' => 'foo']);
        $this->assertSame(200, mb_strlen($j->reason, 'UTF-8'));
    }


    // ---- dev_bypass ----

    public function test_dev_bypass_一致なら_AI判定をスキップ_cleanを返す(): void {
        $this->configure(true, ['message'], false, 0.7, 'block');
        $client = $this->setFakeResponse(['is_spam' => true, 'score' => 0.95, 'reason' => 'spam']);
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => ['ai_spam'],
            'match'   => ['email' => ['qa@example.com']],
            'expires_at' => '2099-12-31',
        ]);

        $j = AISpamDetector::judge(['message' => 'スパム的内容', 'email' => 'qa@example.com']);

        $this->assertFalse($j->isSpam);
        $this->assertSame('dev_bypass', $j->reason);
        $this->assertSame(0, $client->sendCount);  // AI に問い合わせていない
    }

    public function test_dev_bypass_不一致なら通常通り_AI判定が走る(): void {
        $this->configure(true, ['message'], false, 0.7, 'block');
        $client = $this->setFakeResponse(['is_spam' => false, 'score' => 0.1, 'reason' => 'ok']);
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => ['ai_spam'],
            'match'   => ['email' => ['qa@example.com']],
            'expires_at' => '2099-12-31',
        ]);

        AISpamDetector::judge(['message' => '通常のお問い合わせ', 'email' => 'random@example.com']);

        $this->assertSame(1, $client->sendCount);  // AI に問い合わせている
    }

}

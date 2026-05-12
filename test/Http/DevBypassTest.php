<?php

namespace AIJOH\Test\Http;

use AIJOH\Http\DevBypass;
use PHPUnit\Framework\TestCase;

/**
 * DevBypass の判定ロジックのテスト。
 * IP ホワイトリストの代替となる「特定の入力値でバイパス」機能を検証する。
 */
class DevBypassTest extends TestCase {

    protected function setUp() : void {
        DevBypass::reset();
    }

    protected function tearDown() : void {
        DevBypass::reset();
    }


    public function test_設定なしならバイパスしない() : void {
        $this->assertFalse(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }


    public function test_enabledがfalseならバイパスしない() : void {
        DevBypass::configure([
            'enabled' => false,
            'bypass'  => [ 'rate_limit' ],
            'match'   => [ 'email' => [ 'qa@example.com' ] ],
        ]);
        $this->assertFalse(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }


    public function test_リスト一致でバイパス() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],
            'match'   => [ 'email' => [ 'qa@example.com' ] ],
        ]);
        $this->assertTrue(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }


    public function test_リスト不一致ならバイパスしない() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],
            'match'   => [ 'email' => [ 'qa@example.com' ] ],
        ]);
        $this->assertFalse(DevBypass::shouldBypass('rate_limit', [ 'email' => 'random@example.com' ]));
    }


    public function test_リスト内の複数値のうちいずれか一致でバイパス() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],
            'match'   => [ 'email' => [ 'qa@example.com', 'staff@example.com' ] ],
        ]);
        $this->assertTrue(DevBypass::shouldBypass('rate_limit', [ 'email' => 'staff@example.com' ]));
    }


    public function test_複数フィールドのいずれか一致でバイパス() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],
            'match'   => [
                'email' => [ 'qa@example.com' ],
                'name'  => [ 'TEST_USER' ],
            ],
        ]);
        $this->assertTrue(DevBypass::shouldBypass('rate_limit', [
            'email' => 'someone@else.com',
            'name'  => 'TEST_USER',
        ]));
    }


    public function test_リストではなく単一値の文字列でも一致() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],
            'match'   => [ 'email' => 'qa@example.com' ],  // 配列でなく文字列
        ]);
        $this->assertTrue(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }


    public function test_Closure一致でバイパス() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],
            'match'   => fn( array $post ) : bool => ( $post['email'] ?? '' ) === 'qa@example.com',
        ]);
        $this->assertTrue(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }


    public function test_Closure不一致でバイパスしない() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],
            'match'   => fn( array $post ) : bool => false,
        ]);
        $this->assertFalse(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }


    public function test_bypassに含まれない層はバイパスしない() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],  // ai_spam は含まない
            'match'   => [ 'email' => [ 'qa@example.com' ] ],
        ]);
        $this->assertTrue(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
        $this->assertFalse(DevBypass::shouldBypass('ai_spam', [ 'email' => 'qa@example.com' ]));
    }


    public function test_bypassに両方の層を指定すれば両方バイパス() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit', 'ai_spam' ],
            'match'   => [ 'email' => [ 'qa@example.com' ] ],
        ]);
        $data = [ 'email' => 'qa@example.com' ];
        $this->assertTrue(DevBypass::shouldBypass('rate_limit', $data));
        $this->assertTrue(DevBypass::shouldBypass('ai_spam', $data));
    }


    public function test_expires_at過去ならバイパスしない() : void {
        DevBypass::configure([
            'enabled'    => true,
            'bypass'     => [ 'rate_limit' ],
            'match'      => [ 'email' => [ 'qa@example.com' ] ],
            'expires_at' => '2000-01-01',
        ]);
        $this->assertFalse(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }


    public function test_expires_at未来ならバイパスする() : void {
        DevBypass::configure([
            'enabled'    => true,
            'bypass'     => [ 'rate_limit' ],
            'match'      => [ 'email' => [ 'qa@example.com' ] ],
            'expires_at' => '2099-12-31',
        ]);
        $this->assertTrue(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }


    public function test_expires_at_解析不能ならバイパスしない() : void {
        DevBypass::configure([
            'enabled'    => true,
            'bypass'     => [ 'rate_limit' ],
            'match'      => [ 'email' => [ 'qa@example.com' ] ],
            'expires_at' => 'not-a-date',
        ]);
        $this->assertFalse(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }


    public function test_配列値はHPP対策で一致しない() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],
            'match'   => [ 'email' => [ 'qa@example.com' ] ],
        ]);
        // 攻撃者が email[]=qa@example.com で送ってきた場合
        $this->assertFalse(DevBypass::shouldBypass('rate_limit', [ 'email' => [ 'qa@example.com' ] ]));
    }


    public function test_空文字列のexpectedはスキップされる_誤一致防止() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],
            'match'   => [ 'email' => [ '', 'qa@example.com' ] ],
        ]);
        // 未入力フィールドが空文字列の expected と誤一致しない
        $this->assertFalse(DevBypass::shouldBypass('rate_limit', [ 'email' => '' ]));
        // 正規の一致は通る
        $this->assertTrue(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }


    public function test_matchが未設定ならバイパスしない() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],
            // match なし
        ]);
        $this->assertFalse(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }


    public function test_disableで全てバイパスしない() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],
            'match'   => [ 'email' => [ 'qa@example.com' ] ],
        ]);
        DevBypass::disable();
        $this->assertFalse(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }


    public function test_resetで設定がクリアされる() : void {
        DevBypass::configure([
            'enabled' => true,
            'bypass'  => [ 'rate_limit' ],
            'match'   => [ 'email' => [ 'qa@example.com' ] ],
        ]);
        DevBypass::reset();
        $this->assertFalse(DevBypass::shouldBypass('rate_limit', [ 'email' => 'qa@example.com' ]));
    }
}

<?php

namespace AIJOH\Test\Http;

use AIJOH\Http\Post;
use PHPUnit\Framework\TestCase;

/**
 * HPP (HTTP Parameter Pollution) 対策のテスト。
 * Post::getString() は配列を渡されても string として扱う。
 */
class PostHppTest extends TestCase {

    protected function setUp() : void {
        Post::reset();
    }

    protected function tearDown() : void {
        Post::reset();
    }


    public function test_getString_文字列値はそのまま返す() : void {
        Post::setForTest([ 'name' => '田中' ]);
        $this->assertSame('田中', Post::getInstance()->getString('name'));
    }


    public function test_getString_未設定キーはdefault() : void {
        Post::setForTest([]);
        $this->assertSame('', Post::getInstance()->getString('name'));
        $this->assertSame('fallback', Post::getInstance()->getString('name', 'fallback'));
    }


    public function test_getString_配列値は_default_を返す_HPP対策() : void {
        // 攻撃者が name=A&name[]=B のように送って配列で来た場合
        Post::setForTest([ 'name' => [ 'A', 'B' ] ]);
        $this->assertSame('', Post::getInstance()->getString('name'));
        $this->assertSame('fallback', Post::getInstance()->getString('name', 'fallback'));
    }


    public function test_getString_数値は_default_を返す() : void {
        Post::setForTest([ 'count' => 5 ]);
        // string でないので default 返す（Post::get() なら 5 返す）
        $this->assertSame('', Post::getInstance()->getString('count'));
    }


    public function test_get_は従来通り_配列を返す() : void {
        Post::setForTest([ 'name' => [ 'A', 'B' ] ]);
        $this->assertSame([ 'A', 'B' ], Post::getInstance()->get('name'));
    }
}

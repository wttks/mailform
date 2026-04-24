<?php

namespace AIJOH\Test\Util;

use AIJOH\Util\ObjectUtil;
use PHPUnit\Framework\TestCase;

/**
 * ObjectUtil のテスト
 */
class ObjectUtilTest extends TestCase {

    // ---- isEmpty() ----

    public function test_isEmpty_nullはtrue(): void {
        $this->assertTrue(ObjectUtil::isEmpty(null));
    }

    public function test_isEmpty_空文字はtrue(): void {
        $this->assertTrue(ObjectUtil::isEmpty(''));
    }

    public function test_isEmpty_空配列はtrue(): void {
        $this->assertTrue(ObjectUtil::isEmpty([]));
    }

    public function test_isEmpty_空オブジェクトはtrue(): void {
        $this->assertTrue(ObjectUtil::isEmpty(new \stdClass()));
    }

    public function test_isEmpty_0はfalse(): void {
        $this->assertFalse(ObjectUtil::isEmpty(0));
    }

    public function test_isEmpty_0_0はfalse(): void {
        $this->assertFalse(ObjectUtil::isEmpty(0.0));
    }

    public function test_isEmpty_falseはfalse(): void {
        $this->assertFalse(ObjectUtil::isEmpty(false));
    }

    public function test_isEmpty_trueはfalse(): void {
        $this->assertFalse(ObjectUtil::isEmpty(true));
    }

    public function test_isEmpty_文字列0はfalse(): void {
        $this->assertFalse(ObjectUtil::isEmpty('0'));
    }

    public function test_isEmpty_スペースのみの文字列はfalse(): void {
        // 空文字以外の文字列はfalse（スペースは空文字ではない）
        $this->assertFalse(ObjectUtil::isEmpty(' '));
    }

    public function test_isEmpty_値が入っている配列はfalse(): void {
        $this->assertFalse(ObjectUtil::isEmpty([1, 2, 3]));
    }

    public function test_isEmpty_プロパティを持つオブジェクトはfalse(): void {
        $obj = new \stdClass();
        $obj->name = 'test';
        $this->assertFalse(ObjectUtil::isEmpty($obj));
    }

    public function test_isEmpty_ネストした空配列の配列はtrue(): void {
        // ArrayUtil::isEmpty が再帰的に判定するため
        $this->assertTrue(ObjectUtil::isEmpty([[], [null, '']]));
    }
}

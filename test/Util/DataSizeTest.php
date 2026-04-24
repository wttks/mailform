<?php

namespace AIJOH\Test\Util;

use AIJOH\Util\DataSize;
use PHPUnit\Framework\TestCase;

/**
 * DataSize のテスト
 */
class DataSizeTest extends TestCase {

    // ---- 正常系: 単位なし ----

    public function test_toByte_数値文字列をそのままバイトとして返す(): void {
        $this->assertSame(1024, DataSize::toByte('1024'));
    }

    // ---- 正常系: K/M/G 単位 ----

    public function test_toByte_KB単位を変換する(): void {
        $this->assertSame(1000, DataSize::toByte('1K'));
    }

    public function test_toByte_KBをBサフィックス付きで変換する(): void {
        $this->assertSame(1000, DataSize::toByte('1KB'));
    }

    public function test_toByte_MB単位を変換する(): void {
        $this->assertSame(1000000, DataSize::toByte('1M'));
    }

    public function test_toByte_MBをBサフィックス付きで変換する(): void {
        $this->assertSame(1000000, DataSize::toByte('1MB'));
    }

    public function test_toByte_GB単位を変換する(): void {
        $this->assertSame(1000000000, DataSize::toByte('1G'));
    }

    public function test_toByte_小文字でも変換できる(): void {
        $this->assertSame(1000, DataSize::toByte('1k'));
    }

    public function test_toByte_小文字mbでも変換できる(): void {
        $this->assertSame(1000000, DataSize::toByte('1mb'));
    }

    public function test_toByte_2KBは2000バイト(): void {
        $this->assertSame(2000, DataSize::toByte('2K'));
    }

    // ---- 小数値 ----

    public function test_toByte_05MBは500000バイト(): void {
        $this->assertSame(500000, DataSize::toByte('0.5M'));
    }

    // ---- 異常系 ----

    public function test_toByte_不正な文字列は例外を投げる(): void {
        $this->expectException(\InvalidArgumentException::class);
        DataSize::toByte('invalid');
    }

    public function test_toByte_単位だけは0バイトを返す(): void {
        // 'K' → $unit='K'（単位リストにある）, $value='' → (float)'' = 0
        // 例外ではなく 0 * 1000 = 0 が返る仕様
        $this->assertSame(0, DataSize::toByte('K'));
    }
}

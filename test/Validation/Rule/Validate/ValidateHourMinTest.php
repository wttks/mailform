<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateHourMin;
use PHPUnit\Framework\TestCase;

/**
 * ValidateHourMin のテスト
 * パターン: /\A([0-1]?[0-9]|2[0-3]):[0-5]?[0-9]\z/
 * 0:00 ～ 23:59 の HH:MM 形式（時間部分は先頭0省略可）
 */
class ValidateHourMinTest extends TestCase {

    private ValidateHourMin $rule;

    protected function setUp(): void {
        $this->rule = new ValidateHourMin();
    }

    // ---- 正常系 ----

    public function test_ゼロ時はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('0:00'));
    }

    public function test_23時59分はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('23:59'));
    }

    public function test_09_00はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('09:00'));
    }

    public function test_12_30はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('12:30'));
    }

    public function test_先頭ゼロなし9_00はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('9:00'));
    }

    public function test_分の先頭ゼロ省略9_5はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('9:5'));
    }

    // ---- 境界テスト ----

    public function test_23時00分はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('23:00'));
    }

    public function test_24時00分はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('24:00'));
    }

    public function test_00_59はtrueを返す(): void {
        $this->assertTrue($this->rule->validate('00:59'));
    }

    public function test_00_60はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('00:60'));
    }

    // ---- 空値スキップ ----

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(''));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->rule->validate(null));
    }

    // ---- 配列入力 ----

    public function test_有効な時刻の配列はtrueを返す(): void {
        $this->assertTrue($this->rule->validate(['9:00', '18:30']));
    }

    public function test_無効な要素を含む配列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(['9:00', '25:00']));
    }

    // ---- 異常系 ----

    public function test_コロンなし文字列はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('1200'));
    }

    public function test_整数はfalseを返す(): void {
        $this->assertFalse($this->rule->validate(1200));
    }

    public function test_日時形式はfalseを返す(): void {
        $this->assertFalse($this->rule->validate('12:30:00'));
    }

    // ---- エラーメッセージ ----

    public function test_エラーメッセージが正しい形式である(): void {
        $this->assertStringContainsString(':title', $this->rule->getErrorMessage());
    }
}

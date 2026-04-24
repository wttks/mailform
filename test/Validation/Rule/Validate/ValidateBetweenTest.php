<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateBetween;
use PHPUnit\Framework\TestCase;

/**
 * ValidateBetween のテスト
 * args[0]=min, args[1]=max
 * min <= value <= max の場合 true
 */
class ValidateBetweenTest extends TestCase {

    private ValidateBetween $rule;

    private function check(mixed $value, array $args): bool {
        return $this->rule->validate($value, $args);
    }

    protected function setUp(): void {
        $this->rule = new ValidateBetween();
    }

    // ==============================
    // 数値チェック
    // ==============================

    public function test_数値が範囲内はtrueを返す(): void {
        $this->assertTrue($this->check(5, [1, 10]));
    }

    public function test_数値が最小値と等しい場合はtrueを返す(): void {
        $this->assertTrue($this->check(1, [1, 10]));
    }

    public function test_数値が最大値と等しい場合はtrueを返す(): void {
        $this->assertTrue($this->check(10, [1, 10]));
    }

    public function test_数値が最小値より小さい場合はfalseを返す(): void {
        $this->assertFalse($this->check(0, [1, 10]));
    }

    public function test_数値が最大値より大きい場合はfalseを返す(): void {
        $this->assertFalse($this->check(11, [1, 10]));
    }

    public function test_数値境界_最小値マイナス1はfalse(): void {
        $this->assertFalse($this->check(0, [1, 10]));
    }

    public function test_数値境界_最大値プラス1はfalse(): void {
        $this->assertFalse($this->check(11, [1, 10]));
    }

    // ==============================
    // 文字列チェック（文字数 min <= len <= max）
    // ==============================

    public function test_文字数が範囲内はtrueを返す(): void {
        $this->assertTrue($this->check('abc', [2, 5]));
    }

    public function test_文字数が最小値と等しい場合はtrueを返す(): void {
        $this->assertTrue($this->check('ab', [2, 5]));
    }

    public function test_文字数が最大値と等しい場合はtrueを返す(): void {
        $this->assertTrue($this->check('abcde', [2, 5]));
    }

    public function test_文字数が最小値より少ない場合はfalseを返す(): void {
        $this->assertFalse($this->check('a', [2, 5]));
    }

    public function test_文字数が最大値より多い場合はfalseを返す(): void {
        $this->assertFalse($this->check('abcdef', [2, 5]));
    }

    public function test_日本語文字列の範囲チェック(): void {
        $this->assertTrue($this->check('あいう', [2, 5]));
        $this->assertFalse($this->check('あ', [2, 5]));
        $this->assertFalse($this->check('あいうえおか', [2, 5]));
    }

    // ==============================
    // 配列チェック
    // ==============================

    public function test_配列要素数が範囲内はtrueを返す(): void {
        $this->assertTrue($this->check(['a', 'b', 'c'], [2, 4]));
    }

    public function test_配列要素数が最小値と等しい場合はtrueを返す(): void {
        $this->assertTrue($this->check(['a', 'b'], [2, 4]));
    }

    public function test_配列要素数が最大値と等しい場合はtrueを返す(): void {
        $this->assertTrue($this->check(['a', 'b', 'c', 'd'], [2, 4]));
    }

    public function test_配列要素数が最小値より少ない場合はfalseを返す(): void {
        $this->assertFalse($this->check(['a'], [2, 4]));
    }

    public function test_配列要素数が最大値より多い場合はfalseを返す(): void {
        $this->assertFalse($this->check(['a', 'b', 'c', 'd', 'e'], [2, 4]));
    }

    // ==============================
    // DateTimeImmutable チェック（日付範囲）
    // ==============================

    public function test_日付が範囲内はtrueを返す(): void {
        $date = new \DateTimeImmutable('2024-01-15');
        $this->assertTrue($this->check($date, ['2024-01-01', '2024-01-31']));
    }

    public function test_日付が最小値と等しい場合はtrueを返す(): void {
        $date = new \DateTimeImmutable('2024-01-01');
        $this->assertTrue($this->check($date, ['2024-01-01', '2024-01-31']));
    }

    public function test_日付が最大値と等しい場合はtrueを返す(): void {
        $date = new \DateTimeImmutable('2024-01-31');
        $this->assertTrue($this->check($date, ['2024-01-01', '2024-01-31']));
    }

    public function test_日付が範囲外はfalseを返す(): void {
        $date = new \DateTimeImmutable('2024-02-01');
        $this->assertFalse($this->check($date, ['2024-01-01', '2024-01-31']));
    }

    // ==============================
    // 空値スキップ
    // ==============================

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->check('', [2, 5]));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->check(null, [2, 5]));
    }

    // ==============================
    // エラーメッセージ
    // ==============================

    public function test_数値チェック後のエラーメッセージ(): void {
        $this->rule->validate(0, [1, 10]);
        $msg = $this->rule->getErrorMessage();
        $this->assertStringContainsString(':title', $msg);
        $this->assertStringContainsString(':min', $msg);
        $this->assertStringContainsString(':max', $msg);
    }
}

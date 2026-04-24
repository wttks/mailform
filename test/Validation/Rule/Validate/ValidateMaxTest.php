<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateMax;
use PHPUnit\Framework\TestCase;

/**
 * ValidateMax のテスト
 *
 * judgeResults: $result >= 0 → threshold >= value なら true (value <= threshold)
 */
class ValidateMaxTest extends TestCase {

    private ValidateMax $rule;

    private function check(mixed $value, array $args): bool {
        return $this->rule->validate($value, $args);
    }

    protected function setUp(): void {
        $this->rule = new ValidateMax();
    }

    // ==============================
    // 数値チェック
    // ==============================

    public function test_数値が最大値と等しい場合はtrueを返す(): void {
        $this->assertTrue($this->check(10, [10]));
    }

    public function test_数値が最大値より小さい場合はtrueを返す(): void {
        $this->assertTrue($this->check(9, [10]));
    }

    public function test_数値が最大値より大きい場合はfalseを返す(): void {
        $this->assertFalse($this->check(11, [10]));
    }

    public function test_数値文字列の最大値チェック(): void {
        $this->assertTrue($this->check('10', [10]));
        $this->assertFalse($this->check('11', [10]));
    }

    public function test_境界値ピッタリのfloat(): void {
        $this->assertTrue($this->check(10.0, [10]));
    }

    public function test_境界値を僅かに超えるfloat(): void {
        // 10.0000000001 > 10 なので false
        $this->assertFalse($this->check(10.0000000001, [10]));
    }

    // ==============================
    // 文字列チェック（文字数 <= threshold）
    // ==============================

    public function test_文字数が最大値と等しい場合はtrueを返す(): void {
        $this->assertTrue($this->check('abcde', [5]));
    }

    public function test_文字数が最大値より少ない場合はtrueを返す(): void {
        $this->assertTrue($this->check('abcd', [5]));
    }

    public function test_文字数が最大値より多い場合はfalseを返す(): void {
        $this->assertFalse($this->check('abcdef', [5]));
    }

    public function test_日本語文字列の文字数チェック(): void {
        $this->assertTrue($this->check('あいう', [3]));
        $this->assertFalse($this->check('あいうえ', [3]));
    }

    // ==============================
    // 配列チェック
    // ==============================

    public function test_配列要素数が最大値と等しい場合はtrueを返す(): void {
        $this->assertTrue($this->check(['a', 'b', 'c'], [3]));
    }

    public function test_配列要素数が最大値より少ない場合はtrueを返す(): void {
        $this->assertTrue($this->check(['a', 'b'], [3]));
    }

    public function test_配列要素数が最大値より多い場合はfalseを返す(): void {
        $this->assertFalse($this->check(['a', 'b', 'c', 'd'], [3]));
    }

    // ==============================
    // DateTimeImmutable チェック
    // ==============================

    public function test_日付が最大値と等しい場合はtrueを返す(): void {
        $date = new \DateTimeImmutable('2024-01-15');
        $this->assertTrue($this->check($date, ['2024-01-15']));
    }

    public function test_日付が最大値より前の場合はtrueを返す(): void {
        $date = new \DateTimeImmutable('2024-01-14');
        $this->assertTrue($this->check($date, ['2024-01-15']));
    }

    public function test_日付が最大値より後の場合はfalseを返す(): void {
        $date = new \DateTimeImmutable('2024-01-16');
        $this->assertFalse($this->check($date, ['2024-01-15']));
    }

    // ==============================
    // 空値スキップ
    // ==============================

    public function test_空文字はスキップでtrueを返す(): void {
        $this->assertTrue($this->check('', [5]));
    }

    public function test_nullはスキップでtrueを返す(): void {
        $this->assertTrue($this->check(null, [5]));
    }

    // ==============================
    // エラーメッセージ
    // ==============================

    public function test_文字列チェック後のエラーメッセージ(): void {
        $this->rule->validate('abcdef', [5]);
        $msg = $this->rule->getErrorMessage();
        $this->assertStringContainsString(':title', $msg);
        $this->assertStringContainsString(':max', $msg);
    }
}

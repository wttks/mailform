<?php

namespace AIJOH\Test\Validation\Rule\Validate;

use AIJOH\Validation\Rule\Validate\ValidateMin;
use AIJOH\Validation\Validator\Validator;
use PHPUnit\Framework\TestCase;

/**
 * ValidateMin のテスト
 *
 * Validator がない場合は値の型で自動判定:
 *   - is_numeric → number チェック (threshold <=> value で 0 以下なら OK = value >= threshold)
 *   - is_string  → string チェック (threshold <=> mb_strlen で 0 以下なら OK = 文字数 >= threshold)
 *   - UploadFile → file チェック
 *   - DateTimeImmutable → date チェック
 *   - array → array チェック (count)
 *
 * judgeResults: $result <= 0 → threshold <= value なら true
 */
class ValidateMinTest extends TestCase {

    private ValidateMin $rule;

    /** Validator なしで validator=null を使う */
    private function check(mixed $value, array $args): bool {
        return $this->rule->validate($value, $args);
    }

    protected function setUp(): void {
        $this->rule = new ValidateMin();
    }

    // ==============================
    // 数値チェック
    // ==============================

    public function test_数値が最小値と等しい場合はtrueを返す(): void {
        $this->assertTrue($this->check(5, [5]));
    }

    public function test_数値が最小値より大きい場合はtrueを返す(): void {
        $this->assertTrue($this->check(6, [5]));
    }

    public function test_数値が最小値より小さい場合はfalseを返す(): void {
        $this->assertFalse($this->check(4, [5]));
    }

    public function test_数値文字列の最小値チェック(): void {
        $this->assertTrue($this->check('10', [10]));
        $this->assertFalse($this->check('9', [10]));
    }

    public function test_数値の小数点境界_最小値ピッタリ(): void {
        $this->assertTrue($this->check(5.0, [5]));
    }

    public function test_数値の境界_最小値より僅かに小さい(): void {
        // 4.9999 < 5 なので false
        $this->assertFalse($this->check(4.9999999999, [5]));
    }

    public function test_負の最小値チェック(): void {
        $this->assertTrue($this->check(-1, [-2]));
        $this->assertFalse($this->check(-3, [-2]));
    }

    // ==============================
    // 文字列チェック（文字数 >= threshold）
    // ==============================

    public function test_文字数が最小値と等しい場合はtrueを返す(): void {
        // "abc" = 3文字, min=3
        $this->assertTrue($this->check('abc', [3]));
    }

    public function test_文字数が最小値より多い場合はtrueを返す(): void {
        $this->assertTrue($this->check('abcd', [3]));
    }

    public function test_文字数が最小値より少ない場合はfalseを返す(): void {
        $this->assertFalse($this->check('ab', [3]));
    }

    public function test_日本語文字列の文字数チェック(): void {
        // "あいう" = 3文字
        $this->assertTrue($this->check('あいう', [3]));
        $this->assertFalse($this->check('あい', [3]));
    }

    // ==============================
    // 配列チェック（count >= threshold）
    // ==============================

    public function test_配列要素数が最小値と等しい場合はtrueを返す(): void {
        $this->assertTrue($this->check(['a', 'b', 'c'], [3]));
    }

    public function test_配列要素数が最小値より多い場合はtrueを返す(): void {
        $this->assertTrue($this->check(['a', 'b', 'c', 'd'], [3]));
    }

    public function test_配列要素数が最小値より少ない場合はfalseを返す(): void {
        $this->assertFalse($this->check(['a', 'b'], [3]));
    }

    // ==============================
    // DateTimeImmutable チェック（日付 >= threshold）
    // ==============================

    public function test_日付が最小値と等しい場合はtrueを返す(): void {
        $date = new \DateTimeImmutable('2024-01-15');
        $this->assertTrue($this->check($date, ['2024-01-15']));
    }

    public function test_日付が最小値より後の場合はtrueを返す(): void {
        $date = new \DateTimeImmutable('2024-01-16');
        $this->assertTrue($this->check($date, ['2024-01-15']));
    }

    public function test_日付が最小値より前の場合はfalseを返す(): void {
        $date = new \DateTimeImmutable('2024-01-14');
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
    // エラーメッセージ（type セット後）
    // ==============================

    public function test_数値チェック後のエラーメッセージ(): void {
        $this->rule->validate(4, [5]);
        $msg = $this->rule->getErrorMessage();
        $this->assertStringContainsString(':title', $msg);
        $this->assertStringContainsString(':min', $msg);
    }
}

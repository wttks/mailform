<?php

namespace AIJOH\Test\Validation\Rule\Format;

use AIJOH\Validation\Rule\Format\FormatBoolean;
use PHPUnit\Framework\TestCase;

class FormatBooleanTest extends TestCase {

    private FormatBoolean $formatter;

    protected function setUp() : void {
        $this->formatter = new FormatBoolean();
    }

    // ==============================
    // true に変換される値
    // ==============================

    public function test_trueはtrueを返す() : void {
        $this->assertSame(true, $this->formatter->format(true));
    }

    public function test_1はtrueを返す() : void {
        $this->assertSame(true, $this->formatter->format(1));
    }

    public function test_文字列trueはtrueを返す() : void {
        $this->assertSame(true, $this->formatter->format('true'));
    }

    public function test_文字列1はtrueを返す() : void {
        $this->assertSame(true, $this->formatter->format('1'));
    }

    public function test_文字列yesはtrueを返す() : void {
        $this->assertSame(true, $this->formatter->format('yes'));
    }

    public function test_文字列onはtrueを返す() : void {
        $this->assertSame(true, $this->formatter->format('on'));
    }

    public function test_文字列yはtrueを返す() : void {
        $this->assertSame(true, $this->formatter->format('y'));
    }

    public function test_文字列tはtrueを返す() : void {
        $this->assertSame(true, $this->formatter->format('t'));
    }

    // ==============================
    // false に変換される値
    // ==============================

    public function test_falseはfalseを返す() : void {
        $this->assertSame(false, $this->formatter->format(false));
    }

    public function test_0はfalseを返す() : void {
        $this->assertSame(false, $this->formatter->format(0));
    }

    public function test_文字列falseはfalseを返す() : void {
        $this->assertSame(false, $this->formatter->format('false'));
    }

    public function test_文字列0はfalseを返す() : void {
        $this->assertSame(false, $this->formatter->format('0'));
    }

    public function test_文字列noはfalseを返す() : void {
        $this->assertSame(false, $this->formatter->format('no'));
    }

    public function test_文字列offはfalseを返す() : void {
        $this->assertSame(false, $this->formatter->format('off'));
    }

    public function test_文字列nはfalseを返す() : void {
        $this->assertSame(false, $this->formatter->format('n'));
    }

    public function test_文字列fはfalseを返す() : void {
        $this->assertSame(false, $this->formatter->format('f'));
    }

    // ==============================
    // 変換対象外の値（そのまま返る）
    // ==============================

    public function test_マッチしない文字列はそのまま返る() : void {
        $this->assertSame('unknown', $this->formatter->format('unknown'));
    }

    // ==============================
    // 配列の変換（バグ修正の確認）
    // ==============================

    public function test_配列の各要素を変換する() : void {
        $input    = ['true', '1', 'false', '0'];
        $expected = [true, true, false, false];
        $this->assertSame($expected, $this->formatter->format($input));
    }

    public function test_配列変換結果が空にならない() : void {
        // 修正前は $results[$key] ではなく $value[$key] に代入していたため
        // 常に空配列が返っていたバグの確認
        $result = $this->formatter->format(['true', 'false']);
        $this->assertNotEmpty($result);
    }

    public function test_連想配列の各要素を変換する() : void {
        $input    = ['flag1' => 'true', 'flag2' => '0', 'flag3' => 'yes'];
        $expected = ['flag1' => true, 'flag2' => false, 'flag3' => true];
        $this->assertSame($expected, $this->formatter->format($input));
    }

    public function test_ネストした配列の各要素を変換する() : void {
        $input    = [['true', 'false'], ['1', '0']];
        $expected = [[true, false], [true, false]];
        $this->assertSame($expected, $this->formatter->format($input));
    }

    // ==============================
    // 厳密な型チェック
    // ==============================

    public function test_strictモードで文字列の2はマッチしない() : void {
        // '2' は trueValues にも falseValues にも含まれないため元の値をそのまま返す
        $this->assertSame('2', $this->formatter->format('2'));
    }

    public function test_strictモードで数値の2はマッチしない() : void {
        $this->assertSame(2, $this->formatter->format(2));
    }
}

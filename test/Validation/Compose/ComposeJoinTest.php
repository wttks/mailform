<?php

namespace AIJOH\Test\Validation\Compose;

use AIJOH\Validation\Compose\ComposeJoin;
use PHPUnit\Framework\TestCase;

/**
 * ComposeJoin のテスト
 */
class ComposeJoinTest extends TestCase {

    // ---- getSourceFields() ----

    public function test_getSourceFields_元フィールド名を返す(): void {
        $compose = new ComposeJoin(['last_name', 'first_name'], ' ');
        $this->assertSame(['last_name', 'first_name'], $compose->getSourceFields());
    }

    // ---- apply() 基本 ----

    public function test_apply_区切り文字なしで連結(): void {
        $compose = new ComposeJoin(['prefecture', 'city', 'address1']);
        $result = $compose->apply([
            'prefecture' => '東京都',
            'city'       => '千代田区',
            'address1'   => '丸の内1-1',
        ]);
        $this->assertSame('東京都千代田区丸の内1-1', $result);
    }

    public function test_apply_スペース区切りで連結_氏名(): void {
        $compose = new ComposeJoin(['last_name', 'first_name'], ' ');
        $result = $compose->apply(['last_name' => '山田', 'first_name' => '太郎']);
        $this->assertSame('山田 太郎', $result);
    }

    public function test_apply_ハイフン区切りで連結_電話番号(): void {
        $compose = new ComposeJoin(['tel1', 'tel2', 'tel3'], '-');
        $result = $compose->apply(['tel1' => '090', 'tel2' => '1234', 'tel3' => '5678']);
        $this->assertSame('090-1234-5678', $result);
    }

    // ---- apply() ゼロパディング ----

    public function test_apply_ゼロパディング_日付(): void {
        $compose = new ComposeJoin(['y', 'm', 'd'], '-', [4, 2, 2]);
        $result = $compose->apply(['y' => '1990', 'm' => '1', 'd' => '5']);
        $this->assertSame('1990-01-05', $result);
    }

    public function test_apply_ゼロパディング_時刻(): void {
        $compose = new ComposeJoin(['h', 'm'], ':', [2, 2]);
        $result = $compose->apply(['h' => '9', 'm' => '5']);
        $this->assertSame('09:05', $result);
    }

    public function test_apply_pad_0_の要素はパディングしない(): void {
        $compose = new ComposeJoin(['a', 'b'], '/', [0, 3]);
        $result = $compose->apply(['a' => '1', 'b' => '7']);
        $this->assertSame('1/007', $result);
    }

    // ---- apply() 空値時 ----

    public function test_apply_いずれかが空文字なら_null(): void {
        $compose = new ComposeJoin(['y', 'm', 'd'], '-', [4, 2, 2]);
        $result = $compose->apply(['y' => '1990', 'm' => '', 'd' => '5']);
        $this->assertNull($result);
    }

    public function test_apply_いずれかが未定義なら_null(): void {
        $compose = new ComposeJoin(['y', 'm', 'd'], '-', [4, 2, 2]);
        $result = $compose->apply(['y' => '1990', 'd' => '5']);  // m なし
        $this->assertNull($result);
    }

    public function test_apply_いずれかが_null_なら_null(): void {
        $compose = new ComposeJoin(['a', 'b']);
        $result = $compose->apply(['a' => 'x', 'b' => null]);
        $this->assertNull($result);
    }

    // ---- apply() 文字列以外 ----

    public function test_apply_数値も文字列化して連結(): void {
        $compose = new ComposeJoin(['a', 'b'], '-');
        $result = $compose->apply(['a' => 100, 'b' => 200]);
        $this->assertSame('100-200', $result);
    }

}

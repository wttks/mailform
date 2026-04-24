<?php

namespace AIJOH\Test\Validation\Compose;

use AIJOH\Validation\Compose\ComposeFactory;
use AIJOH\Validation\Compose\ComposeJoin;
use PHPUnit\Framework\TestCase;

/**
 * ComposeFactory のテスト
 */
class ComposeFactoryTest extends TestCase {

    public function test_create_join_タイプから_ComposeJoin_を生成(): void {
        $compose = ComposeFactory::create([
            'join'      => ['last_name', 'first_name'],
            'separator' => ' ',
        ]);
        $this->assertInstanceOf(ComposeJoin::class, $compose);
        $this->assertSame(['last_name', 'first_name'], $compose->getSourceFields());
        $this->assertSame('山田 太郎', $compose->apply(['last_name' => '山田', 'first_name' => '太郎']));
    }

    public function test_create_join_separator_未指定なら空文字(): void {
        $compose = ComposeFactory::create(['join' => ['a', 'b', 'c']]);
        $this->assertSame('XYZ', $compose->apply(['a' => 'X', 'b' => 'Y', 'c' => 'Z']));
    }

    public function test_create_join_pad_指定で_ゼロパディング(): void {
        $compose = ComposeFactory::create([
            'join'      => ['y', 'm', 'd'],
            'separator' => '-',
            'pad'       => [4, 2, 2],
        ]);
        $this->assertSame('1990-01-05', $compose->apply(['y' => '1990', 'm' => '1', 'd' => '5']));
    }

    public function test_create_空配列で例外(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('compose 設定が空です。');
        ComposeFactory::create([]);
    }

    public function test_create_不明なタイプで例外(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('不明な compose タイプ: unknown_type');
        ComposeFactory::create(['unknown_type' => ['a', 'b']]);
    }

    public function test_create_元フィールドが配列でなければ例外(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('compose の元フィールドは配列で指定してください。');
        ComposeFactory::create(['join' => 'not_an_array']);
    }

}

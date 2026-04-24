<?php

namespace AIJOH\Test\Util;

use AIJOH\Util\ArrayUtil;
use PHPUnit\Framework\TestCase;

/**
 * ArrayUtil のテスト
 */
class ArrayUtilTest extends TestCase {

    // ---- get() ----

    public function test_get_単純なキーで値を取得できる(): void {
        $arr = ['a' => 1, 'b' => 2];
        $this->assertSame(1, ArrayUtil::get($arr, 'a'));
    }

    public function test_get_存在しないキーはデフォルト値を返す(): void {
        $arr = ['a' => 1];
        $this->assertNull(ArrayUtil::get($arr, 'z'));
    }

    public function test_get_存在しないキーはデフォルト値を指定できる(): void {
        $arr = ['a' => 1];
        $this->assertSame('default', ArrayUtil::get($arr, 'z', 'default'));
    }

    public function test_get_ドット区切りで多次元配列の値を取得できる(): void {
        $arr = ['a' => ['b' => 'val']];
        $this->assertSame('val', ArrayUtil::get($arr, 'a.b'));
    }

    public function test_get_ワイルドカードで複数の値を配列で返す(): void {
        $arr = [['a' => 'x'], ['a' => 'y']];
        $result = ArrayUtil::get($arr, '*.a');
        $this->assertSame(['x', 'y'], $result);
    }

    public function test_get_ワイルドカードで1件の場合は値をそのまま返す(): void {
        $arr = ['foo' => ['a' => 'x']];
        $result = ArrayUtil::get($arr, 'foo.a');
        $this->assertSame('x', $result);
    }

    // ---- set() ----

    public function test_set_単純なキーに値をセットできる(): void {
        $arr = [];
        ArrayUtil::set($arr, 'key', 'value');
        $this->assertSame('value', $arr['key']);
    }

    public function test_set_ドット区切りで多次元配列に値をセットできる(): void {
        $arr = [];
        ArrayUtil::set($arr, 'a.b.c', 'deep');
        $this->assertSame('deep', $arr['a']['b']['c']);
    }

    public function test_set_空キーは何もしない(): void {
        $arr = ['x' => 1];
        ArrayUtil::set($arr, '', 'val');
        $this->assertSame(['x' => 1], $arr);
    }

    // ---- matchKey() ----

    public function test_matchKey_パターンが一致する(): void {
        $this->assertTrue(ArrayUtil::matchKey('foo*', 'foobar'));
    }

    public function test_matchKey_パターンが一致しない(): void {
        $this->assertFalse(ArrayUtil::matchKey('foo*', 'barfoo'));
    }

    // ---- getKeyValueList() ----

    public function test_getKeyValueList_ワイルドカードキーとパスが正しく返る(): void {
        $arr = [0 => ['a' => 'b'], 1 => ['a' => 'c']];
        $result = ArrayUtil::getKeyValueList($arr, '*.a');
        $this->assertSame(['0.a' => 'b', '1.a' => 'c'], $result);
    }

    public function test_getKeyValueList_空キーは配列全体を返す(): void {
        $arr = ['x' => 1];
        $result = ArrayUtil::getKeyValueList($arr, '');
        $this->assertSame(['' => ['x' => 1]], $result);
    }

    // ---- isAllTrue() ----

    public function test_isAllTrue_全要素がtrueを返すcallable(): void {
        $this->assertTrue(ArrayUtil::isAllTrue([2, 4, 6], fn($v) => $v % 2 === 0));
    }

    public function test_isAllTrue_一要素がfalseを返すcallable(): void {
        $this->assertFalse(ArrayUtil::isAllTrue([2, 3, 6], fn($v) => $v % 2 === 0));
    }

    public function test_isAllTrue_空配列はtrueを返す(): void {
        $this->assertTrue(ArrayUtil::isAllTrue([], fn($v) => false));
    }

    // ---- isEmpty() ----

    public function test_isEmpty_空配列はtrue(): void {
        $this->assertTrue(ArrayUtil::isEmpty([]));
    }

    public function test_isEmpty_nullのみの配列はtrue(): void {
        $this->assertTrue(ArrayUtil::isEmpty([null, null]));
    }

    public function test_isEmpty_空文字のみの配列はtrue(): void {
        $this->assertTrue(ArrayUtil::isEmpty(['', '']));
    }

    public function test_isEmpty_値が入っている配列はfalse(): void {
        $this->assertFalse(ArrayUtil::isEmpty([0]));
    }

    public function test_isEmpty_混在配列でnull以外があればfalse(): void {
        $this->assertFalse(ArrayUtil::isEmpty([null, 'value']));
    }

    // ---- flatten() ----

    public function test_flatten_多次元配列を1次元にする(): void {
        $arr = [1, [2, [3, 4]], 5];
        $this->assertSame([1, 2, 3, 4, 5], ArrayUtil::flatten($arr));
    }

    public function test_flatten_既に1次元の場合そのまま返す(): void {
        $arr = [1, 2, 3];
        $this->assertSame([1, 2, 3], ArrayUtil::flatten($arr));
    }

    public function test_flatten_空配列を返す(): void {
        $this->assertSame([], ArrayUtil::flatten([]));
    }

    // ---- isHash() ----

    public function test_isHash_連想配列はtrue(): void {
        $this->assertTrue(ArrayUtil::isHash(['a' => 1, 'b' => 2]));
    }

    public function test_isHash_数値インデックス配列はfalse(): void {
        $this->assertFalse(ArrayUtil::isHash([1, 2, 3]));
    }

    public function test_isHash_空配列はfalse(): void {
        $this->assertFalse(ArrayUtil::isHash([]));
    }

    // ---- sortUnique() ----

    public function test_sortUnique_重複排除してソートされる(): void {
        $arr = [3, 1, 2, 1, 3];
        $this->assertSame([1, 2, 3], ArrayUtil::sortUnique($arr));
    }

    public function test_sortUnique_既に一意な場合はソートのみ(): void {
        $arr = [3, 1, 2];
        $this->assertSame([1, 2, 3], ArrayUtil::sortUnique($arr));
    }

    // ---- arrayMapRecursive() ----

    public function test_arrayMapRecursive_再帰的にマップされる(): void {
        $arr = [1, [2, 3]];
        $result = ArrayUtil::arrayMapRecursive($arr, fn($v) => $v * 2);
        $this->assertSame([2, [4, 6]], $result);
    }

    // ---- hasPatternKey() ----

    public function test_hasPatternKey_アスタリスクを含む場合true(): void {
        $this->assertTrue(ArrayUtil::hasPatternKey('foo.*'));
    }

    public function test_hasPatternKey_クエスチョンを含む場合true(): void {
        $this->assertTrue(ArrayUtil::hasPatternKey('foo?bar'));
    }

    public function test_hasPatternKey_ブラケットを含む場合true(): void {
        $this->assertTrue(ArrayUtil::hasPatternKey('foo[0]'));
    }

    public function test_hasPatternKey_特殊文字なしはfalse(): void {
        $this->assertFalse(ArrayUtil::hasPatternKey('foobar'));
    }
}

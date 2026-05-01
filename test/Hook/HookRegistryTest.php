<?php

namespace AIJOH\Test\Hook;

use AIJOH\Hook\HookRegistry;
use PHPUnit\Framework\TestCase;

class HookRegistryTest extends TestCase {

    // ---- on / dispatch ----

    public function test_dispatch_未登録イベントは何もしない() : void {
        $registry = new HookRegistry();
        // 例外が出ないことだけ確認
        $registry->dispatch('nothing');
        $this->assertSame(0, $registry->count('nothing'));
    }


    public function test_dispatch_登録された_listener_が呼ばれる() : void {
        $called = [];
        $registry = new HookRegistry();
        $registry->on('test', function( $a, $b ) use ( &$called ) {
            $called[] = [ $a, $b ];
        });

        $registry->dispatch('test', 1, 2);
        $this->assertSame([[1, 2]], $called);
    }


    public function test_dispatch_複数登録は順序通り呼ばれる() : void {
        $order = [];
        $registry = new HookRegistry();
        // short closure (fn) では参照キャプチャできないので普通の closure を使う
        $registry->on('test', function() use ( &$order ) { $order[] = 'a'; });
        $registry->on('test', function() use ( &$order ) { $order[] = 'b'; });
        $registry->on('test', function() use ( &$order ) { $order[] = 'c'; });

        $registry->dispatch('test');
        $this->assertSame([ 'a', 'b', 'c' ], $order);
    }


    public function test_dispatch_戻り値は無視される() : void {
        $registry = new HookRegistry();
        $registry->on('test', fn() => 'ignored');
        $registry->on('test', fn() => 'also ignored');

        // 戻り値を返さないが、何もエラーが出ないことを確認
        $registry->dispatch('test');
        $this->assertTrue(true);
    }


    // ---- filter ----

    public function test_filter_未登録は値をそのまま返す() : void {
        $registry = new HookRegistry();
        $this->assertSame(42, $registry->filter('test', 42));
    }


    public function test_filter_戻り値で順次変換される() : void {
        $registry = new HookRegistry();
        $registry->on('test', fn( $v ) => $v + 1);
        $registry->on('test', fn( $v ) => $v * 2);

        // 10 -> 11 -> 22
        $this->assertSame(22, $registry->filter('test', 10));
    }


    public function test_filter_リスナーが_null_を返したら値を変えない() : void {
        $registry = new HookRegistry();
        $registry->on('test', fn( $v ) => null);   // 何もしない
        $registry->on('test', fn( $v ) => $v + 1);

        $this->assertSame(11, $registry->filter('test', 10));
    }


    public function test_fold_未登録は値をそのまま返す() : void {
        $registry = new HookRegistry();
        $this->assertSame('init', $registry->fold('test', 'init'));
    }


    public function test_fold_は_null_も値として採用する() : void {
        $registry = new HookRegistry();
        $registry->on('test', fn( $v ) => null);    // null を採用
        $registry->on('test', fn( $v ) => $v ?? 'fallback');

        $this->assertSame('fallback', $registry->fold('test', 'init'));
    }


    public function test_fold_戻り値で順次変換される() : void {
        $registry = new HookRegistry();
        $registry->on('test', fn( $v ) => $v + 1);
        $registry->on('test', fn( $v ) => $v * 2);

        $this->assertSame(22, $registry->fold('test', 10));
    }


    public function test_fold_追加引数も渡される() : void {
        $registry = new HookRegistry();
        $captured = [];
        $registry->on('test', function( $value, $extra ) use ( &$captured ) {
            $captured[] = [ $value, $extra ];
            return $value;
        });

        $registry->fold('test', 'foo', 'context');
        $this->assertSame([[ 'foo', 'context' ]], $captured);
    }


    public function test_filter_追加引数も渡される() : void {
        $registry = new HookRegistry();
        $captured = [];
        $registry->on('test', function( $value, $extra ) use ( &$captured ) {
            $captured[] = [ $value, $extra ];
            return $value;
        });

        $registry->filter('test', 'foo', 'context');
        $this->assertSame([[ 'foo', 'context' ]], $captured);
    }


    public function test_filter_配列値の上書き() : void {
        $registry = new HookRegistry();
        $registry->on('test', function( array $data ) {
            $data['added'] = 'yes';
            return $data;
        });

        $result = $registry->filter('test', [ 'name' => '田中' ]);
        $this->assertSame([ 'name' => '田中', 'added' => 'yes' ], $result);
    }


    // ---- 例外伝播 ----

    public function test_dispatch_listener_の例外は呼び出し元に伝播() : void {
        $registry = new HookRegistry();
        $registry->on('test', fn() => throw new \RuntimeException('hook error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('hook error');
        $registry->dispatch('test');
    }


    public function test_filter_listener_の例外は呼び出し元に伝播() : void {
        $registry = new HookRegistry();
        $registry->on('test', fn( $v ) => throw new \LogicException('filter error'));

        $this->expectException(\LogicException::class);
        $registry->filter('test', 42);
    }


    // ---- clear ----

    public function test_clear_全イベントを解除() : void {
        $registry = new HookRegistry();
        $registry->on('a', fn() => null);
        $registry->on('b', fn() => null);

        $registry->clear();
        $this->assertSame(0, $registry->count('a'));
        $this->assertSame(0, $registry->count('b'));
    }


    public function test_clear_指定イベントのみ解除() : void {
        $registry = new HookRegistry();
        $registry->on('a', fn() => null);
        $registry->on('b', fn() => null);

        $registry->clear('a');
        $this->assertSame(0, $registry->count('a'));
        $this->assertSame(1, $registry->count('b'));
    }


    // ---- ユーティリティ ----

    public function test_count_は登録数を返す() : void {
        $registry = new HookRegistry();
        $this->assertSame(0, $registry->count('test'));
        $registry->on('test', fn() => null);
        $registry->on('test', fn() => null);
        $this->assertSame(2, $registry->count('test'));
    }


    public function test_events_は登録済イベント名一覧を返す() : void {
        $registry = new HookRegistry();
        $registry->on('a', fn() => null);
        $registry->on('b', fn() => null);
        $registry->on('a', fn() => null);    // 既存に追加でもキー重複しない

        $events = $registry->events();
        sort($events);
        $this->assertSame([ 'a', 'b' ], $events);
    }


    public function test_on_はチェイン可能() : void {
        $registry = new HookRegistry();
        $result = $registry
            ->on('a', fn() => null)
            ->on('b', fn() => null);
        $this->assertSame($registry, $result);
    }
}

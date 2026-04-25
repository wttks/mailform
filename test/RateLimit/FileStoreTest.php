<?php

namespace AIJOH\Test\RateLimit;

use AIJOH\RateLimit\FileStore;
use PHPUnit\Framework\TestCase;

class FileStoreTest extends TestCase {

    private string $tmpDir;

    protected function setUp(): void {
        $this->tmpDir = sys_get_temp_dir() . '/mailform_ratelimit_test_' . uniqid();
    }

    protected function tearDown(): void {
        $files = glob($this->tmpDir . '/*') ?: [];
        foreach ( $files as $file ) {
            @unlink($file);
        }
        @rmdir($this->tmpDir);
    }

    public function test_record_と_countWithin_の基本動作(): void {
        $store = new FileStore($this->tmpDir);
        $this->assertSame(0, $store->countWithin('foo', 60));

        $store->record('foo');
        $store->record('foo');
        $this->assertSame(2, $store->countWithin('foo', 60));
    }

    public function test_別キーは独立してカウント(): void {
        $store = new FileStore($this->tmpDir);
        $store->record('a');
        $store->record('b');
        $store->record('b');
        $this->assertSame(1, $store->countWithin('a', 60));
        $this->assertSame(2, $store->countWithin('b', 60));
    }

    public function test_時間窓外の記録はカウント対象外(): void {
        $store = new class($this->tmpDir) extends FileStore {
            public int $fakeNow = 1000;
            public function now() : int { return $this->fakeNow; }
        };

        $store->fakeNow = 1000;
        $store->record('x');  // ts=1000

        $store->fakeNow = 1100;
        $store->record('x');  // ts=1100

        // 60秒窓: now=1100, 1100-60=1040 以降 → 1100 のみ → 1件
        $this->assertSame(1, $store->countWithin('x', 60));
        // 200秒窓: 900 以降 → 1000, 1100 → 2件
        $this->assertSame(2, $store->countWithin('x', 200));
    }

    public function test_順次書き込みでもデータ消失しない_flock経由(): void {
        $store = new FileStore($this->tmpDir);
        for ( $i = 0; $i < 50; $i++ ) {
            $store->record('p');
        }
        $this->assertSame(50, $store->countWithin('p', 86400));
    }

    public function test_storage_dir_が無ければ作る(): void {
        $newDir = $this->tmpDir . '/nested';
        $store = new FileStore($newDir);
        $store->record('z');
        $this->assertTrue(is_dir($newDir));
        $this->assertSame(1, $store->countWithin('z', 60));
        // 後始末
        @unlink($newDir . '/' . sha1('z') . '.json');
        @rmdir($newDir);
    }

    public function test_gc_は古いファイルを削除する(): void {
        $store = new FileStore($this->tmpDir);
        $store->record('old');
        // ファイルの mtime を強制的に古くする
        $oldFile = $this->tmpDir . '/' . sha1('old') . '.json';
        touch($oldFile, time() - 100000);

        $store->record('new');

        $deleted = $store->gc(86400);
        $this->assertSame(1, $deleted);
        $this->assertFalse(is_file($oldFile));
        $this->assertTrue(is_file($this->tmpDir . '/' . sha1('new') . '.json'));
    }

}

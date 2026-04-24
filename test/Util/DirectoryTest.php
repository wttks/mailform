<?php

namespace AIJOH\Test\Util;

use AIJOH\Util\Directory;
use PHPUnit\Framework\TestCase;

/**
 * Directory のテスト
 * ファイルシステム操作には sys_get_temp_dir() を利用した一時ディレクトリを使用する
 */
class DirectoryTest extends TestCase {

    /** @var string テスト用一時ベースディレクトリ */
    private string $tempBase;

    protected function setUp(): void {
        $this->tempBase = sys_get_temp_dir() . '/aijoh_directory_test_' . uniqid();
    }

    protected function tearDown(): void {
        // テスト後に作成したディレクトリを再帰的に削除する
        if (is_dir($this->tempBase)) {
            $this->removeDir($this->tempBase);
        }
    }

    private function removeDir(string $dir): void {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    // ---- makeRecursive() ----

    public function test_makeRecursive_新規ディレクトリを作成しtrueを返す(): void {
        $path = $this->tempBase . '/new_dir';
        $result = Directory::makeRecursive($path);
        $this->assertTrue($result);
        $this->assertDirectoryExists($path);
    }

    public function test_makeRecursive_既に存在するディレクトリはtrueを返す(): void {
        $path = $this->tempBase . '/existing';
        mkdir($path, 0777, true);
        $result = Directory::makeRecursive($path);
        $this->assertTrue($result);
    }

    public function test_makeRecursive_ネストされたディレクトリを再帰的に作成する(): void {
        $path = $this->tempBase . '/a/b/c/d';
        $result = Directory::makeRecursive($path);
        $this->assertTrue($result);
        $this->assertDirectoryExists($path);
    }
}

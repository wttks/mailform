<?php

namespace AIJOH\Test\Hook;

use AIJOH\Hook\HookRegistry;
use AIJOH\Hook\MailformPlugin;
use AIJOH\Hook\PluginLoader;
use PHPUnit\Framework\TestCase;

class PluginLoaderTest extends TestCase {

    private string $tempDir;

    protected function setUp() : void {
        $this->tempDir = sys_get_temp_dir() . '/mailform_plugin_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }


    protected function tearDown() : void {
        foreach ( glob($this->tempDir . '/*') ?: [] as $f ) {
            @unlink($f);
        }
        @rmdir($this->tempDir);
    }


    private function writePlugin( string $filename, string $content ) : void {
        file_put_contents($this->tempDir . '/' . $filename, $content);
    }


    private function pluginCode( string $event = 'test_event' ) : string {
        return <<<PHP
<?php
return new class implements \AIJOH\Hook\MailformPlugin {
    public function register( \AIJOH\Hook\HookRegistry \$hooks ) : void {
        \$hooks->on('{$event}', fn() => 'fired');
    }
};
PHP;
    }


    // ---- 通常のロード ----

    public function test_存在しないディレクトリは無視される() : void {
        $registry = new HookRegistry();
        $loaded = PluginLoader::loadInto($registry, [ '/nonexistent/path' ]);
        $this->assertSame(0, $loaded);
    }


    public function test_空ディレクトリは0を返す() : void {
        $registry = new HookRegistry();
        $loaded = PluginLoader::loadInto($registry, [ $this->tempDir ]);
        $this->assertSame(0, $loaded);
    }


    public function test_plugin1個ロード() : void {
        $this->writePlugin('plugin1.php', $this->pluginCode('after_send'));

        $registry = new HookRegistry();
        $loaded = PluginLoader::loadInto($registry, [ $this->tempDir ]);

        $this->assertSame(1, $loaded);
        $this->assertSame(1, $registry->count('after_send'));
    }


    // ---- 順序制御 ----

    public function test_ファイル名昇順でロードされる() : void {
        $order = [];
        $writeWithMarker = function( $filename, $marker ) use ( &$order ) {
            $code = <<<PHP
<?php
return new class implements \AIJOH\Hook\MailformPlugin {
    public function register( \AIJOH\Hook\HookRegistry \$hooks ) : void {
        \$hooks->on('order', fn() => '{$marker}');
    }
};
PHP;
            $this->writePlugin($filename, $code);
        };

        // 意図的にバラバラに作成（昇順ロードが効くか確認）
        $writeWithMarker('20_b.php', 'b');
        $writeWithMarker('10_a.php', 'a');
        $writeWithMarker('30_c.php', 'c');

        $registry = new HookRegistry();
        PluginLoader::loadInto($registry, [ $this->tempDir ]);

        // dispatch でリスナー呼び出し順を確認
        $captured = [];
        $registry->on('order', fn() => null);   // ダミー（既存 listeners は呼ばれる）
        // 実際の挙動: 既存登録順で呼ばれる
        foreach ( [ 'order' ] as $event ) {
            // 各 listener の戻り値を取得するのは filter で代用
        }
        // listener が plugin で登録された順序を確認する別方法
        $reg = new HookRegistry();
        // 再度読み込んで filter で順序確認
        PluginLoader::loadInto($reg, [ $this->tempDir ]);
        $value = $reg->filter('order', '');
        // 各 plugin が string を返すが、null チェックで前の値が維持されるはず → 最後の戻り値が反映
        // 実際は各 listener が `fn() => 'a'` のように引数 $value を無視して固定値を返す。
        // filter は null 以外なら上書きするので、登録順で 'a' → 'b' → 'c' と上書きされ、最後に 'c'
        $this->assertSame('c', $value);
    }


    // ---- _disabled_ prefix ----

    public function test__disabled_プレフィックスはロードされない() : void {
        $this->writePlugin('_disabled_old.php', $this->pluginCode('disabled'));
        $this->writePlugin('active.php', $this->pluginCode('active'));

        $registry = new HookRegistry();
        $loaded = PluginLoader::loadInto($registry, [ $this->tempDir ]);

        $this->assertSame(1, $loaded);
        $this->assertSame(0, $registry->count('disabled'));
        $this->assertSame(1, $registry->count('active'));
    }


    // ---- 異常系 ----

    public function test_PHP_syntax_エラーは黙ってスキップされる() : void {
        // syntax エラーがあるファイル（require 時に Throwable）
        $this->writePlugin('broken.php', "<?php this is not valid php\n");
        $this->writePlugin('ok.php', $this->pluginCode('ok'));

        $registry = new HookRegistry();
        $loaded = PluginLoader::loadInto($registry, [ $this->tempDir ]);

        $this->assertSame(1, $loaded);
        $this->assertSame(1, $registry->count('ok'));
    }


    public function test_MailformPlugin_でないものを返したらスキップ() : void {
        $this->writePlugin('not_plugin.php', "<?php return new \\stdClass();\n");
        $this->writePlugin('ok.php', $this->pluginCode('ok'));

        $registry = new HookRegistry();
        $loaded = PluginLoader::loadInto($registry, [ $this->tempDir ]);

        $this->assertSame(1, $loaded);
        $this->assertSame(1, $registry->count('ok'));
    }


    public function test_register_で例外が出たらスキップ() : void {
        $code = <<<'PHP'
<?php
return new class implements \AIJOH\Hook\MailformPlugin {
    public function register( \AIJOH\Hook\HookRegistry $hooks ) : void {
        throw new \RuntimeException('plugin init failed');
    }
};
PHP;
        $this->writePlugin('bad.php', $code);
        $this->writePlugin('ok.php', $this->pluginCode('ok'));

        $registry = new HookRegistry();
        $loaded = PluginLoader::loadInto($registry, [ $this->tempDir ]);

        $this->assertSame(1, $loaded);
        $this->assertSame(1, $registry->count('ok'));
    }


    // ---- 複数ディレクトリ ----

    public function test_複数ディレクトリを順に走査() : void {
        $tempDir2 = sys_get_temp_dir() . '/mailform_plugin_test2_' . uniqid();
        mkdir($tempDir2, 0777, true);

        $this->writePlugin('a.php', $this->pluginCode('event_a'));
        file_put_contents($tempDir2 . '/b.php', $this->pluginCode('event_b'));

        $registry = new HookRegistry();
        $loaded = PluginLoader::loadInto($registry, [ $this->tempDir, $tempDir2 ]);

        $this->assertSame(2, $loaded);
        $this->assertSame(1, $registry->count('event_a'));
        $this->assertSame(1, $registry->count('event_b'));

        @unlink($tempDir2 . '/b.php');
        @rmdir($tempDir2);
    }
}

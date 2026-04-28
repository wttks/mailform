<?php

namespace AIJOH\Form\Draft;

use AIJOH\Form\Draft\Exception\DraftDecryptException;
use AIJOH\Form\Draft\Exception\DraftOverflowException;
use PHPUnit\Framework\TestCase;

class DraftSerializerTest extends TestCase {

    private string $key;
    private DraftSerializer $serializer;

    protected function setUp() : void {
        $this->key = str_repeat("\x00", SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $this->serializer = new DraftSerializer();
    }


    // ---- serialize: 正常系 ----

    public function test_短いデータは1つのCookieに収まる_かつ無圧縮() {
        $data = [ 'name' => '田中', 'email' => 'tanaka@example.com' ];
        $result = $this->serializer->serialize($data, $this->key, 512, 4096, 5);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey(0, $result);
        $this->assertStringStartsWith('v1.-.0.1.', $result[0]);
    }


    public function test_閾値超えのデータはgzip圧縮される() {
        $data = [ 'message' => str_repeat('あいうえお', 100) ];
        $result = $this->serializer->serialize($data, $this->key, 512, 4096, 5);
        $this->assertCount(1, $result);
        $this->assertStringStartsWith('v1.g.0.1.', $result[0]);
    }


    public function test_閾値0で圧縮を無効化できる() {
        $data = [ 'message' => str_repeat('あいうえお', 100) ];
        $result = $this->serializer->serialize($data, $this->key, 0, 14000, 5);
        $this->assertStringStartsWith('v1.-.', $result[0]);
    }


    public function test_長いデータは複数Cookieに分割される() {
        $data = [ 'message' => bin2hex(random_bytes(3000)) ];
        $result = $this->serializer->serialize($data, $this->key, 0, 14000, 5);
        $this->assertGreaterThan(1, count($result));
        foreach ( $result as $i => $value ) {
            $this->assertStringStartsWith('v1.-.' . $i . '.' . count($result) . '.', $value);
        }
    }


    // ---- serialize: 異常系 ----

    public function test_分割上限を超えたら例外() {
        $this->expectException(DraftOverflowException::class);
        $this->expectExceptionMessageMatches('/split limit/');
        $data = [ 'message' => str_repeat('a', 30000) ];
        $this->serializer->serialize($data, $this->key, 0, 1000000, 3);
    }


    public function test_max_bytesを超えたら例外() {
        $this->expectException(DraftOverflowException::class);
        $this->expectExceptionMessageMatches('/byte limit/');
        $data = [ 'message' => str_repeat('a', 5000) ];
        $this->serializer->serialize($data, $this->key, 0, 100, 50);
    }


    public function test_短すぎる暗号化キーは弾く() {
        $this->expectException(\InvalidArgumentException::class);
        $this->serializer->serialize([ 'a' => 'b' ], 'short', 512, 4096, 5);
    }


    // ---- 往復テスト ----

    public function test_短いデータの往復で完全一致() {
        $data = [ 'name' => '田中太郎', 'email' => 'tanaka@example.com' ];
        $cookies = $this->serializer->serialize($data, $this->key, 512, 4096, 5);
        $this->assertSame($data, $this->serializer->unserialize($cookies, $this->key));
    }


    public function test_圧縮済みデータの往復で完全一致() {
        $data = [ 'message' => str_repeat('あいうえお', 200) ];
        $cookies = $this->serializer->serialize($data, $this->key, 512, 4096, 5);
        $this->assertSame($data, $this->serializer->unserialize($cookies, $this->key));
    }


    public function test_分割データの往復で完全一致() {
        $data = [ 'message' => bin2hex(random_bytes(3000)) ];
        $cookies = $this->serializer->serialize($data, $this->key, 0, 14000, 5);
        $this->assertGreaterThan(1, count($cookies));
        $this->assertSame($data, $this->serializer->unserialize($cookies, $this->key));
    }


    public function test_配列とネストと日本語キーの往復で完全一致() {
        $data = [
            'name'       => '田中',
            'interests'  => [ 'web', 'mobile', 'ai' ],
            'address'    => [ 'zip' => '100-0001', 'street' => '千代田1-1' ],
            '日本語キー' => '値',
            'numbers'    => [ 1, 2, 3 ],
        ];
        $cookies = $this->serializer->serialize($data, $this->key, 512, 4096, 5);
        $this->assertSame($data, $this->serializer->unserialize($cookies, $this->key));
    }


    public function test_並べ替えてから渡しても復元できる() {
        $data = [ 'message' => bin2hex(random_bytes(3000)) ];
        $cookies = $this->serializer->serialize($data, $this->key, 0, 14000, 5);
        // 添字をシャッフル
        $shuffled = array_reverse($cookies, true);
        $this->assertSame($data, $this->serializer->unserialize($shuffled, $this->key));
    }


    // ---- unserialize: 異常系 ----

    public function test_不正な暗号化キーでは復号失敗() {
        $cookies = $this->serializer->serialize([ 'name' => '田中' ], $this->key, 512, 4096, 5);
        $wrongKey = str_repeat("\x01", SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $this->expectException(DraftDecryptException::class);
        $this->serializer->unserialize($cookies, $wrongKey);
    }


    public function test_欠損したCookieでは復号失敗() {
        $data = [ 'message' => bin2hex(random_bytes(3000)) ];
        $cookies = $this->serializer->serialize($data, $this->key, 0, 14000, 5);
        $this->assertGreaterThan(1, count($cookies));
        unset($cookies[1]);
        $this->expectException(DraftDecryptException::class);
        $this->serializer->unserialize($cookies, $this->key);
    }


    public function test_改ざんされたCookieでは復号失敗() {
        $cookies = $this->serializer->serialize([ 'name' => '田中' ], $this->key, 512, 4096, 5);
        // base64 部分の最初の文字を別の文字に置き換え
        $cookies[0] = preg_replace('/\.([A-Za-z0-9+\/=]+)$/', '.X$1', $cookies[0]);
        $this->expectException(DraftDecryptException::class);
        $this->serializer->unserialize($cookies, $this->key);
    }


    public function test_不正なフォーマットは弾く() {
        $this->expectException(DraftDecryptException::class);
        $this->serializer->unserialize([ 'invalid' ], $this->key);
    }


    public function test_空配列は弾く() {
        $this->expectException(DraftDecryptException::class);
        $this->serializer->unserialize([], $this->key);
    }


    public function test_異なるバージョンのCookieは弾く() {
        $this->expectException(DraftDecryptException::class);
        $this->serializer->unserialize([ 'v2.-.0.1.aaaa' ], $this->key);
    }


    public function test_index_total不整合は弾く() {
        $this->expectException(DraftDecryptException::class);
        $this->serializer->unserialize([ 'v1.-.5.1.aaaa' ], $this->key);  // index >= total
    }


    public function test_短すぎる暗号化キーは弾く_unserialize() {
        $this->expectException(\InvalidArgumentException::class);
        $this->serializer->unserialize([ 'v1.-.0.1.aaaa' ], 'short');
    }


    public function test_合計数不整合は弾く() {
        $this->expectException(DraftDecryptException::class);
        $this->serializer->unserialize([
            'v1.-.0.2.aaaa',
            'v1.-.1.3.bbbb',  // total が片方 2、片方 3
        ], $this->key);
    }


    public function test_圧縮フラグ不整合は弾く() {
        $this->expectException(DraftDecryptException::class);
        $this->serializer->unserialize([
            'v1.-.0.2.aaaa',
            'v1.g.1.2.bbbb',  // 片方圧縮、片方非圧縮
        ], $this->key);
    }
}

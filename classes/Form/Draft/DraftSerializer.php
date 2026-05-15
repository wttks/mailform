<?php

namespace AIJOH\Form\Draft;

use AIJOH\Form\Draft\Exception\DraftDecryptException;
use AIJOH\Form\Draft\Exception\DraftOverflowException;

/**
 * draft データのシリアライズ／デシリアライズを行う。
 *
 * 出力フォーマット（Cookie 1 個分の値）:
 *   v1.<flag>.<index>.<total>.<base64>
 *     flag  : 'g' = gzip 圧縮済み、'-' = 無圧縮
 *     index : 0 始まり、分割番号
 *     total : 全分割数
 *     base64: nonce(24B) + ciphertext(MAC 16B + payload) を base64
 *
 * 例:
 *   v1.-.0.1.<base64>            … 無圧縮、1 個に収まり
 *   v1.g.0.3.<...> v1.g.1.3.<...> v1.g.2.3.<...>  … gzip 圧縮で 3 分割
 */
class DraftSerializer {

    private const VERSION = 'v1';

    /**
     * 1 Cookie 値の最大サイズ（base64 部分）。Cookie 4KB 制限から
     * ヘッダ ("v1.g.99.99.") を引いた余裕値。
     */
    private const CHUNK_SIZE = 3500;


    /**
     * 連想配列を Cookie 値の配列にシリアライズする。
     *
     * @param array $data 保存対象データ（任意のネスト・配列・日本語キー可）
     * @param string $key 32 バイトの暗号化キー（sodium_crypto_secretbox 用）
     * @param int $compressThreshold このバイト数以上で gzip 圧縮（0 で常に無圧縮）
     * @param int $maxBytes 最終 Cookie 値合計の上限（HTTP ヘッダ制限を考慮）
     * @param int $maxSplit 最大分割 Cookie 数
     * @return string[] Cookie 値の配列（添字 0 から連番）
     * @throws DraftOverflowException 容量超過
     * @throws \InvalidArgumentException キー長違反
     * @throws \RuntimeException JSON エンコード / 暗号化失敗
     */
    public function serialize(
        array $data,
        string $key,
        int $compressThreshold,
        int $maxBytes,
        int $maxSplit,
    ) : array {
        $this->assertKey($key);

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ( $payload === false ) {
            throw new \RuntimeException('JSON encode failed: ' . json_last_error_msg());
        }

        $compressed = false;
        if ( $compressThreshold > 0 && strlen($payload) >= $compressThreshold ) {
            $deflated = gzdeflate($payload, 9);
            // 圧縮しても増えた場合は無圧縮のまま使う
            if ( $deflated !== false && strlen($deflated) < strlen($payload) ) {
                $payload = $deflated;
                $compressed = true;
            }
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($payload, $nonce, $key);
        $encoded = base64_encode($nonce . $ciphertext);

        $chunks = str_split($encoded, self::CHUNK_SIZE);
        $total = count($chunks);

        if ( $total > $maxSplit ) {
            throw new DraftOverflowException(
                "Draft data exceeds split limit: {$total} chunks > {$maxSplit} max",
            );
        }

        $flag = $compressed ? 'g' : '-';
        $result = [];
        $totalSize = 0;
        foreach ( $chunks as $i => $chunk ) {
            $value = self::VERSION . ".{$flag}.{$i}.{$total}." . $chunk;
            $result[ $i ] = $value;
            $totalSize += strlen($value);
        }

        if ( $totalSize > $maxBytes ) {
            throw new DraftOverflowException(
                "Draft data exceeds byte limit: {$totalSize} > {$maxBytes} bytes",
            );
        }

        return $result;
    }


    /**
     * Cookie 値の配列を連想配列にデシリアライズする。
     *
     * 復号前に Cookie 個数と合計バイト数の上限を検証する ( メモリ DoS 対策 )。
     * 上限を超えていたら base64_decode / 解凍に進まずに例外を投げる。
     *
     * @param string[] $cookieValues Cookie 値の配列（添字は問わない、内部で並べ替え）
     * @param string $key 32 バイトの暗号化キー
     * @param int $maxBytes Cookie 値の合計バイト数の上限 ( デフォルト無制限 )
     * @param int $maxSplit Cookie 個数の上限 ( デフォルト無制限 )
     * @return array 元データ
     * @throws DraftDecryptException 復号・パース失敗、または上限超過
     * @throws \InvalidArgumentException キー長違反
     */
    public function unserialize(
        array $cookieValues,
        string $key,
        int $maxBytes = PHP_INT_MAX,
        int $maxSplit = PHP_INT_MAX,
    ) : array {
        $this->assertKey($key);

        if ( empty($cookieValues) ) {
            throw new DraftDecryptException('No cookie values provided');
        }

        // 分割数の上限チェック
        $count = count($cookieValues);
        if ( $count > $maxSplit ) {
            throw new DraftDecryptException(
                "Too many chunks: {$count} > {$maxSplit} max",
            );
        }

        // 合計バイト数の上限チェック ( base64 decode 前 / メモリ DoS 対策 )
        $totalBytes = 0;
        foreach ( $cookieValues as $v ) {
            $totalBytes += strlen((string) $v);
        }
        if ( $totalBytes > $maxBytes ) {
            throw new DraftDecryptException(
                "Draft cookies too large: {$totalBytes} > {$maxBytes} bytes",
            );
        }

        $chunks = [];
        $expectedTotal = null;
        $compressed = null;
        foreach ( $cookieValues as $value ) {
            $parsed = $this->parseCookieValue((string) $value);
            if ( $parsed === null ) {
                throw new DraftDecryptException('Invalid cookie format');
            }
            [ $flag, $index, $total, $chunk ] = $parsed;

            if ( $expectedTotal === null ) {
                $expectedTotal = $total;
                $compressed = ( $flag === 'g' );
            } elseif ( $expectedTotal !== $total ) {
                throw new DraftDecryptException(
                    "Inconsistent total chunk count: {$expectedTotal} vs {$total}",
                );
            } elseif ( $compressed !== ( $flag === 'g' ) ) {
                throw new DraftDecryptException('Inconsistent compression flag across chunks');
            }

            $chunks[ $index ] = $chunk;
        }

        if ( count($chunks) !== $expectedTotal ) {
            throw new DraftDecryptException(
                'Missing chunks: have ' . count($chunks) . ", expected {$expectedTotal}",
            );
        }
        ksort($chunks);
        $encoded = implode('', $chunks);

        $binary = base64_decode($encoded, true);
        if ( $binary === false ) {
            throw new DraftDecryptException('Base64 decode failed');
        }
        if ( strlen($binary) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
            throw new DraftDecryptException('Encrypted data too short');
        }

        $nonce = substr($binary, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($binary, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        if ( $plain === false ) {
            throw new DraftDecryptException('Decryption failed (wrong key or tampered data)');
        }

        if ( $compressed ) {
            $inflated = gzinflate($plain);
            if ( $inflated === false ) {
                throw new DraftDecryptException('Gzip inflate failed');
            }
            $plain = $inflated;
        }

        $data = json_decode($plain, true);
        if ( ! is_array($data) ) {
            throw new DraftDecryptException('JSON decode failed: ' . json_last_error_msg());
        }

        return $data;
    }


    /**
     * Cookie 値をパースする。
     * @return array{0:string,1:int,2:int,3:string}|null [flag, index, total, chunk] または null（不正形式）
     */
    private function parseCookieValue( string $value ) : ?array {
        // v1.<flag>.<index>.<total>.<chunk>
        $parts = explode('.', $value, 5);
        if ( count($parts) !== 5 ) {
            return null;
        }
        if ( $parts[0] !== self::VERSION ) {
            return null;
        }
        if ( ! in_array($parts[1], [ 'g', '-' ], true) ) {
            return null;
        }
        if ( ! ctype_digit($parts[2]) || ! ctype_digit($parts[3]) ) {
            return null;
        }
        $index = (int) $parts[2];
        $total = (int) $parts[3];
        if ( $total < 1 || $index < 0 || $index >= $total ) {
            return null;
        }
        return [ $parts[1], $index, $total, $parts[4] ];
    }


    private function assertKey( string $key ) : void {
        if ( strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) {
            throw new \InvalidArgumentException(
                'Encryption key must be ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . ' bytes',
            );
        }
    }
}

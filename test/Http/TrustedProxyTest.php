<?php

namespace AIJOH\Test\Http;

use AIJOH\Http\TrustedProxy;
use PHPUnit\Framework\TestCase;

class TrustedProxyTest extends TestCase {

    protected function setUp() : void {
        TrustedProxy::reset();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        unset($_SERVER['HTTPS']);
        unset($_SERVER['SERVER_PORT']);
    }

    protected function tearDown() : void {
        TrustedProxy::reset();
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        unset($_SERVER['HTTPS']);
        unset($_SERVER['SERVER_PORT']);
    }


    // ---- getClientIp ----

    public function test_configure未呼出は安全側_XFFを無視してREMOTE_ADDR() : void {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 172.20.0.3';
        $this->assertSame('203.0.113.1', TrustedProxy::getClientIp());
    }


    public function test_configure未呼出_XFFなしならREMOTE_ADDR() : void {
        $this->assertSame('203.0.113.1', TrustedProxy::getClientIp());
    }


    public function test_trust_forwarded_for_false_なら常にREMOTE_ADDR() : void {
        TrustedProxy::configure([
            'trust_forwarded_for' => false,
            'trusted_proxies' => [],
        ]);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        $this->assertSame('203.0.113.1', TrustedProxy::getClientIp());
    }


    public function test_trust_true_proxies空_は安全側でXFF無視() : void {
        TrustedProxy::configure([
            'trust_forwarded_for' => true,
            'trusted_proxies' => [],
        ]);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 172.20.0.3';
        $this->assertSame('203.0.113.1', TrustedProxy::getClientIp());
    }


    public function test_REMOTE_ADDRがtrustedなら_XFF末尾から非trustedをたどる() : void {
        // XFF: client(198.51.100.1) → edge(172.20.0.10) → internal(172.20.0.3)
        // REMOTE_ADDR = 172.20.0.3 (内部 LB)
        $_SERVER['REMOTE_ADDR'] = '172.20.0.3';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 172.20.0.10, 172.20.0.3';
        TrustedProxy::configure([
            'trust_forwarded_for' => true,
            'trusted_proxies' => [ '172.20.0.0/16' ],
        ]);
        $this->assertSame('198.51.100.1', TrustedProxy::getClientIp());
    }


    public function test_REMOTE_ADDRが非trustedなら_XFFを無視してREMOTE_ADDR() : void {
        // 攻撃者: REMOTE_ADDR=198.51.100.2, XFF=127.0.0.1 偽装
        $_SERVER['REMOTE_ADDR'] = '198.51.100.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '127.0.0.1';
        TrustedProxy::configure([
            'trust_forwarded_for' => true,
            'trusted_proxies' => [ '172.20.0.0/16' ],
        ]);
        $this->assertSame('198.51.100.2', TrustedProxy::getClientIp());
    }


    public function test_全てtrustedならXFFの先頭を返す() : void {
        $_SERVER['REMOTE_ADDR'] = '172.20.0.3';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '172.20.0.5, 172.20.0.10';
        TrustedProxy::configure([
            'trust_forwarded_for' => true,
            'trusted_proxies' => [ '172.20.0.0/16' ],
        ]);
        $this->assertSame('172.20.0.5', TrustedProxy::getClientIp());
    }


    public function test_単一IP指定でも判定可能() : void {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        TrustedProxy::configure([
            'trust_forwarded_for' => true,
            'trusted_proxies' => [ '127.0.0.1' ],
        ]);
        $this->assertSame('198.51.100.1', TrustedProxy::getClientIp());
    }


    public function test_IPv6_CIDRも判定可能() : void {
        $_SERVER['REMOTE_ADDR'] = 'fd12:3456:789a::1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '2001:db8::abcd';
        TrustedProxy::configure([
            'trust_forwarded_for' => true,
            'trusted_proxies' => [ 'fd00::/8' ],
        ]);
        $this->assertSame('2001:db8::abcd', TrustedProxy::getClientIp());
    }


    public function test_IPv6_loopback完全一致() : void {
        $_SERVER['REMOTE_ADDR'] = '::1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        TrustedProxy::configure([
            'trust_forwarded_for' => true,
            'trusted_proxies' => [ '::1' ],
        ]);
        $this->assertSame('198.51.100.1', TrustedProxy::getClientIp());
    }


    public function test_IPv4とIPv6を混在しても誤マッチしない() : void {
        // IPv4 の REMOTE_ADDR に対して IPv6 範囲を trusted に入れても誤マッチしない
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        TrustedProxy::configure([
            'trust_forwarded_for' => true,
            'trusted_proxies' => [ '::1/128' ],
        ]);
        // 127.0.0.1 は ::1 には該当しないので XFF は採用されない
        $this->assertSame('127.0.0.1', TrustedProxy::getClientIp());
    }


    // ---- isHttps ----

    public function test_isHttps_HTTPS変数_on() : void {
        $_SERVER['HTTPS'] = 'on';
        $this->assertTrue(TrustedProxy::isHttps());
    }


    public function test_isHttps_HTTPS変数_off_扱い() : void {
        $_SERVER['HTTPS'] = 'off';
        $this->assertFalse(TrustedProxy::isHttps());
    }


    public function test_isHttps_SERVER_PORT_443() : void {
        $_SERVER['SERVER_PORT'] = '443';
        $this->assertTrue(TrustedProxy::isHttps());
    }


    public function test_isHttps_configure未呼出は安全側_XFPを無視() : void {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertFalse(TrustedProxy::isHttps());
    }


    public function test_isHttps_trust_false_ならXFPを無視() : void {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        TrustedProxy::configure([
            'trust_forwarded_for' => false,
            'trusted_proxies' => [],
        ]);
        $this->assertFalse(TrustedProxy::isHttps());
    }


    public function test_isHttps_trust_true_proxies空_は安全側でXFP無視() : void {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        TrustedProxy::configure([
            'trust_forwarded_for' => true,
            'trusted_proxies' => [],
        ]);
        $this->assertFalse(TrustedProxy::isHttps());
    }


    public function test_isHttps_REMOTE_ADDRがtrustedならXFP信頼() : void {
        $_SERVER['REMOTE_ADDR'] = '172.20.0.3';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        TrustedProxy::configure([
            'trust_forwarded_for' => true,
            'trusted_proxies' => [ '172.20.0.0/16' ],
        ]);
        $this->assertTrue(TrustedProxy::isHttps());
    }


    public function test_isHttps_REMOTE_ADDRが非trustedならXFP無視() : void {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.2';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        TrustedProxy::configure([
            'trust_forwarded_for' => true,
            'trusted_proxies' => [ '172.20.0.0/16' ],
        ]);
        $this->assertFalse(TrustedProxy::isHttps());
    }


    // ---- ipInCidr / isTrustedIp 単体 ----

    public function test_ipInCidr_IPv4_境界値() : void {
        $this->assertTrue(TrustedProxy::ipInCidr('172.20.0.0', '172.20.0.0/16'));
        $this->assertTrue(TrustedProxy::ipInCidr('172.20.255.255', '172.20.0.0/16'));
        $this->assertFalse(TrustedProxy::ipInCidr('172.21.0.0', '172.20.0.0/16'));
        $this->assertFalse(TrustedProxy::ipInCidr('172.19.255.255', '172.20.0.0/16'));
    }


    public function test_ipInCidr_スラッシュ32は完全一致() : void {
        $this->assertTrue(TrustedProxy::ipInCidr('192.0.2.1', '192.0.2.1/32'));
        $this->assertFalse(TrustedProxy::ipInCidr('192.0.2.2', '192.0.2.1/32'));
    }


    public function test_ipInCidr_スラッシュ0は全マッチ() : void {
        $this->assertTrue(TrustedProxy::ipInCidr('1.2.3.4', '0.0.0.0/0'));
        $this->assertTrue(TrustedProxy::ipInCidr('::1', '::/0'));
    }


    public function test_ipInCidr_不正なCIDRはfalse() : void {
        $this->assertFalse(TrustedProxy::ipInCidr('1.2.3.4', 'not-a-cidr/16'));
        $this->assertFalse(TrustedProxy::ipInCidr('1.2.3.4', '1.2.3.4/abc'));
        $this->assertFalse(TrustedProxy::ipInCidr('not-an-ip', '1.2.3.0/24'));
    }


    public function test_isTrustedIp_空配列はfalse() : void {
        $this->assertFalse(TrustedProxy::isTrustedIp('127.0.0.1', []));
    }


    public function test_isTrustedIp_空IPはfalse() : void {
        $this->assertFalse(TrustedProxy::isTrustedIp('', [ '127.0.0.1' ]));
    }
}

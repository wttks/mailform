<?php

namespace AIJOH\Test\AI\Client;

/**
 * postJson() を捕捉して固定レスポンスを返すテスト用 trait。
 * 各 Provider のテストで HttpAIClient のサブクラスにミックスインする。
 */
trait CapturingPostJson {
    public string $capturedUrl = '';
    public array $capturedHeaders = [];
    public array $capturedBody = [];
    public array $fakeResponse = [];

    protected function postJson( string $url, array $headers, array $body ) : array {
        $this->capturedUrl = $url;
        $this->capturedHeaders = $headers;
        $this->capturedBody = $body;
        return $this->fakeResponse;
    }
}

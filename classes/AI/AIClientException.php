<?php

namespace AIJOH\AI;

/**
 * AI クライアントのリクエスト失敗。
 * ネットワーク・認証・レスポンス解析エラーを統一して投げる。
 * 上位は Fail Open 等のフォールバックを行う。
 */
class AIClientException extends \RuntimeException {}

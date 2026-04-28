<?php

namespace AIJOH\AI;

/**
 * 汎用 AI クライアント抽象。
 *
 * Claude / OpenAI / Gemini など、各プロバイダの差を吸収して
 * 統一インタフェース send($request) で呼び出せるようにする。
 * スパム判定だけでなく、要約・分類・翻訳など他の用途でも使い回せる。
 */
abstract class AIClient {

    /**
     * リクエストを送信して結果を返す。
     *
     * @throws AIClientException 通信エラー / 認証エラー / レスポンス解析失敗時
     */
    abstract public function send( AIRequest $request ) : AIResponse;

}

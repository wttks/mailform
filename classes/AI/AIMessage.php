<?php

namespace AIJOH\AI;

/**
 * AI への 1 メッセージ。
 */
final class AIMessage {

    public function __construct(
        /** 'user' | 'assistant' */
        public readonly string $role,
        public readonly string $content,
    ) {}


    public static function user( string $content ) : self {
        return new self('user', $content);
    }


    public static function assistant( string $content ) : self {
        return new self('assistant', $content);
    }

}

<?php

namespace AIJOH\AISpam;

/**
 * AI への入力をサニタイズするヘルパ。
 *
 * Indirect Prompt Injection 対策（Kerckhoffs's principle 前提）:
 * - XML 境界タグ文字列のエスケープ（`<user_input>` `</user_input>` `<field` 等）
 * - CDATA 終端 `]]>` のエスケープ
 * - AI モデル特殊トークンの正規化（`<|im_start|>` `[INST]` `Human:` 等）
 * - 制御文字（`\x00-\x1f` の `\t\r\n` 以外）を半角スペースに置換
 *
 * これらはアルゴリズム公開を前提に、入力内に攻撃文字列が含まれてもプロンプト
 * 構造を壊させないことを目的とする。
 */
final class InputSanitizer {

    /**
     * AI モデル特殊トークンの正規表現リスト。
     * 各エントリは preg_replace に渡せる pattern。
     * マッチした文字列は全角化 / 削除する（後述の sanitize() 参照）。
     */
    private const MODEL_TOKEN_PATTERNS = [
        // OpenAI / Llama 系: <|...|>
        '/<\\|[^>]{0,40}\\|>/u',
        // Llama 系: [INST] [/INST] <<SYS>> <</SYS>> <s> </s>
        '/\\[\\/?INST\\]/u',
        '/<<\\/?SYS>>/u',
        '/<\\/?s>/u',
        // Gemini: <start_of_turn> <end_of_turn>
        '/<start_of_turn>/u',
        '/<end_of_turn>/u',
        // Claude / 古い ChatGPT 系: 行頭の Human: / Assistant:
        '/^(Human|Assistant|System):\\s*/mu',
    ];


    /**
     * XML 境界タグとして mailform が使う文字列を全角化するマップ。
     * 入力中に含まれていたら全角不等号に置換してプロンプト構造を壊させない。
     */
    private const XML_BOUNDARIES = [
        '<user_input>'  => '＜user_input＞',
        '</user_input>' => '＜/user_input＞',
        '<field'        => '＜field',
        '</field>'      => '＜/field＞',
    ];


    /**
     * 入力文字列をサニタイズする。
     *
     * @param string $input  入力（フィールド値）
     * @param string[] $extraTokens 設置者が追加で除去したいトークンの正規表現
     * @return string サニタイズ済み文字列
     */
    public static function sanitize( string $input, array $extraTokens = [] ) : string {
        // 1. 制御文字を半角スペースに（\t \r \n は維持）
        $output = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u', ' ', $input) ?? $input;

        // 2. AI モデル特殊トークンを全角化（マッチ箇所を [token] 形式に）
        foreach ( self::MODEL_TOKEN_PATTERNS as $pattern ) {
            $output = preg_replace_callback($pattern, function( $m ) {
                return self::neutralize($m[0]);
            }, $output) ?? $output;
        }

        // 3. 設置者カスタムトークンも同様に処理
        foreach ( $extraTokens as $pattern ) {
            if ( ! is_string($pattern) || $pattern === '' ) continue;
            // 設置者は正規表現を渡してくる前提（pattern 形式）
            $result = @preg_replace_callback($pattern, function( $m ) {
                return self::neutralize($m[0]);
            }, $output);
            if ( $result !== null ) {
                $output = $result;
            }
        }

        // 4. CDATA 終端 `]]>` をエスケープ（CDATA セクションを早期終了させない）
        $output = str_replace(']]>', ']]&gt;', $output);

        // 5. XML 境界タグ文字列を全角化
        foreach ( self::XML_BOUNDARIES as $needle => $replacement ) {
            $output = str_replace($needle, $replacement, $output);
        }

        return $output;
    }


    /**
     * マッチした文字列を「無害化」する。
     *
     * 単純削除すると攻撃文字列の境界が分かりにくくなるので、
     * 各文字を半角スペースに置換した同じ長さの文字列を返す。
     * これによりログ・デバッグ時に「ここに何かあった」が分かる。
     */
    private static function neutralize( string $match ) : string {
        $length = mb_strlen($match, 'UTF-8');
        return str_repeat(' ', $length);
    }
}

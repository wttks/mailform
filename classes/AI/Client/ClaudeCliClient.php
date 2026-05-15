<?php

namespace AIJOH\AI\Client;

use AIJOH\AI\AIClient;
use AIJOH\AI\AIClientException;
use AIJOH\AI\AIRequest;
use AIJOH\AI\AIResponse;

/**
 * ローカルの claude コマンド経由で Claude を呼ぶクライアント。
 * Claude Code CLI が PATH 上にあることが前提。API キー不要。
 *
 * 開発・テスト・閉鎖環境向け。本番はネットワーク経由 API を推奨。
 *
 * 設定キー:
 *   - command:   実行コマンド（デフォルト 'claude'）
 *   - timeout:   タイムアウト秒
 *   - extra_args: 追加 CLI 引数の配列（例: ['--model', 'sonnet']）
 */
class ClaudeCliClient extends AIClient {

    private array $config;
    private int $timeout;


    public function __construct( array $config ) {
        $this->config  = $config;
        $this->timeout = (int) ($config['timeout'] ?? 30);
    }


    public function send( AIRequest $request ) : AIResponse {
        $command  = (string) ($this->config['command'] ?? 'claude');
        $extraArgs = (array) ($this->config['extra_args'] ?? []);

        // claude -p (--print) で非対話モード
        $args = [$command, '-p'];

        // システムプロンプトは --system-prompt に渡す（プロンプト混入を避ける）
        $system = $request->system;
        if ( $request->jsonMode ) {
            $system .= ($system === '' ? '' : "\n\n")
                . 'Respond with a JSON object only. Do not include any explanation or markdown code fences.';
        }
        if ( $system !== '' ) {
            $args[] = '--system-prompt';
            $args[] = $system;
        }

        foreach ( $extraArgs as $arg ) {
            $args[] = (string) $arg;
        }

        // user/assistant メッセージを1つのプロンプトに連結する（claude -p は単一プロンプトのみ）
        $args[] = $this->buildUserPrompt($request);

        $stdout = $this->execCommand($args);

        return $this->buildResponse($stdout, $request->jsonMode);
    }


    private function buildUserPrompt( AIRequest $request ) : string {
        if ( count($request->messages) === 1 && $request->messages[0]->role === 'user' ) {
            return $request->messages[0]->content;
        }
        $parts = [];
        foreach ( $request->messages as $m ) {
            $label = $m->role === 'assistant' ? 'Assistant' : 'User';
            $parts[] = "[{$label}]\n" . $m->content;
        }
        return implode("\n\n", $parts);
    }


    /**
     * @param array<int, string> $args
     * @return string stdout の内容
     * @throws AIClientException
     */
    private function execCommand( array $args ) : string {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        // proc_open の警告（実行ファイル無し等）は AIClientException で表現するため抑制する
        $process = @proc_open($args, $descriptors, $pipes);
        if ( ! is_resource($process) ) {
            throw new AIClientException('claude コマンドの起動に失敗しました');
        }
        fclose($pipes[0]);

        // タイムアウト付きで読み取る
        $stdout = '';
        $stderr = '';
        $start = time();
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        while ( true ) {
            $status = proc_get_status($process);
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            if ( ! $status['running'] ) {
                break;
            }
            if ( time() - $start > $this->timeout ) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                throw new AIClientException("claude コマンドがタイムアウト ({$this->timeout}秒)");
            }
            usleep(100_000);  // 0.1秒
        }
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ( $exitCode !== 0 ) {
            throw new AIClientException("claude コマンドが終了コード {$exitCode}: " . substr($stderr, 0, 200));
        }
        return trim($stdout);
    }


    /**
     * 文字列から JSON を取り出す。
     * 受け付けるのは「全体がコードブロック」「全体がプレーン JSON」のみ。
     * 説明文混在応答は採用しない ( 詳細は HttpAIClient::extractJsonObject() の注釈 )。
     */
    private function buildResponse( string $text, bool $jsonMode ) : AIResponse {
        $jsonData = null;
        if ( $jsonMode ) {
            // 全体が ```json``` コードブロック ( 前後空白のみ可 )
            if ( preg_match('/\A\s*```(?:json)?\s*(\{.*?\})\s*```\s*\z/s', $text, $m) ) {
                $decoded = json_decode($m[1], true);
                if ( is_array($decoded) ) $jsonData = $decoded;
            }
            // 全体がプレーン JSON
            if ( $jsonData === null ) {
                $decoded = json_decode($text, true);
                if ( is_array($decoded) ) {
                    $jsonData = $decoded;
                }
            }
        }
        return new AIResponse($text, $jsonData);
    }

}

<?php

namespace AIJOH\Sender\Send;

use AIJOH\Results\Formatter\FormatBase;
use AIJOH\Results\Formatter\Formatter;
use AIJOH\Results\FormData;

/**
 * フォームのデータを送信するクラスの基底クラス
 */
abstract class AbstractSendBase implements Send {
    
    protected ?array $config = null;
    
    protected ?FormatBase $format = null;
    
    
    public function __construct() {
    }
    
    
    /**
     * 送信用のデータを設定します。
     * @param array $config
     * @return void
     */
    public function setConfig( array $config ) : void {
        $this->config = $this->parseConfig($config);
    }
    
    
    /**
     * 設定情報を解析します。
     * @param array $config
     * @return array
     */
    protected abstract function parseConfig( array $config ) : array;
    
    /**
     * データフォーマット用のクラスを設定する。
     * @param FormatBase $format
     * @return void
     */
    public function setFormat( FormatBase $format ) : void {
        $this->format = $format;
    }
    
    /**
     * 指定したキーに対応するデータを返す。
     * @param string $key
     * @return string
     */
    protected function getStringValue( string $key ) : string {
        $value = $this->config[ $key ] ?? "";
        if ( $this->format === null ) {
            return $value;
        }
        return $this->format->format($value);
    }
    
    /**
     * 設定情報を取得する。
     * @return array
     */
    public function getConfig() : array {
        return $this->config;
    }
    
    /**
     * データを送信します。
     * @return bool
     */
    public abstract function send() : bool;


    /**
     * 設定 'attachments' を解析して [ ['path' => ..., 'name' => ...], ... ] の配列に正規化する。
     *
     * 対応形式:
     *   - 'attachments' => [ '/path/to/file.pdf', ... ]
     *   - 'attachments' => [ ['path' => '/path/to/x.pdf', 'name' => 'ご案内.pdf'], ... ]
     *   - 'attachments' => fn(array $data) => [...]    // フォーム値で動的決定
     *
     * 不正形式の要素は WARN ログを出してスキップ。
     *
     * @return array<int, array{path: string, name: string}>
     */
    protected function resolveStaticAttachments() : array {
        $config = $this->config['attachments'] ?? [];
        if ( is_callable($config) ) {
            $data = $this->format !== null ? $this->format->getFormData()->getData() : [];
            $config = $config($data);
        }
        if ( ! is_array($config) ) {
            return [];
        }
        $result = [];
        foreach ( $config as $i => $item ) {
            if ( is_string($item) ) {
                $result[] = [ 'path' => $item, 'name' => '' ];
                continue;
            }
            if ( is_array($item) && isset($item['path']) && is_string($item['path']) ) {
                $result[] = [
                    'path' => $item['path'],
                    'name' => (string) ( $item['name'] ?? '' ),
                ];
                continue;
            }
            error_log("[AbstractSendBase] invalid attachment at index {$i}, skipped");
        }
        return $result;
    }


    /**
     * 解決済みの static attachments を MailSendBase 系の mailer に追加する。
     * 存在しないファイルは WARN ログを出してスキップ（他の添付は正常送信）。
     */
    protected function addStaticAttachmentsTo( \AIJOH\Output\Mailer\MailSendBase $mailer ) : void {
        foreach ( $this->resolveStaticAttachments() as $att ) {
            if ( $att['path'] === '' || ! is_file($att['path']) ) {
                error_log("[Send] static attachment file not found: '{$att['path']}'");
                continue;
            }
            $mailer->addAttachmentFile($att['path'], $att['name']);
        }
    }
}
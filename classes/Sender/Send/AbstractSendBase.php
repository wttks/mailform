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
}
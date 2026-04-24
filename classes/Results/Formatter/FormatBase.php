<?php

namespace AIJOH\Results\Formatter;

use AIJOH\Http\UploadFile;
use AIJOH\Results\FormData;
use AIJOH\Util\ArrayUtil;

abstract class FormatBase {
    
    public function __construct( protected readonly FormData $data ) {
    }
    
    
    /**
     * 指定した文字列の中の置換文字を元にFormDataの値に置換する。
     * @param string $text 元の文字列
     * @return string 置換後の文字列
     */
    public abstract function format( string $text ) : string;
    
    
    /**
     * フォームデータを取得する。
     * @return FormData
     */
    public function getFormData() : FormData {
        return $this->data;
    }
    
    /**
     * キーに対応するデータをテキストに変換する。
     * @param string $key
     * @return string
     */
    public function getStringData( string $key ) : string {
        $list = $this->data->getKeyValues($key);
        if ( empty($list) ) {
            return "";
        }
        
        $results = [];
        foreach ( $list as $key => $value ) {
            $results[] = match ( true ) {
                is_array($value) => $this->formatArray($key, $value),
                $value instanceof UploadFile => $this->formatUploadFile($key, $value),
                default => (string)$value
            };
        }
        return implode("\n", $results);
    }
    
    
    public function getValue( string $key ) : string {
        return $this->formatValue($key, $this->data->getKeyValues($key));
    }
    
    
    /**
     * キーに対応する値をデータ別に取得する。
     * @param string $key
     * @param mixed $value
     * @return string
     */
    protected function formatValue( string $key, mixed $value ) : string {
        if ( is_array($value) ) {
            return $this->formatArray($key, $value);
        }
        if ( $value instanceof UploadFile ) {
            return $this->formatUploadFile($key, $value);
        }
        
        return (string)$value;
    }
    
    /**
     * 配列のフォーマットを行う。
     * @param string $key
     * @param array $values
     * @return string
     */
    protected function formatArray( string $key, array $values ) : string {
        $text = [];
        foreach ( $values as $value ) {
            if ( $value instanceof UploadFile ) {
                $text[] = $this->formatUploadFile($key, $value);
            } elseif ( is_array($value) ) {
                $text[] = '[' . $this->formatArray($key, $value) . ']';
            } else {
                $text[] = (string)$value;
            }
        }
        return implode(',', $text);
    }
    
    /**
     * アップロードされたファイルのフォーマットを行う。
     * @param string $key
     * @param UploadFile $value
     * @return string
     */
    protected
    function formatUploadFile( string $key, UploadFile $value ) : string {
        return $value->getName();
    }
}
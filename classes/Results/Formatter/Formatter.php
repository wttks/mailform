<?php

namespace AIJOH\Results\Formatter;

use AIJOH\Results\FormData;
use AIJOH\Util\ArrayUtil;
use AIJOH\Util\StrUtil;

class Formatter extends FormatBase {
    
    protected ?string $formDataText = null;
    
    public function __construct( FormData $data ) {
        parent::__construct($data);
    }
    
    /**
     * FormData内のデータ一覧をテキストに変換したものを取得する。
     * @return string
     */
    public function getFormDataText() : string {
        if ( $this->formDataText === null ) {
            $this->formDataText = $this->createToString();
        }
        return $this->formDataText;
    }
    
    /**
     * FormData内のデータ一覧をテキストに変換する。
     * @return string
     */
    private function createToString() : string {
        $titleManager = $this->data->getTitleManager();
        if ( $titleManager === null ) {
            return "";
        }
        
        $text = [];
        foreach ( $titleManager->getAllTitle() as $key => $titleData ) {
            $values = $this->getStringData($key);
            $output = $titleData['output'] ?? false;
            if( $output === "not_empty" && StrUtil::isEmpty($values) ){
                continue;
            }
            $title = $titleData['title'];
            $text[] = '【' . $title . '】';
            $text[] = $values;
        }
        return implode("\n", $text);
    }
    
    
    /**
     * 指定した文字列の中の置換文字を元にFormDataの値に置換する。
     * @param string $text 元の文字列
     * @return string 置換後の文字列
     */
    public function format( string $text ) : string {
        $results = preg_replace_callback('/(?<!\\\\)\{([^}]+)}/', function( $matches ) {
            $key = $matches[1];
            if ( $key === ":data:" ) {
                return $this->getFormDataText();
            }
            return $this->getStringData($key);
        }, $text);
        return str_replace('\{', '{', $results);
    }
    
    
    public function formatAll(array $data) : array {
        return ArrayUtil::arrayMapRecursive($data, function( $value ) {
            if ( is_string($value) ) {
                return $this->format($value);
            }
            return $value;
        });
    }
    
    
    
    
}
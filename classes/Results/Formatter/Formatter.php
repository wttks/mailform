<?php

namespace AIJOH\Results\Formatter;

use AIJOH\Results\FormData;
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
        $result = [];
        foreach ( $data as $key => $value ) {
            $result[ $key ] = $this->resolveValue($value);
        }
        return $result;
    }


    /**
     * 設定値を再帰的に解決する。下記の形式に対応:
     *   - 文字列              : {placeholder} を展開
     *   - Closure             : $closure($formData) を呼び出し、結果を再評価
     *   - map 配列            : ['field' => 'X', 'map' => [...], 'default' => ...]
     *                           → $formData[X] を引いて map[..] ?? default を再評価
     *   - その他の配列        : 各要素を再帰
     *   - その他              : そのまま返す（MailAddress 等の値オブジェクト）
     */
    private function resolveValue( mixed $value ) : mixed {
        if ( is_string($value) ) {
            return $this->format($value);
        }
        if ( $value instanceof \Closure ) {
            return $this->resolveValue($value($this->data->getData()));
        }
        if ( is_array($value) ) {
            if ( self::isMapSpec($value) ) {
                return $this->resolveValue($this->resolveMap($value));
            }
            $result = [];
            foreach ( $value as $k => $v ) {
                $result[ $k ] = $this->resolveValue($v);
            }
            return $result;
        }
        return $value;
    }


    /**
     * 配列が動的解決マッピング指定（'field' + 'map' + 'default'）かを判定する。
     */
    private static function isMapSpec( array $value ) : bool {
        return isset($value['field'])
            && isset($value['map']) && is_array($value['map'])
            && array_key_exists('default', $value);
    }


    /**
     * map 指定をフォームデータで解決する。
     *   ['field' => 'department', 'map' => ['sales' => '...', ...], 'default' => '...']
     *   → form['department'] の値で map を引き、ヒットしなければ default
     */
    private function resolveMap( array $spec ) : mixed {
        $field = (string) $spec['field'];
        $key = $this->data->getData()[ $field ] ?? null;
        if ( $key !== null && array_key_exists((string)$key, $spec['map']) ) {
            return $spec['map'][ (string)$key ];
        }
        return $spec['default'];
    }

}
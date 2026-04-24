<?php

namespace AIJOH\Validation\Formatter;

use AIJOH\Util\ArrayUtil;

class Formatter {
    
    private array $formatters = [];
    
    /**
     * フォーマッターを生成する。
     * @param array $config
     */
    public function __construct(array $config) {
        $this->init($config);
    }
    
    /**
     * フォーマッターを初期化する。
     * @param array $config
     * @throws \AIJOH\Validation\Exception\ValidationRuleException
     */
    private function init(array $config){
        foreach($config as $key => $param) {
            $formatter = new FormatOne($key, $param['format'] ?? '');
            if( $formatter->exists() ){
                $this->formatters[ $key ] = $formatter;
            }
        }
    }
    
    
    /**
     * データのフォーマットを実施する。
     * @param array $data
     * @return array
     */
    public function format(array $data) : array {
        if(  count($this->formatters) === 0 ) {
            return $data;
        }
        
        $results = $data;
        foreach( $this->formatters as $formatKey => $formatter ) {
            $dataValues = ArrayUtil::getKeyValueList($data,$formatKey);
            foreach($dataValues as $key => $value1 ) {
                ArrayUtil::set($results, $key, $formatter->format($value1));
            }
        }
        return $results;
    }
    
}
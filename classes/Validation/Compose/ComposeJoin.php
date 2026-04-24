<?php

namespace AIJOH\Validation\Compose;

/**
 * 区切り文字で複数フィールドを連結する。任意でゼロパディング指定可能。
 *
 * 使用例（設定ファイル側）:
 *   'compose' => ['join' => ['last_name', 'first_name'], 'separator' => ' ']
 *   'compose' => ['join' => ['tel1', 'tel2', 'tel3'], 'separator' => '-']
 *   'compose' => ['join' => ['y', 'm', 'd'], 'separator' => '-', 'pad' => [4, 2, 2]]
 */
class ComposeJoin extends ComposeBase {

    /** @var string[] */
    private array $fields;

    private string $separator;

    /** @var int[]|null 各要素のゼロパディング桁数。null なら無し。 */
    private ?array $padLengths;


    /**
     * @param string[] $fields 元フィールド名
     * @param string   $separator 区切り文字（デフォルト: 空文字）
     * @param int[]|null $padLengths 各要素のゼロパディング桁数（要素数は $fields と同じ）
     */
    public function __construct( array $fields, string $separator = '', ?array $padLengths = null ) {
        $this->fields     = $fields;
        $this->separator  = $separator;
        $this->padLengths = $padLengths;
    }


    public function getSourceFields() : array {
        return $this->fields;
    }


    public function apply( array $data ) : ?string {
        $parts = [];
        foreach ( $this->fields as $i => $field ) {
            $value = $data[ $field ] ?? '';
            if ( $value === '' || $value === null ) {
                return null;
            }
            $value = (string) $value;
            $padLength = $this->padLengths[ $i ] ?? 0;
            if ( $padLength > 0 ) {
                $value = str_pad($value, $padLength, '0', STR_PAD_LEFT);
            }
            $parts[] = $value;
        }
        return implode($this->separator, $parts);
    }

}

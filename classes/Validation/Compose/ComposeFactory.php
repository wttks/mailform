<?php

namespace AIJOH\Validation\Compose;

/**
 * 設定配列から ComposeBase インスタンスを生成するファクトリ。
 *
 * 設定例:
 *   ['join' => ['last_name', 'first_name'], 'separator' => ' ']
 *   ['join' => ['y', 'm', 'd'], 'separator' => '-', 'pad' => [4, 2, 2]]
 *
 * 配列の最初のキーを compose タイプとして扱い、その値を元フィールド配列とする。
 * 追加オプション（separator, pad など）は同階層の他のキーから取得する。
 */
class ComposeFactory {

    /**
     * @param array $config compose 設定配列
     * @return ComposeBase
     * @throws \InvalidArgumentException 不明なタイプ、または設定が不正な場合
     */
    public static function create( array $config ) : ComposeBase {
        if ( $config === [] ) {
            throw new \InvalidArgumentException("compose 設定が空です。");
        }

        $type = array_key_first($config);
        $fields = $config[ $type ];

        if ( ! is_array($fields) ) {
            throw new \InvalidArgumentException("compose の元フィールドは配列で指定してください。");
        }

        return match ( $type ) {
            'join' => new ComposeJoin(
                $fields,
                $config['separator'] ?? '',
                $config['pad'] ?? null,
            ),
            default => throw new \InvalidArgumentException("不明な compose タイプ: {$type}"),
        };
    }

}

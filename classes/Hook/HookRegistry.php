<?php

namespace AIJOH\Hook;

/**
 * Hook（イベント）の登録・発火を管理するレジストリ。
 *
 * 設置者は 3 つの経路で Hook を登録できる:
 *   1. config の 'hooks' セクション（Form::__construct で自動登録）
 *   2. plugins/*.php ディレクトリ（PluginLoader で自動登録）
 *   3. $form->on($event, $listener)（動的登録）
 *
 * すべて同じ HookRegistry に集約され、dispatch 時に登録順で発火する。
 *
 * 2 種類の発火 API:
 *   - dispatch(): 通知のみ（戻り値無視）
 *   - filter(): 戻り値で値を順次変換（フィルタチェイン）
 *
 * 中止が必要な hook 内では例外を throw すれば呼び出し元へ伝播する
 * （ValidationException / SendException / Form::abortWithErrors() など既存パターン）。
 */
class HookRegistry {

    /** @var array<string, list<callable>> */
    private array $listeners = [];


    /**
     * Hook リスナーを登録する。
     *
     * @param string $event Hook 名（'after_send' など）
     * @param callable $listener Hook 発火時に呼ばれる関数
     * @return self
     */
    public function on( string $event, callable $listener ) : self {
        $this->listeners[ $event ][] = $listener;
        return $this;
    }


    /**
     * Hook を発火する（通知のみ、戻り値は無視）。
     *
     * @param string $event Hook 名
     * @param mixed ...$args リスナーに渡す引数
     */
    public function dispatch( string $event, mixed ...$args ) : void {
        foreach ( $this->listeners[ $event ] ?? [] as $listener ) {
            $listener(...$args);
        }
    }


    /**
     * Hook をフィルタチェインとして発火する。
     * 各リスナーの戻り値を次のリスナーの第 1 引数に渡す（map / fold パターン）。
     *
     * リスナーが null を返した場合は値を変更せず元の値を維持する
     * （加工しないリスナーが書きやすいように）。
     *
     * @param string $event Hook 名
     * @param mixed $value 初期値（フィルタチェインの第 1 引数）
     * @param mixed ...$args 追加の引数（毎回同じものが渡される）
     * @return mixed フィルタ後の値
     */
    public function filter( string $event, mixed $value, mixed ...$args ) : mixed {
        foreach ( $this->listeners[ $event ] ?? [] as $listener ) {
            $result = $listener($value, ...$args);
            if ( $result !== null ) {
                $value = $result;
            }
        }
        return $value;
    }


    /**
     * リスナー登録を解除する（テスト用）。
     *
     * @param string|null $event 指定したらその event のみ、null なら全部
     */
    public function clear( ?string $event = null ) : void {
        if ( $event === null ) {
            $this->listeners = [];
            return;
        }
        unset($this->listeners[ $event ]);
    }


    /**
     * 指定 event のリスナー数を返す（デバッグ・テスト用）。
     */
    public function count( string $event ) : int {
        return count($this->listeners[ $event ] ?? []);
    }


    /**
     * 登録されている event 名の一覧を返す。
     * @return string[]
     */
    public function events() : array {
        return array_keys($this->listeners);
    }
}

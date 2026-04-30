<?php

namespace AIJOH\Hook;

/**
 * mailform プラグインのインターフェース。
 *
 * `lib/mailform/config/plugins/*.php` または `'plugin_dirs'` で指定された
 * ディレクトリ配下の PHP ファイルが、このインターフェースを実装した
 * オブジェクトを `return` することで、PluginLoader によって自動登録される。
 *
 * 例:
 * ```php
 * <?php
 * // lib/mailform/config/plugins/01_slack_notify.php
 * return new class implements \AIJOH\Hook\MailformPlugin {
 *     public function register( \AIJOH\Hook\HookRegistry $hooks ) : void {
 *         $hooks->on('after_send', fn( $formData ) => $this->notifySlack($formData));
 *     }
 *     private function notifySlack( $formData ) : void { ... }
 * };
 * ```
 */
interface MailformPlugin {

    /**
     * Hook を HookRegistry に登録する。
     *
     * @param HookRegistry $hooks 登録先のレジストリ
     */
    public function register( HookRegistry $hooks ) : void;
}

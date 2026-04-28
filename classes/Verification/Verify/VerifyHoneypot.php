<?php

namespace AIJOH\Verification\Verify;

use AIJOH\Http\Post;
use AIJOH\Util\HtmlUtil;

/**
 * Honeypot（ハニーポット）によるボット検知。
 *
 * 人間には見えない hidden フィールドをフォームに紛れ込ませ、
 * 送信時にそのフィールドに値が入っていればボットと判定する。
 *
 * 仕組み:
 *   - form タグに input を出力（CSS で非表示・tab対象外・autocomplete無効）
 *   - 通常のユーザは入力しない
 *   - ボットは form 内の全 input に値を入れがちなので検知される
 *
 * 使用例:
 *   'verify' => [
 *       'csrfToken',
 *       'honeypot',                          // デフォルト name="_honeypot"
 *       'honeypot' => ['name' => 'website'], // name をカスタマイズ
 *   ]
 */
class VerifyHoneypot extends VerifyBase {

    /** @var string hidden input の name 属性 */
    private string $name = '_honeypot';


    public function __construct( array $config = [] ) {
        if ( ! empty($config['name']) ) {
            $this->name = $config['name'];
        }
    }


    /**
     * フォーム部分の HTML を返す。
     * 視覚的にも支援技術的にも非表示にし、tab 移動・自動入力からも除外する。
     */
    public function form() : string {
        $name = HtmlUtil::escape($this->name);
        return "<div style='position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;' aria-hidden='true'>"
            . "<input type='text' name='{$name}' value='' tabindex='-1' autocomplete='off'>"
            . "</div>";
    }


    /**
     * 入力の検証を行う。honeypot に値が入っていれば false（ボット）。
     */
    public function verify() : bool {
        $value = Post::getInstance()->get($this->name, '');
        return $value === '';
    }


    public function getErrorMessage() : string {
        return "不正な送信を検知しました。";
    }

}

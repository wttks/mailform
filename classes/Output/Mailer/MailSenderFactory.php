<?php

namespace AIJOH\Output\Mailer;

/**
 * MailSendBase 実装の生成 Factory。
 *
 * 設置者が `'mailer' => ['type' => 'phpmailer']` を指定して送信実装を
 * 切り替えられるようにする extension point。当面は phpmailer のみ実装。
 *
 * 将来追加候補（必要になったら実装）:
 * - 'symfony'  : Symfony Mailer ラッパ
 * - 'wp_mail'  : WordPress プラグイン化時の wp_mail() ラッパ
 * - 'null'     : 送信せずキャプチャするテスト用 mock
 *
 * 設置者が独自実装を差し込みたい場合は register() を使ってクラスを登録できる。
 */
final class MailSenderFactory {

    /**
     * type 名 → ファクトリ Closure のマップ。
     * @var array<string, callable(): MailSendBase>
     */
    private static array $factories = [];


    /**
     * 設定に応じた MailSender を生成して返す。デフォルトは PHPMailer。
     *
     * @param array $config 'mailer' セクション配下の設定（'type' => '...'）
     * @return MailSendBase
     * @throws \InvalidArgumentException 未対応の type 指定
     */
    public static function create( array $config = [] ) : MailSendBase {
        $type = $config['type'] ?? 'phpmailer';

        // 設置者が独自実装を register していればそれを優先
        if ( isset(self::$factories[ $type ]) ) {
            return ( self::$factories[ $type ] )();
        }

        return match ( $type ) {
            'phpmailer' => new PHPMailSender(),
            default     => throw new \InvalidArgumentException(
                "未対応の mailer.type: '{$type}'。'phpmailer' か "
                . "MailSenderFactory::register() で独自実装を登録してください。"
            ),
        };
    }


    /**
     * 独自の MailSender 実装を type 名で登録する。
     * テスト時の mock 注入や、設置者カスタム実装の差し込みに使う。
     *
     * @param string $type 'symfony' / 'null' / 'custom_xxx' 等
     * @param callable $factory MailSendBase インスタンスを返す Closure
     */
    public static function register( string $type, callable $factory ) : void {
        self::$factories[ $type ] = $factory;
    }


    /**
     * register された全カスタム実装を解除（主にテスト用）。
     */
    public static function reset() : void {
        self::$factories = [];
    }


    /**
     * 利用可能な type 一覧（ConfigValidator 用）。
     * @return string[]
     */
    public static function availableTypes() : array {
        return array_unique(array_merge(
            [ 'phpmailer' ],
            array_keys(self::$factories),
        ));
    }
}

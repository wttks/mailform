<?php

namespace AIJOH\Sender;

use AIJOH\Hook\HookRegistry;
use AIJOH\Output\Mailer\MailAddressParser;
use AIJOH\Output\Mailer\SendMailException;
use AIJOH\Results\Formatter\FormatBase;
use AIJOH\Sender\Send\Send;
use AIJOH\Util\StrUtil;
use AIJOH\Validation\Exception\ValidationException;

class Sender {
    
    private static array $sendClassMap = [
        '管理者向けメール' => 'AIJOH\Sender\Send\SendMailAdmin',
        '顧客向けメール'   => 'AIJOH\Sender\Send\SendMailUser',
    ];
    
    
    /**
     * @var Send[]
     */
    private array $sendList = [];

    /**
     * 1 リクエスト内で送信できる宛先の合計上限（to + cc + bcc を全 SendMail で合算）。
     * Email Pumping 対策: 攻撃者が動的宛先で大量送信を試みるのを物理的に阻止する。
     */
    private int $maxRecipientsPerRequest;

    /** @var HookRegistry|null 各 SendMail に伝播する Hook レジストリ */
    private ?HookRegistry $hooks = null;


    /**
     * データを送信するためのクラス
     * @param array $configAll
     *   各キーは SendMail（'管理者向けメール' 等）の設定。
     *   特殊キー 'max_recipients_per_request' が含まれていれば上限値として扱う（デフォルト 10）。
     * @throws SendException
     */
    public function __construct( array $configAll ) {
        // 設定値を抽出して送信設定からは除外
        $this->maxRecipientsPerRequest = (int) ( $configAll['max_recipients_per_request'] ?? 10 );
        unset($configAll['max_recipients_per_request']);

        foreach ( $configAll as $key => $config ) {
            try {
                $this->sendList[ $key ] = $this->buildSendClass($key, $config);
            } catch ( ValidationException $ve ) {
                $className = $this->getClassName($key);
                // 詳細はサーバログに、クライアントには汎用メッセージのみ
                error_log("[Sender] {$className} の設定が不正: " . print_r($ve->getErrors(), true));
                throw new SendException($className, "送信設定にエラーがあります。サーバ管理者にお問い合わせください。");
            } catch ( \Exception $e ) {
                $className = $this->getClassName($key);
                error_log("[Sender] {$className} の設定が不正: " . $e->getMessage());
                throw new SendException($className, "送信設定にエラーがあります。サーバ管理者にお問い合わせください。");
            }
        }
    }


    protected function buildSendClass( string $key, array $config ) : Send {
        $className = $this->getClassName($key);
        $send = new $className();
        $send->setConfig($config);
        return $send;
    }
    
    
    protected function getClassName( string $key ) : string {
        $className = self::$sendClassMap[ $key ] ?? '';
        if ( $className !== "" ) {
            return $className;
        }
        return 'AIJOH\Sender\Send\Send' . ucfirst(StrUtil::toCamelCase($key));
    }
    
    /**
     * format 済み config から to + cc + bcc の合計宛先数を見積もる。
     * Email Pumping 攻撃の前段検出用。
     *
     * @param array $configAll Formatter で動的宛先解決済みの sender config
     */
    public static function countRecipients( array $configAll ) : int {
        $count = 0;
        foreach ( $configAll as $key => $config ) {
            if ( $key === 'max_recipients_per_request' ) {
                continue;
            }
            if ( ! is_array($config) ) {
                continue;
            }
            foreach ( [ 'to', 'cc', 'bcc' ] as $field ) {
                if ( ! isset($config[ $field ]) ) {
                    continue;
                }
                try {
                    $addresses = MailAddressParser::parse($config[ $field ]);
                    $count += count($addresses);
                } catch ( SendMailException $e ) {
                    // パース失敗は別の検証で弾かれるはず、ここでは計上しない
                }
            }
        }
        return $count;
    }


    /**
     * 設定された上限を超える宛先数になっていないか検証する。
     * @throws SendException 上限超過
     */
    private function assertRecipientLimit( array $configAll ) : void {
        $count = self::countRecipients($configAll);
        if ( $count > $this->maxRecipientsPerRequest ) {
            error_log("[Sender] recipient limit exceeded: {$count} > {$this->maxRecipientsPerRequest} (Email Pumping 対策)");
            throw new SendException(
                'Sender',
                "送信先が上限を超えています。フォーム設定を確認してください。"
            );
        }
    }


    /**
     * Hook レジストリを設定する。各 SendMail に伝播し、before_admin_send /
     * before_user_send 等の hook を発火可能にする。
     */
    public function setHookRegistry( HookRegistry $hooks ) : self {
        $this->hooks = $hooks;
        foreach ( $this->sendList as $send ) {
            if ( method_exists($send, 'setHookRegistry') ) {
                $send->setHookRegistry($hooks);
            }
        }
        return $this;
    }


    /**
     * データの送信を行う。
     * @param FormatBase $format
     * @return void
     */
    public function sendAll( FormatBase $format ) {
        // 動的宛先解決後の合計宛先数で Email Pumping 対策
        $configAll = [];
        foreach ( $this->sendList as $key => $send ) {
            $configAll[ $key ] = $send->getConfig();
        }
        $this->assertRecipientLimit($configAll);

        foreach ( $this->sendList as $key => $send ) {
            $send->setFormat($format);
            try {
                if ( $send->send() ) {
                    // 送信に成功した場合は、ログに出力する。
                    error_log($key . "の処理が完了しました。");
                } else {
                    // 送信に失敗した場合は、ログに出力する。
                    error_log($key . "の処理に失敗しました。");
                }
            } catch ( \Exception $e ) {
                // 送信に失敗した場合は、ログに出力する。
                $class = get_class($send);
                error_log($key . "の処理で例外が発生しました。クラス: {$class} 例外：" . $e->getMessage());
                throw new \Exception($key . "の処理で例外が発生しました。クラス: {$class} 例外：" . $e->getMessage());
            }
        }
        
    }
}
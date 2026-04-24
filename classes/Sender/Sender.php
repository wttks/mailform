<?php

namespace AIJOH\Sender;

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
     * データを送信するためのクラス
     * @param array $configAll
     * @throws SendException
     */
    public function __construct( array $configAll ) {
        foreach ( $configAll as $key => $config ) {
            try {
                $this->sendList[ $key ] = $this->buildSendClass($key, $config);
            }catch(ValidationException $ve){
                $className = $this->getClassName($key);
                $errors = print_r($ve->getErrors(), true);
                throw new SendException($className,$className. "の設定が不正です。エラー：" . $errors);
            } catch ( \Exception $e ) {
                $className = $this->getClassName($key);
                error_log($className . "の設定が不正です。エラー：" . $e->getMessage());
                throw new SendException($className,$className . "の設定が不正です。エラー：" . $e->getMessage());
            }
        }
    }
    
    
    protected function buildSendClass( string $key, array $config ) : Send {
        $className = $this->getClassName($key);
        error_log("sender class name: $className\n");
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
     * データの送信を行う。
     * @param FormatBase $format
     * @return void
     */
    public function sendAll( FormatBase $format ) {
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
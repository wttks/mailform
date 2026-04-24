<?php

namespace AIJOH\Form;

use AIJOH\Http\Request;
use AIJOH\Http\Response;
use AIJOH\Results\Formatter\Formatter;
use AIJOH\Sender\Sender;
use AIJOH\Validation\Validation;
use AIJOH\Verification\Verification;
use AIJOH\Validation\Exception\ValidationException;

use \AIJOH\Sender\SendException;

class Form implements FormBase {
    
    /**
     * ベリファイの取得を行う。
     * @var Verification
     */
    private $verification;
    
    private $validationConfig;
    
    
    private $sendConfig;
    
    private $sender;
    
    public function __construct( $config ) {
        $this->verification = new Verification($config['verify'] ?? []);
        $this->validationConfig = $config['validation'] ?? [];
        $this->sendConfig = $config['sender'] ?? [];
    }
    
    
    public function getHeaderTag() : string {
        return $this->verification->getHeaderTag();
    }
    
    
    public function getFormTag() : string {
        return $this->verification->getFormTag();
    }
    
    
    public function getFooterTag() : string {
        return $this->verification->getFooterTag();
    }
    
    
    public function receive() {
        $request = Request::getInstance();
        if ( ! $request->isPost() ) {
            return;
        }
        
        $verifyMessage = $this->verification->verify();
        if ( $verifyMessage !== true ) {
            Response::jsonResults(false, $verifyMessage, []);
            return;
        }
        
        try {
            $formData = $request->validateForm($this->validationConfig);
            $formatter = new Formatter($formData);
            $sendConfig = $formatter->formatAll($this->sendConfig);
            $sender = new Sender($sendConfig);
            $sender->sendAll($formatter);
            Response::jsonResults(true, '送信が完了しました。');
        } catch ( SendException $se ) {
            Response::jsonResults(false, $se->getMessage());
        } catch ( ValidationException $e ) {
            $errors = $e->getErrors();
            Response::jsonResults(false, 'フォームのチェックに失敗しました。', $errors);
        }
    }
    
    
}
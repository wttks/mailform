<?php

namespace AIJOH\Verification\Verify;

use AIJOH\Validation\Validation;
use AIJOH\Verification\Verify\VerifyBase;

class VerifyReCaptcha extends VerifyBase {
    
    private VerifyBase $verify;
    
    /**
     * VerifyReCaptcha constructor.
     * @param $options
     */
    public function __construct( $options = [] ) {
        $version = $options['version'] ?? 'v2';
        $this->verify = $this->loadVerify($version, $options);
        
    }
    
    /**
     * ReCaptchaのバージョンによって、Verifyクラスを読み込む
     * @param $version
     * @param array $options
     * @return \AIJOH\Verification\Verify\VerifyBase
     * @throws \AIJOH\Validation\Exception\ValidationException
     */
    private function loadVerify( $version, array $options ) : VerifyBase {
        return new VerifyReCaptchaV2($options);
    }
    
    
    /**
     * @return string
     */
    public function header() : string {
        return $this->verify->header();
    }
    
    
    public function form() : string {
        return $this->verify->form();
    }
    
    public function footer() : string {
        return $this->verify->footer();
    }
    
    
    public function verify() : bool {
        return $this->verify->verify();
    }
    
    
    public function getErrorMessage() : string {
        return $this->verify->getErrorMessage();
    }
}


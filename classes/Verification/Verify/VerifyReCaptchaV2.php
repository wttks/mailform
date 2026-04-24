<?php

namespace AIJOH\Verification\Verify;

use AIJOH\Util\HtmlUtil;
use AIJOH\Validation\Validation;
use AIJOH\Validation\Validator\Validator;

class VerifyReCaptchaV2 extends VerifyBase {
    
    use ReCaptchaVerify;
    
    /**
     * サイトキー
     * @var string
     */
    private string $siteKey;
    
    private string $secretKey;
    
    
    /**
     * バリデーションルール
     * @var array|string[]
     */
    private static array $validation = [
        'site_key'   => [
            'rule' => 'required|string',
        ],
        'secret_key' => [
            'rule' => 'required|string',
        ],
    ];
    
    
    /**
     * コンストラクタ
     * @param $options
     * @throws \AIJOH\Validation\Exception\ValidationException
     */
    public function __construct( $options = [] ) {
        $options = $this->validate($options);
        $this->siteKey = $options['site_key'];
        $this->secretKey = $options['secret_key'];
    }
    
    /**
     * オプションのバリデーションを行う。
     * @return array
     * @throws \AIJOH\Validation\Exception\ValidationException
     */
    public function validate( $options ) : array {
        $validator = new Validator(self::$validation);
        return $validator->validate($options);
    }
    
    
    /**
     * ヘッダーに表示するタグを取得する。
     * @return string
     */
    public function header() : string {
        return <<< __END__
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        grecaptcha.reset();
    });
});
</script>
__END__;
    }
    
    
    public function form() : string {
        return '<div class="recaptcha_area"><div class="g-recaptcha" data-sitekey="' . HtmlUtil::escape($this->siteKey) . '"></div></div>';
    }
    
    /**
     * reCaptchaV2のチェックを行う。
     * @return bool
     */
    public function verify() : bool {
        return $this->verifySuccess();
    }
    
    
    public function getErrorMessage() : string {
        return 'reCaptchaのチェックに失敗しました。';
    }
}
<?php
namespace Bitlama\Miscellaneous;

use Aura\Filter\AbstractRule;

class CaptchaCorrect extends AbstractRule
{
    protected $message_map = [
        'failure_is'            => 'FILTER_RULE_CAPTCHA_NOT_CORRECT',
        'failure_is_not'        => '',
        'failure_is_blank_or'   => '',
        'failure_fix'           => '',
        'failure_fix_blank_or'  => '',
    ];

    public function setApp($app)
    {
        $this->app = $app;
    }

    public function validate()
    {
        $response = $this->app->captcha->check();
        return $response->isValid();
    }

    public function sanitize($max = null)
    {
        return true;
    }

    public function getMessages()
    {
        return [
            "FILTER_RULE_CAPTCHA_NOT_CORRECT" => "Captcha is not correct."
            ];
    }
}

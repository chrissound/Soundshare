<?php
namespace Bitlama\Miscellaneous;

use Aura\Filter\AbstractRule;

class UsernameAvailable extends AbstractRule
{
    protected $message_map = [
        'failure_is'            => 'FILTER_RULE_USERNAME_NOT_AVAILABLE',
        'failure_is_not'        => '',
        'failure_is_blank_or'   => '',
        'failure_fix'           => '',
        'failure_fix_blank_or'  => '',
    ];

    public function setApp($app)
    {
        $this->app = $app;
    }

    public function validate($userAlias)
    {
        $userModel = call_user_func($this->app->model, 'user');
        return !($userModel->exists(['alias' => $userAlias]));
    }

    public function sanitize($max = null)
    {
        return true;
    }

    public function getMessages()
    {
        return [
            "FILTER_RULE_USERNAME_NOT_AVAILABLE" => "Username chosen is already taken."
            ];
    }
}

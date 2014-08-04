<?php
namespace Bitlama\Miscellaneous;

use Aura\Filter\AbstractRule;

class Alphanumspace extends \Aura\Filter\Rule\Alnum 
{
    protected $message_map = [
        'failure_is'            => 'FILTER_RULE_IS_ALPHANUM',
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
        $value = $this->getValue();

        $words = explode(" ", trim($value));
        foreach($words as $word)
        {
            if (!$this->app->filter->value($word, \Aura\Filter\RuleCollection::IS, 'alnum'))
            {
                return false;
            }
        }

        return true;
    }

    public function sanitize($max = null)
    {
        return true;
    }

    public function getMessages()
    {
        return [
            "FILTER_RULE_IS_ALPHANUM" => "Please use alphnumeric and space characters only."
            ];
    }
}

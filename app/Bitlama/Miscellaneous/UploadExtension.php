<?php
namespace Bitlama\Miscellaneous;

use Aura\Filter\AbstractRule;

class UploadExtension extends AbstractRule
{
    protected $message_map = [
        'failure_is'            => 'UPLOAD_EXTENSION_NOT_VALID',
        'failure_is_not'        => '',
        'failure_is_blank_or'   => '',
        'failure_fix'           => '',
        'failure_fix_blank_or'  => '',
    ];

    public function setApp($app)
    {
        $this->app = $app;
    }

    public function validate($extensions)
    {
        $file = $this->getValue();
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

        return in_array($extension, $extensions);
    }

    public function sanitize($max = null)
    {
        return true;
    }

    public function getMessages()
    {
        return [
            "UPLOAD_EXTENSION_NOT_VALID" => "File extension not valid."
            ];
    }
}

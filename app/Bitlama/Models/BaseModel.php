<?php
namespace Bitlama\Models;

class BaseModel extends \RedBean_SimpleModel {

    protected $app;

    public function __construct() {
    }

    public function setApp($app)
    {
        $this->app = $app;
    }
}

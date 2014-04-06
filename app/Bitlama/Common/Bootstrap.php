<?php

namespace Bitlama\Common;
use RedBean_Facade as R;

class Bootstrap {

    static public $appPath;
    static public $soundsPageSize;

    static public function Init($appPath)
    {
        self::$appPath = $appPath;
        self::$soundsPageSize = 1;
    }

}

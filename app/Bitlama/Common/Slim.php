<?php

namespace Bitlama\Common;

class Slim {

    static $app;

    static public function urlFor($route, $params)
    {
        return \Bitlama\Common\Config::projectUrl . self::$app->urlFor($route, $params);
    }

}

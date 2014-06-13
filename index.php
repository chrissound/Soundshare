<?php
session_cache_limiter(false);
session_start();
require 'vendor/autoload.php';

function el($var, $annotation = '')
{
    if (!empty($annotation))
    error_log($annotation);
    error_log(var_export($var, true));
}


class SeriouslyBad extends Exception {
}
class ConversionNotFinished extends Exception {
}
class NotLoggedIn extends Exception {
}
class NoSourceFileFound extends Exception {
}
class InvalidMimeType extends Exception {
}
class InvalidInt extends Exception {
}
class InvalidArgument extends Exception {
}

// Not too sure whats the best way to handle this. Guess a static class would be easiest to change later on though.
class LogWriter {
    static $app;

    public static function debug($args)
    {
        foreach((array)$args as $arg)
        {
            //call_user_func_array(array(self::$app->log, "debug"), (array)$arg);
        }
    }

    public static function info($args)
    {
        foreach((array)$args as $arg)
            call_user_func_array(array(self::$app->log, "info"), (array)$arg);
    }

    public static function notice($args)
    {
        foreach((array)$args as $arg)
            call_user_func_array(array(self::$app->log, "notice"), (array)$arg);
    }

    public static function warning($args)
    {
        foreach((array)$args as $arg)
            call_user_func_array(array(self::$app->log, "warning"), (array)$arg);
    }
}



// Slim
$appConfigSettings = [
    'view' => new \Slim\Views\Twig(),
    'templates.path' => './app/Templates',
];

if (isset(\Bitlama\Common\Config::log) AND file_exists(\Bitlama\Common\Config::log))
{
    $logWriter = new \Slim\LogWriter(fopen(\Bitlama\Common\Config::log, 'a'));
    $appConfigSettings = array_merge($appConfigSettings, [
        'log.enabled' => true,
        'log.writer' => $logWriter
    ]);
}


$app = new \Slim\Slim($appConfigSettings);

$app->log->setLevel(\Slim\Log::DEBUG);
LogWriter::$app = $app;


$app->view->parserExtensions = [
    new Twig_Extension_Debug()
];
$app->view->parserOptions = [
    'debug' => true
];


// RedBean
class MyModelFormatter implements RedBean_IModelFormatter {
    public function formatModel($model) {
        return '\\'.'Bitlama'.'\\'.'Models'.'\\'.ucfirst($model);
    }
}
use RedBean_Facade as R;
R::setup(
    Bitlama\Common\Config::dbEngine.':host='.Bitlama\Common\Config::dbHost.';dbname='.Bitlama\Common\Config::dbName,
    Bitlama\Common\Config::dbUser,
    Bitlama\Common\Config::dbPassword);
$formatter = new MyModelFormatter;
RedBean_ModelHelper::setModelFormatter($formatter);
$app->container->singleton('datasource', function(){
    $fml = new R();
    return $fml;
});
$app->model = $app->container->protect(function($model) use($app) {
    
   $modelInstance =  $app->datasource->dispense($model);
   $modelInstance->setApp($app);
   return $modelInstance;
});
$app->filterRule = $app->container->protect(function($rule) use ($app) {
    $bob = new Bitlama\Miscellaneous\UsernameAvailable();
    $filterRuleInstance = "\\Bitlama\\Miscellaneous\\".$rule;
    $filterRuleInstance = new $filterRuleInstance;
    $filterRuleInstance->setApp($app);

    $translator = $app->filter->getTranslator();
    foreach($filterRuleInstance->getMessages() as $key => $message)
    {
        $translator->set($key, $message);
    }

    return $filterRuleInstance;
});

// Aura Filter
$app->filter = require "vendor/aura/filter/scripts/instance.php";


// Bitlama  
\Bitlama\Common\Bootstrap::Init(__DIR__.'/', $app);
$controllers = [ 
    new \Bitlama\Controllers\Admin\Controller($app),
    new \Bitlama\Controllers\User\Controller($app),
    new \Bitlama\Controllers\Home($app),
    new \Bitlama\Controllers\BackgroundProcessing($app),
];

foreach($controllers as $controller)
    $controller->setRoutes();

$app->run();


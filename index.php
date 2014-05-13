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

// Not too sure whats the best way to handle this. Guess a static class would be easiest to change later on though.
class LogWriter {
    static $app;
    public static function debug($args)
    {
        call_user_func_array(array($app->debug, "log"), $args);
    }
}



// Slim
$logWriter = new \Slim\LogWriter(fopen('/home/webroot/logs/bitlama_errors.log', 'a'));
$app = new \Slim\Slim([
    'view' => new \Slim\Views\Twig(),
    'templates.path' => './app/Templates',
    'log.enabled' => true,
    'log.writer' => $logWriter
]);

LogWriter::$app = $app;


$app->log->setLevel(\Slim\Log::DEBUG);

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

// Aura Filter
$app->filter = require "vendor/aura/filter/scripts/instance.php";


// Bitlama  
\Bitlama\Common\Bootstrap::Init(__DIR__.'/');
$controllers = [ 
    new \Bitlama\Controllers\Admin\Controller($app),
    new \Bitlama\Controllers\User\Controller($app),
    new \Bitlama\Controllers\Home($app),
    new \Bitlama\Controllers\BackgroundProcessing($app)
];

foreach($controllers as $controller)
    $controller->setRoutes();

$app->run();


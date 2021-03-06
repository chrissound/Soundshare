<?php
$start = microtime(true);
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
        if (is_string($args))
        {
            call_user_func_array(array(self::$app->log, "debug"), (array)$args);
            return;
        }

        foreach($args as &$arg)
            $arg = !is_string($arg) ? var_export($arg, true): $arg;

        foreach((array)$args as $arg)
        {
            call_user_func_array(array(self::$app->log, "debug"), (array)$arg);
        }
    }

    public static function info($args)
    {
        foreach($args as &$arg)
            $arg = !is_string($arg) ? var_export($arg, true): $arg;

        foreach((array)$args as $arg)
            call_user_func_array(array(self::$app->log, "info"), (array)$arg);
    }

    public static function notice($args)
    {
        foreach($args as &$arg)
            $arg = !is_string($arg) ? var_export($arg, true): $arg;

        foreach((array)$args as $arg)
            call_user_func_array(array(self::$app->log, "notice"), (array)$arg);
    }

    public static function warning($args)
    {
        foreach($args as &$arg)
            $arg = !is_string($arg) ? var_export($arg, true): $arg;

        foreach((array)$args as $arg)
            call_user_func_array(array(self::$app->log, "warning"), (array)$arg);
    }
}

date_default_timezone_set('Europe/London');

// Slim
$appConfigSettings = [
    'view' => new \Slim\Views\Twig(),
    'templates.path' => './app/Templates',
];

if (!empty(\Bitlama\Common\Config::log) AND file_exists(\Bitlama\Common\Config::log))
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
if (true)
{
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

    $di = new RedBean_DependencyInjector;
    RedBean_ModelHelper::setDependencyInjector( $di );

    $di->addDependency('app', $app);
}

// Aura Filter
if (true)
{
    $app->filterInstance = function(){
        $value = require "vendor/aura/filter/scripts/instance.php";
        return $value;
    };

    $app->filter = require "vendor/aura/filter/scripts/instance.php";
    $app->filterRule = $app->container->protect(function($rule) use ($app) {
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

    $app->filterRuleInstance = $app->container->protect(function($rule, $translator) use ($app) {
        $filterRuleInstance = "\\Bitlama\\Miscellaneous\\".$rule;
        $filterRuleInstance = new $filterRuleInstance;
        $filterRuleInstance->setApp($app);

        foreach($filterRuleInstance->getMessages() as $key => $message)
        {
            $translator->set($key, $message);
        }

        return $filterRuleInstance;
    });

    $locator = $app->filter->getRuleLocator();
    $locator->set('usernameAvaliable', function () use($app) {
        $rule = call_user_func($app->filterRule, 'UsernameAvailable');
        return $rule;
    });
    $locator->set('captcha', function () use($app) {
        $rule = call_user_func($app->filterRule, 'CaptchaCorrect');
        return $rule;
    });
    $locator->set('alphanumspace', function () use($app) {
        $rule = call_user_func($app->filterRule, 'Alphanumspace');
        return $rule;
    });
}

$captcha = new Captcha\Captcha();
$captcha->setPublicKey(\Bitlama\Common\Config::recaptchaPublicKey);
$captcha->setPrivateKey(\Bitlama\Common\Config::recaptchaPrivateKey);
$captcha->setTheme('clean');
$app->captcha = $captcha;




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

LogWriter::debug("Time taken for request: ". round((microtime(true) - $start), 3) ."s");

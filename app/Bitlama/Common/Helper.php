<?php

namespace Bitlama\Common;

class Helper {

    static public function redirect($url, $app)
    {
        $app->redirect(\Bitlama\Common\Helper::getUrl($url, null));
        $app->stop();
        die();
    }

    static public function getPreviousFormValues()
    {
        return isset($_SESSION['slim.flash']['fields']) ? $_SESSION['slim.flash']['fields'] : array();
    }

    static public function getMessages()
    {
        return isset($_SESSION['slim.flash']['messages']) ? $_SESSION['slim.flash']['messages'] : array();
    }

    static public function getAllRoutes($app)
    {
    }

    static public function getInterfaceList($title, $headers, $records)
    {
        return [
            'title' =>      $title,
            'headers' =>    $headers,
            'records' =>    $records
        ];
    }

    // This should actually be done by SLIM 
    static public function render($template, $data, $app)
    {
        $app->view->setData($data);
        return $app->view->fetch($template);
    }

    static public function paginate($recordCount, callable $pageLink, $pageCurrent, $pageSize = 15)
    {
        $pages = [];   
        $pageCount = ceil($recordCount / $pageSize);
        for ($i = 1; $i <= $pageCount; $i++)
        {
            $page = [];
            $page['link'] = $pageLink($i);;
            $page['title'] = $i;
            if ($i == $pageCurrent)
                $page['active'] = true;
            $pages[] = $page;
        }

        return $pages;
    }

    static public function getUrl($url, $getValue = null)
    {
        if ($getValue)
            return $url."?".http_build_query((array)$getValue);
        else
            return $url;
    }

    static public function generateRandomString($length = 16)
    {
        if (!is_int($length))
            throw new \InvalidInt();

        $charBucket = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $charBucketLength = strlen($charBucket);

        $string = '';
        for($i=0;$i<$length;$i++)
            $string .= $charBucket[rand(0,$charBucketLength-1)];

        return $string;
    }

    static public function sendEmail($to, $toAddress, $subject, $htmlMessage, $message, $app)
    {
        try {
            $emailRecord = call_user_func($app->model, 'email');
            $emailRecord->to            = $to;
            $emailRecord->subject       = $subject;
            $emailRecord->htmlMessage   = $htmlMessage;
            $emailRecord->message       = $message;
            $emailRecord->success       = $message;
            $app->datasource->store($emailRecord);

            $transport = \Swift_SmtpTransport::newInstance(\Bitlama\Common\Config::emailHost, \Bitlama\Common\Config::emailPort)
              ->setUsername(\Bitlama\Common\Config::emailUsername)
              ->setPassword(\Bitlama\Common\Config::emailPassword);

            $mailer = \Swift_Mailer::newInstance($transport);
            $message = \Swift_Message::newInstance()
                ->setSubject($subject)
                ->setFrom(array(\Bitlama\Common\Config::emailFromAddress => \Bitlama\Common\Config::emailFrom))
                ->setTo(array($toAddress => $to))
                ->setBody($message)
                ->addPart($htmlMessage, 'text/html')
                ->setReturnPath(\Bitlama\Common\Config::emailFromAddress);
                
            $emailRecord->success = $mailer->send($message);

            $app->datasource->store($emailRecord);
        } catch (Exception $e)
        {
            \LogWriter::debug($e); 
        }
    }

    static public function implodeIndexed(array $array, array $indexes, $separator = '')
    {
        if(!is_string($separator))
            throw new \InvalidArgument();

        $values = array(); 
        foreach ($indexes as $index)
            $values[] = $array[$index];

        return implode($separator, $values);
    }

    static public function renderPage($title, $contentCollections, $commonViewData)
    {
        $viewBase = [
            'title' => $title,
            'content' => implode("", $contentCollections)
        ]; 
        $viewBase = array_merge_recursive($viewBase, $commonViewData);
        $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);

        $controller->booom($viewRenderedBase);
    }

}

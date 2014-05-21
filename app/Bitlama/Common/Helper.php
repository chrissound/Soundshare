<?php

namespace Bitlama\Common;

class Helper {

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

    static public function session($key)
    {
        if (isset($_SESSION[$key]))
            return $_SESSION[$key];
        else
            return null;
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
}

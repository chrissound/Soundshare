<?php

namespace Bitlama\Controllers;

class BaseController {
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    protected function Booom($output)
    {
        // Remove bitlamas flash session
        if (isset($_SESSION['flash']) && is_array($_SESSION['flash']))
        {
            foreach ($_SESSION['flash'] as &$flash)
            {
                if ($flash['valid_request_count'] <= 0)
                    unset($flash); 
            }  
        }

        echo $output;
    }

    protected function GetCommonViewData()
    {
        $navigation = [
            ['type'=>'item', 'title'=>  'Home',         'href'=>    '/'],
            ['type'=>'item', 'title'=>  'Sounds',       'href'=>    '/sounds/1'],
        ];


        $showLogin = !\Bitlama\Auth\User::isLoggedIn();
        if (\Bitlama\Auth\User::isLoggedIn())
        {
            $deleteNavs[] = 'Login';
            $deleteNavs[] = 'Register';


            if (\Bitlama\Auth\User::getUserId() == 1)
            {
                //foreach($this->app->router->listRoutes('', false) as $route)
                //    $devitems[] = ['type'=>'item', 'title' => $route, 'href' => $route];
                $devitems[] = ['type'=>'item', 'title' => 'Background Process woo!', 'href' => '/background_process/abracadabra'];


                $navigation[] = ['type'=>'collection', 'title' => 'Dev', 'items'=>$devitems];
            }

            $navigation[] = 
            ['type'=>'collection', 'title' => 'User',
                'items'=> [
                    ['type'=>'item', 'title'=>  'My Profile',       'href'=>    '/user/'.\Bitlama\Auth\User::getUserId()],
                    ['type'=>'item', 'title'=>  'Edit Profile',     'href'=>    '/user/edit_profile'],
                    ['type'=>'item', 'title'=>  'Upload Sound',     'href'=>    '/user/upload_sound'],
                ]
            ];
            $navigation[] = ['type'=>'item', 'title'=>  'Logout',       'href'=>    '/user/logout'];
        }
        else
        {
            $navigation[] = ['type'=>'item', 'title'=>  'Login',        'href'=>    '/user/login'];
            $navigation[] = ['type'=>'item', 'title'=>  'Register',     'href'=>    '/user/register'];
        }

        return [
            'navigation' => $navigation,
            'showLogin'  => $showLogin
        ];
    }

    /*
     * IF not logged in.
     * Redirect user to login screen and then intern redirect back to redirect url.
     */
    protected function authorize($redirectUrl = "/")
    {
        if (!\Bitlama\Auth\User::isLoggedIn())
        {
            $this->app->log->debug("Redirect to:". \Bitlama\Common\Helper::getUrl("/user/login", ['redirectUrl'=>$redirectUrl]));
            $this->app->redirect(\Bitlama\Common\Helper::getUrl("/user/login", ['redirectUrl'=>$redirectUrl]));
            $this->app->stop();
            die();
        }
    }

    /*
     * Redirect user to login screen and then intern redirect back to redirect url.
     */
    protected function authorizeActivation()
    {
        $this->authorize();

        $userInstance = new \Bitlama\Auth\User;
        $userInstanceRecord = $this->app->datasource->load('user', \Bitlama\Auth\User::getUserId());
        $activationRecord = reset($userInstanceRecord->ownActivation);

        if ($activationRecord)
        {
            if (!$activationRecord->activated)
            {
                $messages = [];
                $messages[] = ['title'=>'Account activation required.', 'content'=>'Please first activate your account in order to proceed.'];
                $this->app->flash('messages', $messages);

                $this->app->log->debug("Redirect to: ". \Bitlama\Common\Helper::getUrl("/"));
                $this->app->redirect(\Bitlama\Common\Helper::getUrl("/"));
                $this->app->stop();
                die();
            }
        }
        else
        {
            throw new \SeriouslyBad();
        }
    }

    /*
     * IF not logged in.
     * Redirect user to login screen and then intern redirect back to redirect url.
     */
    protected function authorizeAdmin($redirectUrl = "/")
    {
        if (!(\Bitlama\Auth\User::isLoggedIn() && \Bitlama\Auth\User::getUserId() == 1))
        {
            $this->app->log->debug("Redirect to:". \Bitlama\Common\Helper::getUrl("/user/login", ['redirectUrl'=>$redirectUrl]));
            $this->app->redirect(\Bitlama\Common\Helper::getUrl("/user/login", ['redirectUrl'=>$redirectUrl]));
            $this->app->stop();
            die(); // I'm not paranoid I'm just.... You're not a cop are you? 
        }
    }
}

<?php

namespace Bitlama\Controllers;

class Home extends \Bitlama\Controllers\BaseController {

    public function setRoutes()
    {
        $controller = $this;
        $this->app->get('/', function () use($controller) {
            $controller->app->redirect(\Bitlama\Common\Helper::getUrl('/sounds/1'));
        });

        $this->app->get('/sounds/:page', function ($page) use($controller) {

            $userInstance = new \Bitlama\Auth\User();
            $sounds = $this->app->datasource->findAll('sound', 'WHERE present_files = 1 ORDER BY id LIMIT ?,?', array(
                ($page-1) * \Bitlama\Common\Bootstrap::$soundsPageSize,
                \Bitlama\Common\Bootstrap::$soundsPageSize));
            $soundCount = $this->app->datasource->count('sound', 'WHERE present_files = 1');

            // dependency injection candidate
            foreach($sounds as $sound)
            {
                $sound->setApp($controller->app);
                $sound->initialize();
                $sound->user;

                if (!($sound->isPresentMp3() || $sound->isPresentOgg()))
                {
                    $this->app->log->debug("Sound has no has matches! Id: ". $sound->id);
                    $this->app->log->debug("Files: ". print_r($sound->getFiles(),1));
                }
            }

            $views['RenderedSounds'] =      \Bitlama\Common\Helper::render(
                'sounds.html',
                [
                    'sounds' =>                 $sounds,
                    'showAuthorReference' =>    true,
                ],
                $this->app);
            $views['RenderedHomePage'] =    \Bitlama\Common\Helper::render('home.html', [], $this->app);


            $this->app->log->debug("Sound count:". $soundCount);
            $pages = \Bitlama\Common\Helper::paginate(
                $soundCount,
                function($pageNumber){
                    return "/sounds/{$pageNumber}";
                },
                $page, \Bitlama\Common\Bootstrap::$soundsPageSize);
            $views['RenderedPagination'] =  \Bitlama\Common\Helper::render('pagination.html', ['pages'=>$pages], $this->app);


            if ($userInstance->isLoggedIn())
            {
                $user = $this->app->datasource->load('user', $userInstance->getUserId());

                $views['RenderedInfoBar'] = \Bitlama\Common\Helper::render('infobar.html', ['user'=> ['alias' => $user->alias]], $this->app);
                $viewBase = [
                    'content' => 
                        $views['RenderedInfoBar'] . $views['RenderedHomePage'] . $views['RenderedSounds'] .$views['RenderedPagination'],
                ];
            }
            else
            {
                $viewBase = [
                    'content' => $views['RenderedHomePage'] . $views['RenderedSounds'] . $views['RenderedPagination'],
                ];
            }

            $viewBase['title'] = 'Home';
            $viewBase = array_merge_recursive($viewBase, $this->GetCommonViewData($this->app));
            $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $this->app);

            $this->booom($viewRenderedBase);
        })->conditions(array('page' => '\d+'));;
    }
}


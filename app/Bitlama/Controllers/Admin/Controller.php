<?php

namespace Bitlama\Controllers\Admin;

class Controller extends \Bitlama\Controllers\BaseController {

    public $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function setRoutes()
    {
        $controller = $this;

        $this->app->get('/admin', function () use ($controller) {
            $this->authorizeAdmin('/admin');

            $views['RenderedUsersList'] =   \Bitlama\Common\Helper::render('list.html', $controller->getViewUsersList(), $this->app);
            $views['RenderedSoundsList'] =  \Bitlama\Common\Helper::render('list.html', $controller->getViewSoundsList(), $this->app);
            $viewBase = [
                'title' => 'Test Admin',
                'content' => implode($views)
            ];
            $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));

            $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $this->app);

            $this->Booom($viewRenderedBase);
        });

        $this->app->get('/admin/users', function () use ($controller) {
            $this->authorizeAdmin('/admin/users');

            $views['RenderedUsersList'] =   \Bitlama\Common\Helper::render('list.html', $controller->getViewUsersList(), $this->app);
            $viewBase = [
                'title' => 'Test Admin',
                'content' => implode($views)
            ];
            $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));

            $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $this->app);

            $this->Booom($viewRenderedBase);
        });
        
        $this->app->get('/admin/sounds', function () use ($controller) {
            $this->authorizeAdmin('/admin/sounds');

            $views['RenderedSoundsList'] =  \Bitlama\Common\Helper::render('list.html', $controller->getViewSoundsList(), $this->app);
            $viewBase = [
                'title' => 'Test Admin',
                'content' => implode($views)
            ];
            $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));

            $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $this->app);

            $this->Booom($viewRenderedBase);
        });

        $this->app->get('/admin/sound/approve/:soundId/:approveStatus', function ($soundId, $approveStatus) use ($controller) {
            $soundId =          (int) $soundId;
            $approveStatus =    (bool)$approveStatus;

            $this->authorizeAdmin('/admin/sounds');

            $soundRecord = $controller->app->datasource->findOne('sound', 'id=?', [$soundId]);

            if ($soundRecord)
            {
                $soundRecord->approve = $approveStatus;
                $controller->app->datasource->store($soundRecord);
                $controller->app->response->redirect("/admin");
            }
            else
                $controller->app->notFound();
        });
    }

    protected function getViewUsersList()
    {
        return \Bitlama\Common\Helper::getInterfaceList('Users', array_keys($this->app->datasource->inspect('user')), $this->app->datasource->findAll('user', 'ORDER BY id'));
    }

    protected function getViewSoundsList()
    {
        $list = \Bitlama\Common\Helper::getInterfaceList('Sounds', array_keys($this->app->datasource->inspect('sound')), $this->app->datasource->findAll('sound', 'ORDER BY id'));
        $list['headers'][] = 'approve_action';
        foreach($list['records'] as $record)
        {
            $record['approve'] = (bool)$record['approve'];
            $text = !$record['approve'] ? 'Approve' : 'Unnaprove'; 
            $record['approve_action'] = "<a href='/admin/sound/approve/{$record['id']}/".((int)!$record['approve'])."'>{$text}</a>"; 
        }
        return $list;
    }
}

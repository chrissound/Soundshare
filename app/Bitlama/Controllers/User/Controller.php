<?php

namespace Bitlama\Controllers\User;

class Controller extends \Bitlama\Controllers\BaseController {
    protected $app;
    protected $filter;

    public function setRoutes()
    {
        $controller = $this;
        $this->app->get('/user/register/', function () use ($controller) {

            $userInstance = new \Bitlama\Auth\User;
            if($userInstance->isLoggedIn())
            {
                $this->app->log->warning("User requsted the registration page but... He is already logged in? Wtf?");
                $this->app->redirect(\Bitlama\Common\Helper::getUrl("/", null));
                $this->app->stop();
                die(); // I'm not paranoid I'm just.... HOLY SHIT ARE YOU A COP?!?!  
            }

            $registerUrl = "/user/register";

            $previousFormValues = isset($_SESSION['slim.flash']['fields']) ? $_SESSION['slim.flash']['fields'] : array();
            $validationMessages = isset($_SESSION['slim.flash']['messages']) ? $_SESSION['slim.flash']['messages'] : array();

            $views['renderedHeader'] = \Bitlama\Common\Helper::render('page_header.html', ['page'=>['header'=>'Register']],$controller->app);
            $views['renderedRegistrationForm'] = \Bitlama\Common\Helper::render(
                'form.html', $controller->getRegisterForm($registerUrl, $previousFormValues), $controller->app);
            $views['renderedMessages'] = \Bitlama\Common\Helper::render(
                'notify.html', ['messages' => $validationMessages], $controller->app);

            $viewBase = [
                'title' => 'User Registration',
                'content' => 
                    $views['renderedHeader'] . $views['renderedMessages'] . $views['renderedRegistrationForm']
            ];
            $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));

            $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);
            $controller->booom($viewRenderedBase);
        });

        $this->app->post('/user/register/', function () use ($controller) {

            $userInstance = new \Bitlama\Auth\User;
            if($userInstance->isLoggedIn())
            {
                $this->app->log->warning("User requsted the registration page but... He is already logged in? Wtf?");
                $this->app->redirect(\Bitlama\Common\Helper::getUrl("/", null));
                $this->app->stop();
                die(); // I'm not paranoid I'm just.... HOLY SHIT ARE YOU A COP?!?!  
            }

            /* @TODO filter should be instantiated here - as it's confusing*/
            $controller->app->filter->addSoftRule('alias',           \Aura\Filter\RuleCollection::IS,    'alnum');
            $controller->app->filter->addSoftRule('alias',           \Aura\Filter\RuleCollection::IS,    'strlenMin',    3);
            $controller->app->filter->addSoftRule('password',        \Aura\Filter\RuleCollection::IS,    'strlenMin',    8);
            $controller->app->filter->addSoftRule('password_repeat', \Aura\Filter\RuleCollection::IS,    'strlenMin',    8);
            $controller->app->filter->addSoftRule('email',           \Aura\Filter\RuleCollection::IS,    'email');

            $validationData = [
                'alias' =>              $controller->app->request->post('alias'),
                'password' =>           $controller->app->request->post('password'),
                'password_repeat' =>    $controller->app->request->post('password_repeat'),
                'email' =>              $controller->app->request->post('email'),
            ];

            if ($controller->app->filter->values($validationData)) {

                $activationRecord = call_user_func($controller->app->model, 'activation');
                $activationRecord->code = \Bitlama\Common\Helper::generateRandomString(32);
                $activationRecord->activated = false;

                $userRecord = call_user_func($controller->app->model, 'user');
                $userRecord->alias =                $validationData['alias'];
                $userRecord->email =                $validationData['email'];
                $userRecord->password =             md5($validationData['password'] . "6krfcoEsY2DUJYnxZc36HDKnyRYHE"); // I could be using something mo
                $userRecord->registeredTimestamp =  time();
                $userRecord->ownActivation[] = $activationRecord;
                $controller->app->datasource->store($userRecord);

                \Bitlama\Common\Helper::sendEmail(
                    $userRecord->alias,
                    $userRecord->email,
                    "Soundshare - Activate Account",
                    nl2br(\Bitlama\Common\Helper::render('register.mail', $controller->getViewDataForRegisterMail($userRecord, $activationRecord, true), $controller->app)),
                    \Bitlama\Common\Helper::render('register.mail', $controller->getViewDataForRegisterMail($userRecord, $activationRecord, false), $controller->app),
                    $controller->app);

                $controller->app->response->redirect(\Bitlama\Common\Helper::getUrl('/'));
            }
            else
            {
                $fieldLabels = [
                    'alias' =>             "Alias/Username",
                    'password' =>          "Password", 
                    'password_repeat' =>   "Password Confirmation",
                    'email' =>             "Email"  
                ];

                $messages2 = array();
                $messages = $controller->app->filter->getMessages();
                foreach ($messages as $field => $fieldMessages)
                {
                    $messages2[] = [
                        'title' =>      $fieldLabels[$field], 
                        'content' =>    implode(" ", $fieldMessages)
                    ];
                }
                $controller->app->flash('messages', $messages2);
                $controller->app->flash('fields', array_intersect_key($validationData, array_flip(['alias','email'])));
                $controller->app->response->redirect(\Bitlama\Common\Helper::getUrl('/user/register'));
            }
        });

        $this->app->get('/user/activate/:userId/:activationCode', function ($userId, $activationCode) use ($controller) {

            $userRecord = $controller->app->datasource->findOne('user', 'id = ?', [$userId]);
            $activationRecord = reset($userRecord->ownActivation); // why doesn't $userRecord->activation work?!??!?! 

            if ($userRecord
                && $activationRecord
                && !$activationRecord->activated
                && $activationRecord->code === $activationCode) 
            {
                $activationRecord->activated = true;
                $controller->app->datasource->store($activationRecord);

                $messages[] = ['title'=>'Account activated', 'content'=>'Account activated.'];
                $controller->app->flash('messages', $messages);
                $controller->app->response->redirect('/');
            }
            else
            {
                \LogWriter::info(["Invalid activation request", $userId, $activationCode, $activationRecord->code]);
                $messages[] = ['title'=>'Invalid activation', 'content'=>'Invalid activation request.'];
                $controller->app->flash('messages', $messages);
                $controller->app->response->redirect('/');
            }


        })->name('routeUserActivate');

        $this->app->get('/user/login', function () use ($controller) {

            $redirectUrl = $controller->app->request->get("redirectUrl"); 
            $loginUrl = \Bitlama\Common\Helper::getUrl("/user/login", ['redirectUrl'=>$redirectUrl]); 
                

            $userInstance = new \Bitlama\Auth\User;
            if($userInstance->isLoggedIn())
            {
                $this->app->log->warning("User requsted the login page but... He is already logged in? Wtf?");
                $this->app->redirect(\Bitlama\Common\Helper::getUrl("/", null));
                $this->app->stop();
                die(); // I'm not paranoid I'm just.... HOLY SHIT ARE YOU A COP?!?!  
            }

            $previousFormValues = isset($_SESSION['slim.flash']['fields']) ? $_SESSION['slim.flash']['fields'] : array();
            $messages = isset($_SESSION['slim.flash']['messages']) ? $_SESSION['slim.flash']['messages'] : array();


            $views['renderedHeader'] = \Bitlama\Common\Helper::render('page_header.html', ['page'=>['header'=>'Login']],$controller->app);

            $views['renderedLoginForm'] =   \Bitlama\Common\Helper::render('form.html', $controller->getLoginForm($loginUrl, $previousFormValues), $controller->app);

            $views['renderedMessages'] = \Bitlama\Common\Helper::render(
                'notify.html', ['messages' => $messages], $controller->app);

            $viewBase = [
                'title' => 'User Login',
                'content' => $views['renderedHeader'] . $views['renderedMessages'] . $views['renderedLoginForm']
            ];

            $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));
            $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);

            echo $viewRenderedBase;
        });

        $this->app->post('/user/login', function () use ($controller) {

            $userInstance = new \Bitlama\Auth\User;
            if($userInstance->isLoggedIn())
            {
                $this->app->log->warning("User requsted the login page but... He is already logged in? Wtf?");
                $this->app->redirect(\Bitlama\Common\Helper::getUrl("/", null));
                $this->app->stop();
                die(); // I'm not paranoid I'm just.... HOLY SHIT ARE YOU A COP?!?!  
            }

            $loginUrl = "/user/login";

            $requestData = [
                'alias' =>      $controller->app->request->post('alias'),
                'password' =>   $controller->app->request->post('password'),
            ];

            $userRecord = $controller->app->datasource->findOne('user', 'alias = ? AND password = ?',
                [
                    $requestData['alias'],
                    md5($requestData['password'].'6krfcoEsY2DUJYnxZc36HDKnyRYHE')
                ]
            );

            if ($userRecord) {
                $userInstance->login($userRecord->id);
                $userRecord->loginTimestamp =  time();
                $controller->app->datasource->store($userRecord);

                if ($redirectUrl = $controller->app->request->get("redirectUrl"))
                    $controller->app->response->redirect($redirectUrl);
                else
                    $controller->app->response->redirect('/');
            }
            else
            {
                $messages = [];
                $messages[] = ['title'=>'Invalid login details', 'content'=>'Hmmmmmmmmmmmmmmmmmmmm'];
                $controller->app->flash('messages', $messages);
                $controller->app->flash('fields', ['alias'=>$requestData['alias']]);
                $controller->app->response->redirect('/user/login');
            }

        });

        $this->app->get('/user/logout', function () use ($controller) {
            $this->authorize();
            session_destroy();
            unset($_SESSION);
            $controller->app->response->redirect('/');
        });

        $this->app->get('/user/:userId', function ($userId) use ($controller) {

            if($userRecord = $controller->app->datasource->findOne('user', 'id = ?', [$userId]))
            {
                $sounds = (array)$userRecord->ownSound;
                $comments = (array)$userRecord->ownComment;
                foreach($sounds as $sound)
                {
                    $sound->setApp($controller->app);
                    $sound->initialize();
                }

                foreach($comments as $comment)
                {
                    $comment->user;
                    $comment->sound;
                }

                $views['renderedMessages'] =    \Bitlama\Common\Helper::render('notify.html', ['messages' => []], $controller->app);
                $views['renderedSounds'] =      \Bitlama\Common\Helper::render(
                    'sounds.html',
                    [
                        'sounds' => $sounds,
                        'showSoundProcessing'=> true,
                    ],
                    $controller->app);
                $views['renderedComments'] =    \Bitlama\Common\Helper::render(
                    'sound_comments.html',
                    [
                        'comments'=>            $comments,
                        'showSoundReference'=>  true,
                    ],
                    $controller->app);
                $views['renderedUser'] =        \Bitlama\Common\Helper::render(
                    'user.html',
                    [
                        'user' =>               $userRecord,
                        'renderedSounds' =>     $views['renderedSounds'],
                        'renderedComments' =>   $views['renderedComments'],
                    ],
                    $controller->app);


                $viewBase = [
                    'title' => 'Home',
                    'content' => implode("", [$views['renderedUser']])
                ]; 
                $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));
                $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);

                $controller->booom($viewRenderedBase);

            }
            else
            {
                // User does not exist error page?
            }
        })->conditions(['userId'=>'\d+']);

        $this->app->get('/user/sound/:soundId', function ($soundId) use($controller) {
            $soundRecord = $controller->app->datasource->findOne('sound', 'id=?', [$soundId]);

            if ($soundRecord)
            {
                $soundRecord->setApp($controller->app);
                $soundRecord->initialize();
                $soundRecord->user;
                $commentRecords = $soundRecord->ownComment;

                foreach($commentRecords as $comment)
                    $comment->user;

                $views['renderedHeader'] = \Bitlama\Common\Helper::render('page_header.html', ['page'=>['header'=>$soundRecord->title." - ".$soundRecord->user->alias]],$controller->app);
                $views['renderedSound'] =   \Bitlama\Common\Helper::render('sound.html', ['sound'=>$soundRecord], $controller->app);
                $views['renderedMessages'] = \Bitlama\Common\Helper::render('notify.html', ['messages' => []], $controller->app);
                $views['renderedComments'] = \Bitlama\Common\Helper::render('sound_comments.html', ['comments'=>$commentRecords, 'showAuthorReference'=>true], $controller->app);

                if (\Bitlama\Auth\User::isLoggedIn())
                    $views['renderedCommentForm'] =   \Bitlama\Common\Helper::render('form.html', $controller->getCommentForm('/user/add_comment', ['sound_id' => $soundRecord->id]), $controller->app);
                else
                    $views['renderedCommentForm'] =   '';

                $viewBase = [
                    'title' => 'User Login',
                    'content' => implode("", [$views['renderedMessages'], $views['renderedHeader'], $views['renderedSound'], $views['renderedComments'], $views['renderedCommentForm']])
                ];
                $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));

                $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);

                $controller->booom($viewRenderedBase);
            }
            else
                $controller->app->notFound();
        });

        $this->app->get('/user/upload_sound', function () use ($controller) {

            $requestUrl = "/user/upload_sound";
            $this->authorize($requestUrl);
            $this->authorizeActivation();

            $previousFormValues = isset($_SESSION['slim.flash']['fields']) ? $_SESSION['slim.flash']['fields'] : array();
            $messages = isset($_SESSION['slim.flash']['messages']) ? $_SESSION['slim.flash']['messages'] : array();

            $views['renderedHeader'] = \Bitlama\Common\Helper::render('page_header.html', ['page'=>['header'=>'Upload Sound']],$controller->app);
            $views['renderedUploadForm'] =   \Bitlama\Common\Helper::render(
                'form_file.html',
                $controller->getUploadSoundForm($requestUrl, $previousFormValues),
                $controller->app);

            $views['renderedMessages'] = \Bitlama\Common\Helper::render(
                'notify.html',
                ['messages' => $messages],
                $controller->app);

            $viewBase = [
                'title' => 'User Login',
                'content' => $views['renderedHeader'] . $views['renderedMessages'] . $views['renderedUploadForm']
            ];
            $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));
            $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);
            $controller->booom($viewRenderedBase);
        });

        $this->app->post('/user/upload_sound', function () use ($controller) {

            ini_set('post_max_size', '64M');
            ini_set('upload_max_filesize', '64M');

            $requestUrl = "/user/upload_sound";
            $this->authorize($requestUrl);

            $this->authorize();
            $userInstance = new \Bitlama\Auth\User;

            $requestData = [
                'title' =>                  $controller->app->request->post('title'),
                'description' =>            $controller->app->request->post('description'),
                'sound_file' =>             isset($_FILES['sound_file']) ? $_FILES['sound_file'] : null,
                'sound_file_extension' =>   pathinfo($_FILES['sound_file']['name'], PATHINFO_EXTENSION),
            ];

            $controller->app->filter->addSoftRule('title',                   \Aura\Filter\RuleCollection::IS,        'alnum');
            $controller->app->filter->addSoftRule('description',             \Aura\Filter\RuleCollection::IS_NOT,    'blank');
            $controller->app->filter->addSoftRule('sound_file',              \Aura\Filter\RuleCollection::IS,        'upload');
            $controller->app->filter->addSoftRule('sound_file_extension',    \Aura\Filter\RuleCollection::IS,        'inValues',
                ['aac', 'ac3', 'aif', 'aifc', 'flac', 'm4a', 'm4b', 'mp3', 'ogg', 'wav', 'wma']); // hardcoded for now

            if ($controller->app->filter->values($requestData))
            {
                $userInstanceRecord = $controller->app->datasource->load('user', $userInstance->getUserId());

                $soundRecord = call_user_func($controller->app->model, 'sound');
                $soundRecord->title =               $requestData['title'];
                $soundRecord->description =         $requestData['description'];
                $soundRecord->user_id =             $userInstance->getUserId();
                $soundRecord->createdTimestamp =    time();

                // Save record and mapping to user
                $soundRecordMeta['id'] =    $controller->app->datasource->store($soundRecord);
                $userInstanceRecord->ownSound[] = $soundRecord;
                $controller->app->datasource->store($soundRecord);

                $soundRecord->loadFile($_FILES['sound_file']['tmp_name']);
                $soundRecord->createConversions();

                $controller->app->response->redirect('/');
            }
            else
            {
                $fieldLabels = [
                    'title' =>                  "Title",
                    'description' =>            "Description", 
                    'sound_file' =>             "Sound file",
                    'sound_file_extension' =>   "Sound file extension"
                ];

                $flashMessages= array();
                $messages = $controller->app->filter->getMessages();
                foreach ($messages as $field => $fieldMessages)
                {
                    $flashMessages[] = [
                        'title' =>      $fieldLabels[$field], 
                        'content' =>    implode(" ", $fieldMessages)
                    ];
                }

                $controller->app->flash('messages', $flashMessages);
                $controller->app->flash('fields', array_intersect_key($requestData, array_flip(['title','description'])));
                $controller->app->response->redirect($requestUrl);
            }

        });

        $this->app->post('/user/add_comment', function () use ($controller) {

            $this->authorize();
            $userInstance = new \Bitlama\Auth\User;

            $requestUrl = "/user/upload_sound";

            $requestData = [
                'comment_content' =>    $controller->app->request->post('comment_content'),
                'sound_id' =>           $controller->app->request->post('sound_id'),
            ];

            $controller->app->filter->addSoftRule('comment_content',     \Aura\Filter\RuleCollection::IS_NOT,    'blank');
            $controller->app->filter->addSoftRule('sound_id',            \Aura\Filter\RuleCollection::IS,        'int');

            if ($controller->app->filter->values($requestData))
            {
                $userInstanceRecord = $controller->app->datasource->load('user', $userInstance->getUserId());

                if($soundRecord = $controller->app->datasource->findOne('sound', 'id = ?', [$requestData['sound_id']]))
                {
                    $comment = call_user_func($controller->app->model, 'comment');
                    $comment->content = $requestData['comment_content'];
                    $comment->timestamp = time();

                    $soundRecord->ownComment[] = $comment;
                    $userInstanceRecord->ownComment[] = $comment;

                    $controller->app->datasource->store($soundRecord);
                    $controller->app->datasource->store($userInstanceRecord);

                    $controller->app->response->redirect('/user/sound/'.$requestData['sound_id']);
                }
                else
                    throw new \SeriouslyBad();
            }
            else
                throw new \SeriouslyBad();
        });
    }

    protected function getViewDataForRegisterMail($user, $activation, $html)
    {
        return [
            'user' => $user->alias,
            'activation' => [
                'url' => \Bitlama\Common\Slim::urlFor('routeUserActivate', ['userId' => $user->id, 'activationCode' => $activation->code])
            ],
            'email' => [
                'html' => $html,
            ]
        ];
    }

    protected function getCommentForm($url, $fieldValues)
    {
        $fields = [
            'action' => $url,
            'fields' => [
                ['name' => 'comment_content',   'title' =>  'Comment:', 'type' =>   'textlong'],
                ['name' => 'sound_id',          'title' =>  '',         'type' =>   'hidden'],
            ]
        ];

        foreach ($fields['fields'] as &$data)
        {
            if (isset($fieldValues[$data['name']]))
                $data['value'] = $fieldValues[ $data['name'] ];
        }

        return $fields;
    }

    protected function getRegisterForm($url, $fieldValues)
    {
        $fields = [
            'action' => $url,
            'fields' => [
                ['name' => 'alias',             'title' =>  'Username', 'type' =>   'textshort'],
                ['name' => 'password',          'title' =>  'Password', 'type' =>   'textsecure'],
                ['name' => 'password_repeat',   'title' =>  'Confirm password', 'type' =>   'textsecure'],
                ['name' => 'email',             'title' =>  'Email', 'type' =>   'textshort'],
            ]
        ];

        foreach ($fields['fields'] as &$data)
        {
            if (isset($fieldValues[$data['name']]))
                $data['value'] = $fieldValues[ $data['name'] ];
        }

        return $fields;
    }

    protected function getLoginForm($url, $fieldValues)
    {
        $fields = [
            'action' => $url,
            'fields' => [
                ['name' => 'alias',             'title' =>  'Username', 'type' =>   'textshort'],
                ['name' => 'password',          'title' =>  'Password', 'type' =>   'textsecure'],
            ]
        ];

        foreach ($fields['fields'] as &$data)
        {
            if (isset($fieldValues[$data['name']]))
                $data['value'] = $fieldValues[ $data['name'] ];
        }

        return $fields;
    }

    protected function getUploadSoundForm($url, $fieldValues)
    {
        $fields = [
            'action' => $url,
            'fields' => [
                ['name' => 'title',             'title' =>  'Title',        'type' =>   'textshort'],
                ['name' => 'description',       'title' =>  'Description',  'type' =>   'textlong'],
                ['name' => 'sound_file',        'title' =>  'Sound File',   'type' =>   'file'],
            ]
        ];

        foreach ($fields['fields'] as &$data)
        {
            if (isset($fieldValues[$data['name']]))
                $data['value'] = $fieldValues[ $data['name'] ];
        }

        return $fields;
    }
}

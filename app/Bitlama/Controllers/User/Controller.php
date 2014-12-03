<?php

namespace Bitlama\Controllers\User;

class Controller extends \Bitlama\Controllers\BaseController {
    protected $app;

    public function setRoutes()
    {
        $controller = $this;
        $this->app->get('/user/register/', function () use ($controller) {

            $userInstance = new \Bitlama\Auth\User;
            if($userInstance->isLoggedIn())
                \Bitlama\Common\Helper::redirect("/", $controller->app);

            $previousFormValues = \Bitlama\Common\Helper::getPreviousFormValues();;
            $validationMessages = \Bitlama\Common\Helper::getMessages();

            $registerUrl = "/user/register";

            $captcha = $controller->app->captcha;
            $views['renderedHeader'] = \Bitlama\Common\Helper::render('page_header.html', ['page'=>['header'=>'Register']],$controller->app);
            $views['renderedMessages'] = \Bitlama\Common\Helper::render('notify.html', ['messages' => $validationMessages], $controller->app);
            $views['renderedRegistrationForm'] = \Bitlama\Common\Helper::render(
                'register_form.html',
                array_merge(
                    $controller->getRegisterForm($registerUrl, $previousFormValues),
                    ['captcha' => $captcha->html()]),
                $controller->app);

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
                \Bitlama\Common\Helper::redirect("/", $controller->app);


            /* @TODO filter should be instantiated here - as it's confusing*/
            $validationData = [
                'alias' =>              $controller->app->request->post('alias'),
                'password' =>           $controller->app->request->post('password'),
                'password_repeat' =>    $controller->app->request->post('password_repeat'),
                'email' =>              $controller->app->request->post('email'),
            ];

            $controller->app->filter->addSoftRule('alias',           \Aura\Filter\RuleCollection::IS,    'usernameAvaliable', $validationData['alias']);
            $controller->app->filter->addSoftRule('alias',           \Aura\Filter\RuleCollection::IS,    'alnum');
            $controller->app->filter->addSoftRule('alias',           \Aura\Filter\RuleCollection::IS,    'strlenMin',    3);
            $controller->app->filter->addSoftRule('password',        \Aura\Filter\RuleCollection::IS,    'strlenMin',    8);
            $controller->app->filter->addSoftRule('password_repeat', \Aura\Filter\RuleCollection::IS,    'strlenMin',    8);
            $controller->app->filter->addSoftRule('email',           \Aura\Filter\RuleCollection::IS,    'email');
            $controller->app->filter->addSoftRule('captcha',         \Aura\Filter\RuleCollection::IS,    'captcha');


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

                $controller->app->flash('messages', [['title'=>'Registration successful', 'content' => 'An activation email has been sent, please activate your account.']]);
                $controller->app->response->redirect(\Bitlama\Common\Helper::getUrl('/'));
            }
            else
            {
                $fieldLabels = [
                    'alias' =>              "Alias/Username",
                    'password' =>           "Password", 
                    'password_repeat' =>    "Password Confirmation",
                    'email' =>              "Email",
                    'captcha' =>            "Captcha"
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
            $activationRecord = reset($userRecord->ownActivation);

            if ($userRecord
                && $activationRecord
                && !$activationRecord->activated
                && $activationRecord->code === $activationCode) 
            {
                $activationRecord->activated = true;
                $controller->app->datasource->store($activationRecord);

                $messages[] = ['title'=>'Account activated', 'content'=>'Account activated.'];
            }
            else
            {
                \LogWriter::info(["Invalid activation request", $userId, $activationCode, $activationRecord->code]);
                $messages[] = ['title'=>'Invalid activation', 'content'=>'Invalid activation request.'];
            }

            $controller->app->flash('messages', $messages);
            $controller->app->response->redirect('/');


        })->name('routeUserActivate');

        $this->app->get('/user/login', function () use ($controller) {

            $redirectUrl = $controller->app->request->get("redirectUrl"); 
            $loginUrl = \Bitlama\Common\Helper::getUrl("/user/login", ['redirectUrl'=>$redirectUrl]); 
                

            $userInstance = new \Bitlama\Auth\User;
            if($userInstance->isLoggedIn())
                \Bitlama\Common\Helper::redirect("/", $controller->app);

            $previousFormValues = \Bitlama\Common\Helper::getPreviousFormValues();
            $messages = \Bitlama\Common\Helper::getMessages(); 

            $views['renderedHeader'] = \Bitlama\Common\Helper::render('page_header.html', ['page'=>['header'=>'Login']],$controller->app);

            $views['renderedLoginForm'] =   \Bitlama\Common\Helper::render('form.html', $controller->getLoginForm($loginUrl, $previousFormValues), $controller->app);

            $views['renderedMessages'] = \Bitlama\Common\Helper::render(
                'notify.html', ['messages' => $messages], $controller->app);
            $views['renderedPasswordResetLink'] = \Bitlama\Common\Helper::render('anchor.html', ['linkUrl' => '/user/passwordReset', 'linkText' => 'Reset password'], $controller->app);


            $viewBase = [
                'title' => 'User Login',
                'content' => $views['renderedHeader'] . $views['renderedMessages'] . $views['renderedLoginForm'] . $views['renderedPasswordResetLink']
            ];

            $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));
            $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);

            echo $viewRenderedBase;
        });

        $this->app->post('/user/login', function () use ($controller) {

            $userInstance = new \Bitlama\Auth\User;
            if($userInstance->isLoggedIn())
                \Bitlama\Common\Helper::redirect("/", $controller->app);

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

        $this->app->get('/user/passwordReset', function() use ($controller) {

            $userInstance = new \Bitlama\Auth\User;
            if($userInstance->isLoggedIn())
                \Bitlama\Common\Helper::redirect("/", $controller->app);

            $messages = \Bitlama\Common\Helper::getMessages(); 

            $views = [];
            $views['renderedHeader'] = \Bitlama\Common\Helper::render('page_header.html', ['page'=>['header'=>'Password Reset']],$controller->app);
            $views['renderedResetForm'] = \Bitlama\Common\Helper::render('form.html', $controller->getPasswordResetForm("/user/passwordReset"), $controller->app);
            $views['renderedMessages'] = \Bitlama\Common\Helper::render(
                'notify.html', ['messages' => $messages], $controller->app);

            $viewBase = [
                'title' => 'Password Reset',
                'content' => $views['renderedHeader'] . $views['renderedMessages'] . $views['renderedResetForm']
            ];

            $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));
            $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);

            $this->booom($viewRenderedBase);

        });

        $this->app->post('/user/passwordReset', function () use ($controller) {

            $userInstance = new \Bitlama\Auth\User;
            if($userInstance->isLoggedIn())
                \Bitlama\Common\Helper::redirect("/", $controller->app);

            $requestData = ['email' =>$controller->app->request->post('email')];

            $userRecord = $controller->app->datasource->findOne('user', 'email = ?', [$requestData['email']]);

            if ($userRecord) {

                $resetPasswordRecord = call_user_func($this->app->model, 'passwordreset');
                $resetPasswordRecord->userId = $userRecord->id;
                $resetPasswordRecord->confirmKey = \Bitlama\Common\Helper::generateRandomString(32); 
                $resetPasswordRecord->created = time();
                $this->app->datasource->store($resetPasswordRecord);
                
                \Bitlama\Common\Helper::sendEmail(
                    $userRecord->alias,
                    $userRecord->email,
                    "Soundshare - Password reset",
                    nl2br(\Bitlama\Common\Helper::render('password_reset.mail', $controller->getViewDataForPasswordResetMail($userRecord, $resetPasswordRecord, true), $controller->app)),
                    \Bitlama\Common\Helper::render('password_reset.mail', $controller->getViewDataForPasswordResetMail($userRecord, $resetPasswordRecord, false), $controller->app),
                    $controller->app);

                // Blabla send reset email

                // We should probably a generate a generic page for these situtations 
                //
                $views = [];
                $views['renderedHeader'] = \Bitlama\Common\Helper::render('page_header.html', ['page'=>['header'=>'Password Reset']],$controller->app);
                $views['renderedParagraph'] = \Bitlama\Common\Helper::render('paragraph.html', ['paragraph' => "An email has been sent"], $controller->app);
                $viewBase = [
                    'title' => 'Password Reset',
                    'content' => $views['renderedHeader'] . $views['renderedParagraph']
                ];
                $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));
                $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);
                $this->booom($viewRenderedBase);

            }
            else
            {
                $messages = [];
                $messages[] = ['title'=>'No user found', 'content'=>'No user found with email provided'];
                $controller->app->flash('messages', $messages);
                $controller->app->response->redirect('/user/passwordReset');
            }

        });

        $this->app->get('/user/passwordReset/confirm/:confirmKey', function ($confirmKey) use ($controller) {

            $userInstance = new \Bitlama\Auth\User;
            if($userInstance->isLoggedIn())
                \Bitlama\Common\Helper::redirect("/", $controller->app);

            $resetPasswordRecord = $controller->app->datasource->findOne('passwordreset', 'confirm_key = ? AND created > ?', [$confirmKey, time() - (15 * 60)]);

            if ($resetPasswordRecord) {
                $messages = isset($_SESSION['slim.flash']['messages']) ? $_SESSION['slim.flash']['messages'] : array();

                $views = [];
                $views['renderedHeader'] = \Bitlama\Common\Helper::render('page_header.html', ['page'=>['header'=>'Change password']],$controller->app);
                $views['renderedMessages'] = \Bitlama\Common\Helper::render('notify.html', ['messages' => $messages], $controller->app);
                $views['renderedChangeForm'] = \Bitlama\Common\Helper::render('form.html', $controller->getPasswordChangeForm("/user/changePassword", $confirmKey), $controller->app);

                $viewBase = [
                    'title' => 'Password Reset',
                    'content' => $views['renderedHeader'] . $views['renderedMessages'] . $views['renderedChangeForm']
                ];

                $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));
                $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);

                $this->booom($viewRenderedBase);
            }
            else
            {
                $messages = [];
                $messages[] = ['title'=>'Invalid password request', 'content'=>'Invalid password reset. Please try requesting a new reset.'];
                $controller->app->flash('messages', $messages);
                $controller->app->response->redirect('/user/passwordReset');
            }

        })->name('confirmResetPassword');

        $this->app->post('/user/changePassword', function () use ($controller) {

            $userInstance = new \Bitlama\Auth\User;
            if($userInstance->isLoggedIn())
                \Bitlama\Common\Helper::redirect("/", $controller->app);

            $validationData = [ 
                'confirmKey' =>         $controller->app->request->post('confirm_key'),
                'password' =>           $controller->app->request->post('password'),
                'password_repeat' =>    $controller->app->request->post('password_repeat'),
            ];

            $controller->app->filter->addSoftRule('password',        \Aura\Filter\RuleCollection::IS,    'strlenMin',    8);
            $controller->app->filter->addSoftRule('password_repeat', \Aura\Filter\RuleCollection::IS,    'strlenMin',    8);

            $resetPasswordRecord = $controller->app->datasource->findOne('passwordreset', 'confirm_key = ? AND created > ?', [$validationData['confirmKey'], time() - (15 * 60)]);

            if ($resetPasswordRecord)
            {
                if ($controller->app->filter->values($validationData))
                {
                    $userRecord = $resetPasswordRecord->user; 
                    $userRecord->password = md5($validationData['password'] . "6krfcoEsY2DUJYnxZc36HDKnyRYHE");
                    $controller->app->datasource->store($userRecord);
                    $controller->app->datasource->trash($resetPasswordRecord);

                    $views = [];
                    $views['renderedHeader'] = \Bitlama\Common\Helper::render('page_header.html', ['page'=>['header'=>'Change password']],$controller->app);
                    $views['renderedParagraph'] = \Bitlama\Common\Helper::render('paragraph.html', ['paragraph' => "Your password has been reset. You can now proceed to login. "], $controller->app);

                    $viewBase = [
                        'title' => 'Password Reset',
                        'content' => $views['renderedHeader'] . $views['renderedParagraph']
                    ];

                    $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));
                    $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);

                    $this->booom($viewRenderedBase);
                }
                else
                {
                    $fieldLabels = [
                        'password' =>           "Password", 
                        'password_repeat' =>    "Password confirmation",
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
                    $controller->app->flash('fields', array_intersect_key($validationData, array_flip(['confirmKey'])));
                    $controller->app->response->redirect(\Bitlama\Common\Slim::urlFor('confirmResetPassword', ['confirmKey' => $validationData['confirmKey']]));
                }

            }
            else
            {
                $messages = [];
                $messages[] = ['title'=>'Invalid password request', 'content'=>'Invalid password reset. Please try requesting a new reset.'];
                $controller->app->flash('messages', $messages);
                $controller->app->response->redirect('/user/passwordReset');
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
                $userIsOwner = (\Bitlama\Auth\User::isLoggedIn() && \Bitlama\Auth\User::getUserId() == $userId);

                $sounds = (array)$userRecord->ownSound;
                $comments = (array)$userRecord->ownComment;
                foreach($sounds as $index => $sound)
                    if ($sound->approve OR $userIsOwner)
                    {
                        $sound->setApp($controller->app);
                        $sound->initialize();
                    }
                    else
                    {
                        unset($sounds[$index]);
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
                    'title' => $userRecord->alias,
                    'content' => implode("", [$views['renderedUser']])
                ]; 
                $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));
                $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);

                $controller->booom($viewRenderedBase);

            }
            else
               $controller->app->notFound();
        })->conditions(['userId'=>'\d+']);

        $this->app->get('/user/edit_profile', function () use ($controller) {
            $requestUrl = '/user/edit_profile';
            $this->authorize();

            $messages = isset($_SESSION['slim.flash']['messages']) ? $_SESSION['slim.flash']['messages'] : array();

            $views['renderedParagraph'] = \Bitlama\Common\Helper::render(
                'paragraph.html',
                ['paragraphRaw' => '*Recommended profile picture dimensions: 200px x 200px. <br />*Recommended cover picture dimensions: 1040px x 260px. <br />'],
                $controller->app);
            $views['renderedEditForm'] = \Bitlama\Common\Helper::render(
                'form_file.html',
                $controller->getEditProfileForm($requestUrl, null),
                $controller->app);
            $views['renderedMessages'] = \Bitlama\Common\Helper::render(
                'notify.html', ['messages' => $messages], $controller->app);

            $viewBase = [
                'title' => 'Edit Profile',
                'content' => implode("", [$views['renderedParagraph'], $views['renderedMessages'], $views['renderedEditForm']])
            ]; 
            $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));
            $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);
            $controller->booom($viewRenderedBase);
        });

        $this->app->post('/user/edit_profile', function () use ($controller) {
            $requestUrl = '/user/edit_profile';
            $this->authorize();
            $userInstance = new \Bitlama\Auth\User;

            $requestData = [
                'profile_picture_file' =>               isset($_FILES['profile_picture_file']) ? $_FILES['profile_picture_file'] : null,
                'cover_picture_file' =>                 isset($_FILES['cover_picture_file']) ? $_FILES['cover_picture_file'] : null,
            ];

            if (true)
            {
                $locator = $controller->app->filter->getRuleLocator();
                $locator->set('uploadExtension', function () use($controller) {
                    $rule = call_user_func($controller->app->filterRule, 'UploadExtension');
                    return $rule;
                });
                
                $filterBoth = $controller->app->filterInstance;
                $locator = $filterBoth->getRuleLocator();
                $locator->set('uploadExtension', function () use($controller, $filterBoth) {
                    $rule = call_user_func($controller->app->filterRuleInstance, 'UploadExtension', $filterBoth->getTranslator());
                    return $rule;
                });

                $filterProfile = $controller->app->filterInstance;
                $locator = $filterProfile->getRuleLocator();
                $locator->set('uploadExtension', function () use($controller, $filterProfile) {
                    $rule = call_user_func($controller->app->filterRuleInstance, 'UploadExtension', $filterProfile->getTranslator());
                    return $rule;
                });

                $filterCover = $controller->app->filterInstance;
                $locator = $filterCover->getRuleLocator();
                $locator->set('uploadExtension', function () use($controller, $filterCover) {
                    $rule = call_user_func($controller->app->filterRuleInstance, 'UploadExtension', $filterCover->getTranslator());
                    return $rule;
                });
            }

            $validationMessages = array();

            // grrrr aura bureaucracy
            $profile_picture_file = $requestData['profile_picture_file'];
            $cover_picture_file = $requestData['cover_picture_file'];

            if (!$controller->app->filter->value($profile_picture_file,   \Aura\Filter\RuleCollection::IS,    'upload')
            &&  !$controller->app->filter->value($cover_picture_file,     \Aura\Filter\RuleCollection::IS,    'upload')
            )
            {
                $filterBoth->addSoftRule('profile_picture_file',   \Aura\Filter\RuleCollection::IS,    'upload');
                $filterBoth->addSoftRule('cover_picture_file',     \Aura\Filter\RuleCollection::IS,    'upload');
                $filterBoth->values($requestData);

                $validationMessages = array_merge($validationMessages, $filterBoth->getMessages());

                \LogWriter::debug("Both validation failed :");
                \LogWriter::debug($validationMessages);
            }
            else
            {
                if ($controller->app->filter->value($profile_picture_file,   \Aura\Filter\RuleCollection::IS,    'upload'))
                {
                    if ($controller->app->filter->value($profile_picture_file,   \Aura\Filter\RuleCollection::IS,    'uploadExtension', ['jpeg', 'jpg', 'png', 'bmp']))
                    {
                        \LogWriter::debug('profile picture success');

                        $profileImageRecord = $this->app->datasource->findOne('image', 'user_id = ? AND image_type_id = ?', [$userInstance->getUserId(), 1]);

                        if (!$profileImageRecord)
                            $profileImageRecord = call_user_func($controller->app->model, 'image');

                        $profileImageRecord->user_id =          $userInstance->getUserId();
                        $profileImageRecord->image_type_id =   1;
                        $profileImageRecord->createdTimestamp = time();

                        $controller->app->datasource->store($profileImageRecord);
                        $profileImageRecord->loadFile($_FILES['profile_picture_file']['tmp_name']);

                        $controller->app->flash('messages', [['title'=>'Profile picture', 'content' => 'Profile picture successfully changed.']]);
                    }
                    else
                    {
                        \LogWriter::debug('profile picture fail');
                        $filterProfile->addSoftRule('profile_picture_file',   \Aura\Filter\RuleCollection::IS,    'uploadExtension', ['jpeg', 'jpg', 'png', 'bmp']);
                        $filterProfile->values($requestData);
                        $validationMessages = array_merge($validationMessages, $filterProfile->getMessages());
                    }
                }

                if ($controller->app->filter->value($cover_picture_file,     \Aura\Filter\RuleCollection::IS,    'upload'))
                {
                    if($controller->app->filter->value($cover_picture_file,     \Aura\Filter\RuleCollection::IS,    'uploadExtension', ['jpeg', 'jpg', 'png', 'bmp']))
                    {
                        \LogWriter::debug('cover picture success');

                        $coverImageRecord = $this->app->datasource->findOne('image', 'user_id = ? AND image_type_id = ?', [$userInstance->getUserId(), 2]);

                        if (!$coverImageRecord)
                            $coverImageRecord = call_user_func($controller->app->model, 'image');

                        $coverImageRecord->user_id =            $userInstance->getUserId();
                        $coverImageRecord->image_type_id =   2;
                        $coverImageRecord->createdTimestamp =   time();

                        $controller->app->datasource->store($coverImageRecord);
                        $coverImageRecord->loadFile($_FILES['cover_picture_file']['tmp_name']);

                        $controller->app->flash('messages', [['title'=>'Cover picture', 'content' => 'Cover picture successfully changed.']]);
                    }
                    else
                    {
                        \LogWriter::debug('cover picture fail');
                        $filterCover->addSoftRule('cover_picture_file',     \Aura\Filter\RuleCollection::IS,    'uploadExtension', ['jpeg', 'jpg', 'png', 'bmp']);
                        $filterCover->values($requestData);
                        $validationMessages = array_merge($validationMessages, $filterCover->getMessages());
                    }
                }
            }

            if (empty($validationMessages))
            {
                $controller->app->response->redirect('/user/'.$userInstance->getUserId());
            }
            else
            {
                $fieldLabels = [
                    'profile_picture_file' =>           "Profile picture",
                    'profile_picture_file_extension' => "Profile picture extension",
                    'cover_picture_file' =>             "Cover picture",
                    'cover_picture_file_extension' =>   "Cover picture extension"
                ];

                $flashMessages= array();
                $messages = $validationMessages;
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

        $this->app->get('/user/sound/:soundId', function ($soundId) use($controller) {
            $soundRecord = $controller->app->datasource->findOne('sound', 'id=?', [$soundId]);

            if ($soundRecord)
            {
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
                    'title' => $soundRecord->title . " - " . $soundRecord->user->alias,
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

            $views['renderedHeader'] = \Bitlama\Common\Helper::render(
                'page_header.html',
                ['page'=>['header'=>'Upload Sound']],
                $controller->app);
            $views['renderedUploadForm'] = \Bitlama\Common\Helper::render(
                'form_file.html',
                $controller->getUploadSoundForm($requestUrl, $previousFormValues),
                $controller->app);
            $views['renderedMessages'] = \Bitlama\Common\Helper::render(
                'notify.html',
                ['messages' => $messages],
                $controller->app);

            $viewBase = [
                'title' => 'Upload Sound',
                'content' => \Bitlama\Common\Helper::implodeIndexed($views, array('renderedHeader','renderedMessages','renderedUploadForm'))
            ];
            $viewBase = array_merge_recursive($viewBase, $controller->GetCommonViewData($controller->app));
            $viewRenderedBase = \Bitlama\Common\Helper::render('base.html', $viewBase, $controller->app);
            $controller->booom($viewRenderedBase);
        });

        $this->app->post('/user/upload_sound', function () use ($controller) {

            $requestUrl = "/user/upload_sound";
            $this->authorize($requestUrl);

            $userInstance = new \Bitlama\Auth\User;

            $requestData = [
                'title' =>                  $controller->app->request->post('title'),
                'description' =>            $controller->app->request->post('description'),
                'sound_file' =>             isset($_FILES['sound_file']) ? $_FILES['sound_file'] : null,
                'sound_file_extension' =>   pathinfo($_FILES['sound_file']['name'], PATHINFO_EXTENSION),
            ];

            $controller->app->filter->addHardRule('title',                   \Aura\Filter\RuleCollection::IS_NOT,    'blank');
            $controller->app->filter->addSoftRule('title',                   \Aura\Filter\RuleCollection::IS,        'alphanumspace');
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
                $soundRecord->approve =             false;

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

    protected function getViewDataForPasswordResetMail($user, $confirmReset, $html)
    {
        return [
            'user' => $user->alias,
            'confirmReset' => [
                'url' => \Bitlama\Common\Slim::urlFor('confirmResetPassword', ['confirmKey' => $confirmReset->confirmKey])
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

    protected function getPasswordResetForm($url)
    {
        $fields = [
            'action' => $url,
            'fields' => [
                ['name' => 'email',             'title' =>  'Email', 'type' =>   'textshort'],
            ]
        ];

        return $fields;
    }

    protected function getPasswordChangeForm($url, $confirmKey)
    {
        $fields = [
            'action' => $url,
            'fields' => [
                ['name' => 'password',          'title' =>  'Password',         'type' =>   'textsecure'],
                ['name' => 'password_repeat',   'title' =>  'Confirm password', 'type' =>   'textsecure'],
                ['name' => 'confirm_key',       'value' => $confirmKey,         'type' =>   'hidden'],
            ]
        ];

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

    protected function getEditProfileForm($url, $fieldValues)
    {
        $fields = [
            'action' => $url,
            'fields' => [
                ['name' => 'profile_picture_file',   'title' =>  'Profile Picture',  'type' =>   'file'],
                ['name' => 'cover_picture_file',     'title' =>  'Cover Picture',   'type' =>   'file'],
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

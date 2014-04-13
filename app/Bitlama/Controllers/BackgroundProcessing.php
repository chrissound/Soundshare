<?php

namespace Bitlama\Controllers;

class BackgroundProcessing extends \Bitlama\Controllers\BaseController {

    public function setRoutes()
    {
        $controller = $this;
        $this->app->get('/background_process/:secret', function ($secret) use($controller) {
                    $controller->app->log->debug("Background process.");
            if ($secret === 'abracadabra') // L33t securities 
            {

                $sounds = $this->app->datasource->findAll('sound');

                // dependency injection candidate
                foreach($sounds as $sound)
                {
                    $sound->setApp($controller->app);
                    $sound->initialize();

                    $controller->app->log->debug("Sound {$sound->id} processing is {$sound->processing}.");
                    if ($sound->isProcessing())
                    {
                        $convert = new \Bitlama\Common\Convert($sound);
                        $convert->setApp($this->app);

                        $convert->addToQue($sound);
                    }
                }
            }
            else 
                echo "Ohhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh shit! PREPARE THE LAZERS!";
        });
    }
}

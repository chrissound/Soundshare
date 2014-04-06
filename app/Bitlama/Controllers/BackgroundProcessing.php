<?php

namespace Bitlama\Controllers;

class BackgroundProcessing extends \Bitlama\Controllers\BaseController {

    public function setRoutes()
    {
        $controller = $this;
        $this->app->get('/background_process/:secret', function ($secret) use($controller) {
            if ($secret === 'abracadabra') // L33t securities 
            {

                $sounds = $this->app->datasource->findAll('sound');

                // dependency injection candidate
                foreach($sounds as $sound)
                {
                    $sound->user; // Seems like a twig/readbean bug :(
                    $sound->setApp($controller->app);
                    $sound->initialize();

                    if ($sound->processing)
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

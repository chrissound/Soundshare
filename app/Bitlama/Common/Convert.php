<?php

namespace Bitlama\Common;

class Convert {

    public function __construct()
    {
    }

    public function setApp($app)
    {
        $this->app = $app;
    }

    public function addToQue($sound)
    {
        try {
          $soundSourceFile = $sound->getSourceFile(); 
        }
        catch (\NoSourceFileFound $e) {
            $this->app->log->warning("Sound file no source file! Sound Id: ".$sound->id);
            $this->app->log->warning($e->getMessage());
            return null;
        }

        foreach($sound->getConversions() as $conversion)
        {
            // Process already exists? 
            if ($conversion->processurlid)
            {
                try {
                    $filePath = $this->processConversion($conversion);
                    $sound->loadFile($filePath);
                    $this->app->datasource->trash($conversion);
                }
                catch (\ConversionNotFinished $e)
                {
                }
            }
            else
            {
                $conversion->processurlid = $this->createProcess($conversion); // And after this we'll need to come back next time LOL xD
                $this->app->datasource->store($conversion);
            }
             
        }
    }

    public function createProcess($conversion)
    {
        $sourcefile = new \Bitlama\Common\File($conversion->filepath);

        try{
            $convert = new \Bitlama\Common\Cloudconvert;
            $process = $convert->createProcess(
                $sourcefile->getMimeType(true),
                $conversion->output,
                "J2Ng8EYv6op9AN1owqlprrvY2MgPwnkxotM1R2KJpF9ihFtWmZcfdnnla4trqXUrSUsRNcLtR5CzVlf6fbAa-w");
            $process->setOption('audio_bitrate', 256);
            $process->upload($sourcefile->getFilePath(), $conversion->output); // upload

            return $process->getUrl();
        }
        catch (\Exception $e)
        {
            throw $e;
        }
    } 

    public function processConversion($conversion)
    {
        $this->app->log->info(__FUNCTION__);
        $this->app->log->info($conversion->processurlid);

        $convert = new \Bitlama\Common\Cloudconvert;
        $convert = $convert->useProcess($conversion->processurlid);
        $response = $convert->status();

        $this->app->log->info('response');
        $this->app->log->info(print_r($response, true));

        if($response->step == 'finished')
        {
            $filePath = \Bitlama\Common\Bootstrap::$appPath."appdata/tmp/{$conversion->id}.{$conversion->output}";
            $this->app->log->debug("saving converted file to:");
            $this->app->log->debug($filePath);

            $convert->download($filePath);

            return $filePath;
        }
        else
            throw new \ConversionNotFinished("Step is: ".$response->step);
    }
}

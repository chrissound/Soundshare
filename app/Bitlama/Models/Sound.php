<?php
namespace Bitlama\Models;

class Sound extends \Bitlama\Models\BaseModel {

    protected $directoryPath;
    protected $fileExtensions = ['mp3', 'wav', 'wave', 'ogg', 'oga'];
    protected $fileMimeTypes = ['mp3', 'wav', 'ogg'];

    protected $presentMp3 = false;
    protected $presentOgg = false;
    protected $presentWav = false;

    public function __construct() {
        parent::__construct();
        $this->directoryPath = \Bitlama\Common\Bootstrap::$appPath."appdata/sounds/";
    }

    public function initialize() {
        if($this->bean->processing != $this->isProcessing())
        {
            $this->bean->processing = !$this->bean->processing;
            $this->app->datasource->store($this->bean);
        }

        $this->getFiles();
    }

    public function open() {
    }

    /* returns bool if any coversions remain */
    public function isProcessing() {
        if (empty($this->getFiles()) || count($this->getConversions()) > 0)
        {
            $this->app->log->debug("Sound id: ".            $this->bean->id);
            $this->app->log->debug("Sound determined as processing.");
            $this->app->log->debug("Sound files: ".         print_r($this->getFiles(),true));
            return true;
        }
        else
            return false;
    }

    /* copies file to application path */
    public function loadFile($filePath)
    {
        $file = new \Bitlama\Common\File($filePath);
        $extension = $file->getMimeType(true);

        $destFilePath =  \Bitlama\Common\Bootstrap::$appPath."appdata/sounds/sound_{$this->bean->id}.{$extension}";

        $this->app->log->debug("Attempting copy from: '{$filePath}' to '{$destFilePath}.'");
        if (!copy($filePath, $destFilePath))
            throw Exception("Unable to move file $filePath to $destFilePath.");

        if (!empty($this->getFiles()))
        {
            if(!$this->bean->present_files)
            {
                $this->bean->present_files = true;
                $this->app->datasource->store($this->bean);
            }
            
        }
    }

    public function getSourceFile()
    {
        $files = $this->getFiles();
        $soundTypes = [];


        // Put em into mimetypes
        foreach ($files as $file)
            foreach ($this->fileMimeTypes as $mimetype)
                if ($file->isShortMimeType($mimetype))
                    $soundTypes[$mimetype] = $file;

        
        // Find the best source file based on order 
        $source = false;
        $order = ['wav', 'mp3', 'ogg'];
        foreach ($order as $shortMime)
            if (!empty($soundTypes[$shortMime]))
            {
                $source = $soundTypes[$shortMime]; 
                break;
            }

        if ($source)
            return $source;
        else
        {
            \LogWriter::debug($files);
            \LogWriter::debug($soundTypes);
            throw new \NoSourceFileFound();
        }
    }

    /* create conversion records based on which files are present */
    public function createConversions()
    {
        $source = $this->getSourceFile();

        $files = $this->getFiles();

        $soundTypes = array();
        foreach($files as $file)
        {
            $mimetype = $file->getMimeType(true);
            $soundTypes[$mimetype][] = $file;
        }

        \LogWriter::debug($this->fileMimeTypes, 'ftypes');
        \LogWriter::debug($soundTypes, 'stypes');
        

        // Create the conversion records
        foreach($this->fileMimeTypes as $type)
            if (empty($soundTypes[$type]) && $type != 'wav') // ignore not present wav
            {
                $conversion = call_user_func($this->app->model, 'conversion');
                $conversion->filepath = $source->getFilePath();
                $conversion->sound = $this->bean;
                $conversion->output = $type;
                $this->app->datasource->store($conversion);
            }

        $this->bean->processing = $this->isProcessing();
        $this->app->datasource->store($this->bean);
    }

    public function getFiles()
    {
        if (!$this->bean->id)
            return [];


        // Find all files in path
        $files = [];
        foreach ($this->fileExtensions as $fileExtension)
        {
            $filePath = $this->directoryPath."sound_{$this->bean->id}.{$fileExtension}";

            if (file_exists($filePath))
                $files[] = new \Bitlama\Common\File($filePath);
        }


        // See which are valid (match audio mime type)
        $validFiles = [];
        foreach ($files as $file)
            foreach ($this->fileMimeTypes as $mimetype)
            {
                if ($file->isShortMimeType($mimetype))
                {
                    if ($mimetype == 'mp3')
                        $this->presentMp3 =  true;
                    elseif ($mimetype == 'ogg')
                        $this->presentOgg =  true;
                    elseif ($mimetype == 'wav')
                        $this->presentWav =  true;

                    $validFiles[] = $file;
                    break;
                }
            }

        if (!empty($validFiles))
        {
            if(!$this->bean->present_files)
            {
                $this->bean->present_files = true;
                $this->app->datasource->store($this->bean);
            }
            
        }
        
        return $validFiles;
    }

    public function getConversions()
    {
        return $this->bean->ownConversion;
    }

    public function isPresentMp3()
    {
        return $this->presentMp3;
    }

    public function isPresentOgg()
    {
        return $this->presentOgg;
    }

    public function isPresentWav()
    {
        return $this->presentWav;
    }

    /*
     * @return DateTime
     */
    public function getCreatedDateTime()
    {
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($this->bean->createdTimestamp);
        return $dateTime;
    }
}

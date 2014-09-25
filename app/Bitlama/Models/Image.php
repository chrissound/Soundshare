<?php
namespace Bitlama\Models;

class Image extends \Bitlama\Models\BaseModel {
    protected $directoryPath;
    protected $fileExtensions = ['jpeg', 'jpg', 'png', 'bmp'];
    protected $fileMimeTypes = ['jpeg', 'png', 'bmp'];

    protected $presentImage = false;
    protected $type;

    public function __construct() {
        parent::__construct();
        $this->directoryPath = \Bitlama\Common\Bootstrap::$appPath."appdata/images/";
    }

    public function initialize() {
        $this->getFiles();
    }

    /* copies file to application path */
    public function loadFile($filePath)
    {
        $file = new \Bitlama\Common\File($filePath);
        $extension = $file->getMimeType(true);

        $destFilePath =  \Bitlama\Common\Bootstrap::$appPath."appdata/images/image_{$this->bean->id}.{$extension}";

        if (true) // is valid?
        {
            // delete image
            if($this->getFile())
            {
                $oldFile = $this->getFile();
                unlink($oldFile->getFilePath());
            }
            
            if (!copy($filePath, $destFilePath))
                throw Exception("Unable to move file $filePath to $destFilePath.");
        }

    }

    public function getFile()
    {
        if (!$this->bean->id)
            return false;

        // Find all files in path
        $files = [];
        foreach ($this->fileExtensions as $fileExtension)
        {
            $filePath = $this->directoryPath."image_{$this->bean->id}.{$fileExtension}";

            if (file_exists($filePath))
                $files[] = new \Bitlama\Common\File($filePath);
        }

        $validFiles = [];
        foreach ($files as $file)
            foreach ($this->fileMimeTypes as $mimetype)
            {
                if ($file->isShortMimeType($mimetype))
                {
                    $validFiles[] = $file;
                    break;
                }
            }

        if (!empty($validFiles))
        {
            $presentImage = false;
            return $validFiles[0];
        }
    }

    public function getImageUrl()
    {
        $file = $this->getFile();
        $pathinfo = pathinfo($file->getFilePath()); 
        return "/appdata/public/images/". $pathinfo['basename'];
    }
}

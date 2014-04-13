<?php
namespace Bitlama\Common;

class File {

    protected $filePath;

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    public function getFilePath()
    {
        if (!file_exists($this->filePath))
            throw new \Exception("No file found.");

        return $this->filePath;
    }

    public function getMimeType($short)
    {
        $mimetypes = [
            'wav' => ['audio/wave','audio/wav','audio/x-wav'],
            'ogg' => ['audio/ogg', 'application/ogg'],
            'mp3' => ['audio/mpeg', 'audio/x-mpeg-3', 'audio/mpeg3', 'audio/mp3'],
        ];
        $mimetypekeys = array_keys($mimetypes);

        $finfo = new \finfo();
        if ($short)
        {
            // lazyness 
            foreach ($mimetypekeys as $key)
                if ($this->isShortMimeType($key))
                    return $key;

            throw new \InvalidMimeType();
            LogWriter::debug("Invalid mime type of:".$finfo->file($this->filePath, FILEINFO_MIME_TYPE));

        }
        else
        {
            return $finfo->file($this->filePath, FILEINFO_MIME_TYPE);
        }
    }

    public function isShortMimeType($type)
    {
        $mimetypes = [
            'wav' => ['audio/wave','audio/wav','audio/x-wav'],
            'ogg' => ['audio/ogg', 'application/ogg'],
            'mp3' => ['audio/mpeg', 'audio/x-mpeg-3', 'audio/mpeg3', 'audio/mp3'],
        ];

        if (!in_array($type, array_keys($mimetypes)))
            assert("Invalid mime type selected!");

        global $app; // sorry!
        $app->log->debug("File long mime type detected as: ".$this->getMimeType(false));

        return in_array($this->getMimeType(false), $mimetypes[$type]);
    }

    public function getPublicUrl()
    {
    }   
}

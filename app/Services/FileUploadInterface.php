<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

interface FileUploadInterface
{
    /**
     * Receives the UploadFile object containing the file to be uploaded and
     * stores the file at the storage location provided
     * @param UploadedFile $fileToUpload
     * @param string $storagePath
     * @return string
     */
    public function uploadFile(UploadedFile $fileToUpload, string $storagePath): string;
}

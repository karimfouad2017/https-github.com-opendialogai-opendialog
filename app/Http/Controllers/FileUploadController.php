<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageUploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileUploadController extends Controller
{

    public function __construct()
    {
        $throttleRate = config('opendialog.file_upload.throttle_rate_per_minute');
        $this->middleware("throttle:$throttleRate,1");
    }

    public function upload(ImageUploadRequest $request)
    {
        $fileUploadService = resolve(FileUploadInterface::class);

        $storagePath = '/uploads';
        $fileToUpload = $request->file('image');

        $path = $fileUploadService->uploadFile($fileToUpload, $storagePath);

        return return new JsonResponse([
            'path' => $path,
        ]);
    }
}

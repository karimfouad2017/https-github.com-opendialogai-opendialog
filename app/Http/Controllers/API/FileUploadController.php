<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenDialogAi\Core\Http\Requests\FileUploadRequest;
use OpenDialogAi\Core\Services\FileUploadInterface;

class FileUploadController extends Controller
{

    public function __construct()
    {
        $throttleRate = config('opendialog.file_upload.throttle_rate_per_minute');
        $this->middleware("throttle:$throttleRate,1");
    }

    public function upload(FileUploadRequest $request)
    {
        $fileUploadService = resolve(FileUploadInterface::class);

        $storagePath = '/uploads';
        $fileToUpload = $request->file('file');

        $path = $fileUploadService->uploadFile($fileToUpload, $storagePath);

        return new JsonResponse([
            'path' => $path,
        ]);
    }
}

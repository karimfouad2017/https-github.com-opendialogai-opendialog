<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AudioFileUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'image' => [
                'file',
                'mimes:mp3',
                'max:' . config('opendialog.file_upload.max_file_upload_size'),
                'min:' . config('opendialog.file_upload.min_file_upload_size'),
            ]
        ];
    }
}

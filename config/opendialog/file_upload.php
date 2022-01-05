<?php

return [
    /**
     * This property sets the max file upload size allowed in file uploads to OpenDialog.
     * The value is set in terms of Kilobytes and the default here is 10000kb = 10mb
     */
    'max_file_upload_size' => env('MAX_FILE_UPLOAD_SIZE', 10000),
    /**
     * This property sets the min file upload size allowed in file uploads to OpenDialog.
     * The value is set in terms of Kilobytes and the default here is 100kb
     */
    'min_file_upload_size' => env('MIN_FILE_UPLOAD_SIZE', 100),
    /**
     * This property sets the rate of throttling for requests to upload files in OpenDialog.
     */
    'throttle_rate_per_minute' => env('FILE_UPLOAD_THROTTLE_PER_MINUTE', 30)
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Media Change Detection
    |--------------------------------------------------------------------------
    |
    | When enabled, each sync HEAD-probes the wholesaler's source files
    | (PDF / cover image) and compares a fingerprint (filename, byte size,
    | ETag, Last-Modified) against the last mirrored copy. If the wholesaler
    | silently replaces a file, the system re-mirrors the new file, deletes the
    | old one, and tags the tour (pdf_updated_at / cover_image_updated_at) so it
    | shows an "อัปเดต PDF/รูป" badge in the dashboard.
    |
    | Set MEDIA_CHANGE_DETECTION=false in .env to turn the feature off. When
    | off, the system falls back to the previous behaviour: a file already
    | mirrored to the cloud is never re-downloaded and no update tag is set.
    |
    */

    'media_change_detection' => (bool) env('MEDIA_CHANGE_DETECTION', true),

];

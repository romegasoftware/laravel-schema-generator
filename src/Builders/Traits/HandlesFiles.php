<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Builders\Traits;

trait HandlesFiles
{
    protected function convertMimesToMimeTypes(array $mimes): array
    {
        return $this->convertExtensionsToMimeTypes($mimes);
    }

    /**
     * Convert file extensions to MIME types
     */
    protected function convertExtensionsToMimeTypes(array $extensions): array
    {
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
        ];

        $mimeTypes = [];
        foreach ($extensions as $ext) {
            $ext = strtolower($ext);
            if (isset($mimeMap[$ext])) {
                $mimeTypes[] = $mimeMap[$ext];
            }
        }

        return $mimeTypes;
    }

    /**
     * Apply dimensions validation
     */
    protected function extractDimensions(array $parameters): array
    {
        $constraints = [];

        // Parse Laravel dimensions parameters
        foreach ($parameters as $param) {
            if (strpos($param, '=') !== false) {
                [$key, $value] = explode('=', $param);
                $constraints[$key] = $value;
            }
        }

        return $constraints;
    }
}

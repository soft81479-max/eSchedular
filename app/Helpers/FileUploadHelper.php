<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileUploadHelper {
    public static function upload(
        $file,
        string $folder,
        ?string $oldFile = null,
        string $disk = 'public',
        array $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'],
        int $maxSizeKB = 4096,
        ?int $width = null,
        ?int $height = null,
        string $mode = 'resize', // resize | fit
        string $field = 'file'
    ): string {
        /*
        |--------------------------------------------------------------------------
        | File Validation
        |--------------------------------------------------------------------------
        */
        if (!$file || !$file->isValid()) {
            throw ValidationException::withMessages([
                $field => 'Invalid file upload.',
            ]);
        }

        $mime = $file->getMimeType();

        if (!in_array($mime, $allowedMimes)) {
            throw ValidationException::withMessages([
                $field => 'Invalid file type.',
            ]);
        }

        if (($file->getSize() / 1024) > $maxSizeKB) {
            throw ValidationException::withMessages([
                $field => "File too large. Maximum {$maxSizeKB}KB allowed.",
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Extension Mapping
        |--------------------------------------------------------------------------
        */
        $mimeToExtension = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        $extension = $mimeToExtension[$mime] ?? 'jpg';

        /*
        |--------------------------------------------------------------------------
        | File Name
        |--------------------------------------------------------------------------
        */
        $filename = (string) Str::uuid() . '.' . $extension;
        $path = trim($folder, '/') . '/' . $filename;

        /*
        |--------------------------------------------------------------------------
        | Image Processing
        |--------------------------------------------------------------------------
        */
        if ($width !== null) {
            $manager = new ImageManager(new Driver());
            $image = $manager->read(
                $file->getRealPath()
            );

            if ($mode === 'fit') {
                /*
                |--------------------------------------------------------------------------
                | Avatar / Logo Crop
                |--------------------------------------------------------------------------
                */
                $image = $image->cover($width,$height ?? $width);
            } else {
                /*
                |--------------------------------------------------------------------------
                | Maintain Aspect Ratio
                |--------------------------------------------------------------------------
                */
                $image = $image->scale(width: $width);
            }

            /*
            |--------------------------------------------------------------------------
            | Encode Image
            |--------------------------------------------------------------------------
            */
            switch ($extension) {
                case 'png': $encoded = $image->toPng();
                    break;
                case 'webp': $encoded = $image->toWebp(85);
                    break;
                default: $encoded = $image->toJpeg(85);
                    break;
            }

            Storage::disk($disk)->put($path,(string) $encoded);
        } else {
            /*
            |--------------------------------------------------------------------------
            | Direct Upload
            |--------------------------------------------------------------------------
            */
            $path = $file->storeAs(trim($folder, '/'),$filename,$disk);
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Previous File
        |--------------------------------------------------------------------------
        */
        if (!empty($oldFile) && Storage::disk($disk)->exists($oldFile)) {
            Storage::disk($disk)->delete($oldFile);
        }

        return $path;
    }

    /*
    |--------------------------------------------------------------------------
    | Delete File
    |--------------------------------------------------------------------------
    */
    public static function delete(?string $path,string $disk = 'public'): bool {
        if (empty($path)) {
            return false;
        }

        if (! Storage::disk($disk)->exists($path)) {
            return false;
        }
        return Storage::disk($disk)->delete($path);
    }
}
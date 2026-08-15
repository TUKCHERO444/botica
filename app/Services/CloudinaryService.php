<?php

namespace App\Services;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    public function upload(UploadedFile $file, string $module): array
    {
        $result = Cloudinary::uploadApi()->upload(
            $file->getRealPath(),
            ['folder' => $this->getFolder($module)]
        );

        return [
            'url'       => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    public function delete(?string $publicId): void
    {
        if (!$publicId) {
            return;
        }

        Cloudinary::uploadApi()->destroy($publicId);
    }

    public function getPublicIdFromUrl(string $url): ?string
    {
        if (!str_starts_with($url, 'http')) {
            return null;
        }

        $parts = explode('/', $url);
        $filename = pathinfo(end($parts), PATHINFO_FILENAME);

        return $filename ?: null;
    }

    public function getFolder(string $module): string
    {
        return config("cloudinary.folders.{$module}", config('cloudinary.default_folder', 'general'));
    }
}

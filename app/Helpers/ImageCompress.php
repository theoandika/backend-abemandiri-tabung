<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Imagick;
use ImagickException;

class ImageCompress
{
    /**
     * Compress image.
     *
     * @throws ImagickException
     */
    public static function compress(
        UploadedFile|string $file,
        int $maxDimension = 2048,
        int $quality = 85
    ): UploadedFile {

        $path = $file instanceof UploadedFile
            ? $file->getRealPath()
            : $file;

        $image = new Imagick($path);

        // Auto rotate berdasarkan EXIF
        $image->autoOrient();

        $width = $image->getImageWidth();
        $height = $image->getImageHeight();

        // Resize hanya jika diperlukan
        if ($width > $maxDimension || $height > $maxDimension) {

            if ($width >= $height) {
                $newWidth = $maxDimension;
                $newHeight = (int) (($height / $width) * $newWidth);
            } else {
                $newHeight = $maxDimension;
                $newWidth = (int) (($width / $height) * $newHeight);
            }

            $image->resizeImage(
                $newWidth,
                $newHeight,
                Imagick::FILTER_LANCZOS,
                1,
                true
            );
        }

        $extension = strtolower(
            $file instanceof UploadedFile
                ? $file->getClientOriginalExtension()
                : pathinfo($path, PATHINFO_EXTENSION)
        );

        switch ($extension) {

            case 'png':
                $image->setImageFormat('png');
                $image->setOption('png:compression-level', '6');
                $output = tempnam(sys_get_temp_dir(), 'img_').'.png';
                break;

            case 'webp':
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality($quality);
                $output = tempnam(sys_get_temp_dir(), 'img_').'.webp';
                break;

            default:
                $image->setImageFormat('jpeg');
                $image->setImageCompression(Imagick::COMPRESSION_JPEG);
                $image->setImageCompressionQuality($quality);
                $image->stripImage();
                $output = tempnam(sys_get_temp_dir(), 'img_').'.jpg';
                break;
        }

        $image->writeImage($output);
        $image->clear();

        return new UploadedFile(
            $output,
            basename($output),
            mime_content_type($output),
            test: true
        );
    }
}
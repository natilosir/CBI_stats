<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use ZanySoft\Zip\Facades\Zip;

trait HandlesZip {
    /**
     * باز کردن یا ایجاد یک فایل ZIP (در صورت نیاز دایرکتوری را می‌سازد)
     *
     * @param string $zipPath مسیر کامل فایل ZIP (نسبت به دیسک Storage)
     * @return mixed
     * @throws \Exception
     */
    protected function openOrCreateZip( string $zipPath ) {
        if ( file_exists($zipPath) ) {
            $zip = Zip::open($zipPath);
            if ( !$zip ) {
                throw new \Exception("Failed to open existing ZIP: {$zipPath}");
            }
            return $zip;
        }

        $zip = Zip::create($zipPath);
        if ( !$zip ) {
            throw new \Exception("Failed to create new ZIP: {$zipPath}");
        }
        return $zip;
    }

    /**
     * اضافه کردن فایل به ZIP
     *
     * @param mixed $zip
     * @param string $filePath مسیر کامل فایل منبع (نسبت به دیسک Storage)
     * @param string $fileName نام فایل درون ZIP
     * @return void
     * @throws \Exception
     */
    protected function addFileToZip( $zip, string $filePath, string $fileName ): void {
        $filePath = Storage::disk()
            ->path($filePath);

        if ( !file_exists($filePath) ) {
            throw new \Exception("Source file not found: {$filePath}");
        }
        $added = $zip->add($filePath, $fileName);

        if ( !$added ) {
            throw new \Exception("Failed to add file '{$fileName}' to ZIP");
        }
    }

    /**
     * بستن ZIP
     *
     * @param mixed $zip
     * @return void
     * @throws \Exception
     */
    protected function closeZip( mixed $zip ): void {
        $closed = $zip->close();
        if ( !$closed ) {
            throw new \Exception("Failed to close ZIP archive");
        }
    }
}
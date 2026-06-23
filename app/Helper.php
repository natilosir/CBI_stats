<?php

namespace App;

use Illuminate\Support\Facades\Storage;
use ZanySoft\Zip\Facades\Zip;
use ZipArchive;

Class Helper {
    public function persianDigitsToEnglish( $string ) {
        $persianDigits = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];
        $arabicDigits  = [ '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' ];
        $englishDigits = [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ];

        $string = str_replace($persianDigits, $englishDigits, $string);
        $string = str_replace($arabicDigits, $englishDigits, $string);

        return $string;
    }

    public function normalizePersianDate( $string ) {
        $englishDate = $this->persianDigitsToEnglish($string);
        $englishDate = str_replace('/', '.', $englishDate);
        return $englishDate;
    }

    public function extractDigits( $string ) {
        $englishString = $this->persianDigitsToEnglish($string);
        return preg_replace('/[^0-9]/', '', $englishString);
    }

    public function fileExistsInZip( $zipPath, $fileName ) {
        $fullZipPath = Storage::path($zipPath);

        if ( !file_exists($fullZipPath) ) {
            return false;
        }

        $zip = Zip::open($fullZipPath);

        if ( $zip->has($fileName, ZipArchive::FL_NODIR | ZipArchive::FL_NOCASE) ) {
            return true;
        }

        return false;
    }
}
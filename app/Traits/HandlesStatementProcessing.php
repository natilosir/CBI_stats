<?php

namespace App\Traits;

use App\Helper;
use App\Models\Report\Report;
use Carbon\Carbon;
use Dflydev\DotAccessData\Data;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait HandlesStatementProcessing {
    use HandlesZip;

    protected function processStatementReport( Report $report ): bool {
        try {
            $query = parse_url($report->url, PHP_URL_QUERY);
            parse_str($query, $params);
            $letterSerial = $params['LetterSerial'] ?? null;

            if ( !$letterSerial ) {
                Log::warning("Statement report {$report->id} missing LetterSerial.");
                return false;
            }

            $serial      = str_replace('+', 'OOObOOO', $letterSerial);
            $url         = "https://codal.ir/Reports/MonthlyActivity.aspx";
            $initialHtml = Http::withoutVerifying()
                ->get($url, [ 'LetterSerial' => $serial ])
                ->body();

            preg_match_all('/<option[^>]*value="(\d+)"[^>]*>([^<]+)<\/option>/iu', $initialHtml, $matches);
            $dropdownItems = [];

            if ( !empty($matches[1]) ) {
                foreach ( $matches[1] as $index => $val ) {
                    $text            = trim($matches[2][$index]);
                    $dropdownItems[] = [
                        'index'      => $index,
                        'value'      => $val,
                        'text'       => $text,
                        'clean_text' => $this->normalizeText($text),
                    ];
                }
            }

            if ( empty($dropdownItems) ) {
                Log::warning("Statement report {$report->id}: No options found.");
                $this->downloadReportFile($report);
            }

            $itemsToFetch = [];
            $title        = $report->title ?? '';
            $bsItem       = null;
            $plItem       = null;

            foreach ( $dropdownItems as $item ) {
                if ( str_contains($item['clean_text'], 'صورت وضعیت مالی تلفیقی') || str_contains($item['clean_text'], 'ترازنامه تلفیقی') ) {
                    $bsItem = $item;
                    break;
                }
            }
            if ( !$bsItem ) {
                foreach ( $dropdownItems as $item ) {
                    $isBs = str_contains($item['clean_text'], 'صورت وضعیت مالی') || str_contains($item['clean_text'], 'ترازنامه');
                    if ( $isBs && !str_contains($item['clean_text'], 'تلفیقی') ) {
                        $bsItem = $item;
                        break;
                    }
                }
            }
            if ( $bsItem ) {
                $itemsToFetch[] = $bsItem;
            }

            foreach ( $dropdownItems as $item ) {
                if ( str_contains($item['clean_text'], 'صورت سود و زیان تلفیقی') ) {
                    $plItem = $item;
                    break;
                }
            }
            if ( !$plItem ) {
                $isAudited = str_contains($title, 'حسابرسی شده');
                foreach ( $dropdownItems as $item ) {
                    if ( str_contains($item['clean_text'], 'صورت سود و زیان') ) {
                        if ( str_contains($item['clean_text'], 'تلفیقی') ) continue;
                        if ( $isAudited ) {
                            if ( !str_contains($item['clean_text'], 'جامع') ) {
                                $plItem = $item;
                                break;
                            }
                        }
                        else {
                            $plItem = $item;
                            break;
                        }
                    }
                }
            }
            if ( $plItem ) {
                $itemsToFetch[] = $plItem;
            }

            if ( empty($itemsToFetch) ) {
                $blacklist = [ 'نظر حسابرس', 'اعضای', 'تولید', 'تفسیری', 'ضمایم' ];
                foreach ( $dropdownItems as $item ) {
                    $safe = true;
                    foreach ( $blacklist as $bad ) {
                        if ( str_contains($item['text'], $bad) ) {
                            $safe = false;
                            break;
                        }
                    }
                    if ( $safe ) {
                        $itemsToFetch[] = $item;
                    }
                }
            }

            $itemsToFetch = collect($itemsToFetch)
                ->unique('index')
                ->values()
                ->all();

            $downloadedSheets = [];
            $allSucceeded     = true;

            foreach ( $itemsToFetch as $item ) {
                $valueToSend = $item['value'];
                if ( $item['index'] === 0 ) {
                    $valueToSend = '';
                }

                try {
                    $sheetData = $this->fetchAndSaveStatementSheet($report, $serial, $url, $valueToSend, $item['text'], $item['value']);
                    if ( $sheetData ) {
                        $downloadedSheets[] = $sheetData;
                    }
                    else {
                        $allSucceeded = false;
                    }
                } catch ( \Throwable $e ) {
                    Log::error("Statement sheet '{$item['text']}' failed for report {$report->id}: " . $e->getMessage());
                    $allSucceeded = false;
                }
            }

            $letterCode = app(Helper::class)->extractDigits($report->letter_code);

            if ( $allSucceeded && !empty($downloadedSheets) ) {
                $directory       = "reports/{$report->stock_id}";
                $zipFileName     = "{$letterCode}.zip";
                $zipRelativePath = $directory . '/' . $zipFileName;
                $fullZipPath     = Storage::disk()
                    ->path($zipRelativePath);

                try {
                    $zip       = $this->openOrCreateZip($fullZipPath);
                    $htmlFiles = [];

                    foreach ( $downloadedSheets as $sheet ) {
                        $fullHtmlPath = Storage::disk()
                            ->path($sheet['html_path']);
                        if ( !file_exists($fullHtmlPath) ) {
                            throw new \Exception("HTML file missing: {$fullHtmlPath}");
                        }
                        $this->addFileToZip($zip, $sheet['html_path'], $sheet['html_filename']);

                        $filename       = $sheet['html_filename'];
                        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
                        $parts          = explode('.', $nameWithoutExt);
                        $key            = end($parts);

                        $htmlFiles[$key] = $filename;
                    }

                    $this->closeZip($zip);

                    foreach ( $downloadedSheets as $sheet ) {
                        Storage::disk()
                            ->delete($sheet['html_path']);
                    }

                    $report->update([
                        'downloaded_file_path' => [
                            'zip'  => $zipRelativePath,
                            'html' => $htmlFiles,
                        ],
                        'downloaded_at'        => Carbon::now(),
                    ]);

                    return true;
                } catch ( \Exception $e ) {
                    Log::error("ZIP creation failed for statement report {$report->id}: " . $e->getMessage());
                    foreach ( $downloadedSheets as $sheet ) {
                        Storage::disk()
                            ->delete($sheet['html_path']);
                    }
                    return false;
                }
            }

            return false;
        } catch ( \Throwable $e ) {
            Log::error("processStatementReport failed for {$report->id}: " . $e->getMessage());
            return false;
        }
    }

    private function fetchAndSaveStatementSheet( Report $report, string $serial, string $url, string $ddlTable, string $sheetName, string $sheetValue ): ?array {
        $params = [
            'LetterSerial'   => $serial,
            '__EVENTTARGET'  => 'ctl00$ddlTable',
            'ctl00$ddlTable' => $ddlTable,
        ];

        $response = Http::withoutVerifying()
            ->asForm()
            ->get($url, $params);

        if ( !$response->successful() ) {
            Log::warning("Failed to fetch sheet '{$sheetName}' for report {$report->id}, status: " . $response->status());
            return null;
        }
        $htmlContent = $response->body();
        $data        = $this->extractDateAndCode($report);
        $englishDate = $data['englishDate'];
        $fileName    = "{$englishDate}.{$report->id}.{$sheetValue}.html";
        $directory   = "reports/{$report->stock_id}/";
        $htmlPath    = $directory . $fileName;

        Storage::disk()
            ->put($htmlPath, $htmlContent);

        return [
            'sheet_id'      => $sheetValue,
            'sheet_name'    => $sheetName,
            'html_path'     => $htmlPath,
            'html_filename' => $fileName,
        ];
    }

    private function normalizeText( string $text ): string {
        $text = str_replace([ 'ي', 'ك' ], [ 'ی', 'ک' ], $text);
        return trim($text);
    }

    private function cleanAndNormalizeTitle( string $text ): string {
        if ( empty($text) ) return $text;
        $text = str_replace([ 'ي', 'ك' ], [ 'ی', 'ک' ], $text);
        $text = str_replace('تلفیقی', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}

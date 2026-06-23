<?php

namespace App\Traits;

use App\Helper;
use App\Models\Report\Report;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait HandlesReportDownload {
    use HandlesZip;

    protected function extractDateAndCode( Report $report ): array {
        preg_match('/[۰-۹]{4}\/[۰-۹]{2}\/[۰-۹]{2}/u', $report->title, $matches);
        $persianDate = $matches[0] ?? '';
        $englishDate = app(Helper::class)->normalizePersianDate($persianDate);
        $letterCode  = app(Helper::class)->extractDigits($report->letter_code);
        return compact('englishDate', 'letterCode');
    }

    protected function downloadReportFile( Report $report ): bool {
        $data        = $this->extractDateAndCode($report);
        $englishDate = $data['englishDate'];
        $letterCode  = $data['letterCode'];

        $fullUrl = "https://codal.ir" . $report->url;

        try {
            $response = Http::withHeaders([
                'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language'           => 'en-US,en;q=0.9,fa;q=0.8',
                'Accept-Encoding'           => 'gzip, deflate, br',
                'Connection'                => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest'            => 'document',
                'Sec-Fetch-Mode'            => 'navigate',
                'Sec-Fetch-Site'            => 'none',
                'Sec-Fetch-User'            => '?1',
                'Cache-Control'             => 'max-age=0',
            ])
                ->timeout(30)
                ->withoutVerifying()
                ->retry(2, 1000)
                ->withOptions([
                    'allow_redirects' => [ 'max' => 5, 'strict' => true ],
                    'verify'          => true,
                ])
                ->get($fullUrl);
        } catch ( \Exception $e ) {
            Log::error("HTTP request failed for report {$report->id}: " . $e->getMessage());
            return false;
        }

        if ( $response->successful() ) {
            $content         = $response->body();
            $directory       = "reports/{$report->stock_id}";
            $htmlFileName    = "{$englishDate}.{$report->id}.html";
            $htmlPath        = $directory . '/' . $htmlFileName;
            $zipFileName     = "{$letterCode}.zip";
            $zipRelativePath = $directory . '/' . $zipFileName;

            Storage::disk()
                ->put($htmlPath, $content);
            $fullZipPath = Storage::disk()
                ->path($zipRelativePath);

            try {
                $zip = $this->openOrCreateZip($fullZipPath);
                $this->addFileToZip($zip, $htmlPath, $htmlFileName);
                $this->closeZip($zip);
                Storage::disk()
                    ->delete($htmlPath);

                $report->update([
                    'downloaded_file_path' => [
                        'zip'  => $zipRelativePath,
                        'html' => [ $htmlFileName ],
                    ],
                    'downloaded_at'        => Carbon::now(),
                ]);
                return true;
            } catch ( \Exception $e ) {
                Log::error("ZIP operation failed for report {$report->id}: " . $e->getMessage());
                Storage::disk()
                    ->delete($htmlPath);
                return false;
            }
        }

        Log::warning("Download failed for report ID {$report->id}, status: " . $response->status());
        return false;
    }
}

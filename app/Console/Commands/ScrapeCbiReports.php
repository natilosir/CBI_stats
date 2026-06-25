<?php

namespace App\Console\Commands;

use App\Models\CBI\Report;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ScrapeCbiReports extends Command {
    protected $signature = 'cbi:scrape
                                {year : Scrape only a specific Persian year, e.g. 1404}';

    protected $description = 'Scrape CBI monetary statistics Excel files for all years and months';

    private const BASE_URL     = 'https://cbi.ir';
    private const CATEGORY_URL = 'https://cbi.ir/category/2692.aspx';

    /**
     * Persian month name → zero-padded month number
     */
    private const MONTH_MAP = [
        'فروردین'  => '01',
        'اردیبهشت' => '02',
        'خرداد'    => '03',
        'تیر'      => '04',
        'مرداد'    => '05',
        'شهریور'   => '06',
        'مهر'      => '07',
        'آبان'     => '08',
        'آذر'      => '09',
        'دی'       => '10',
        'بهمن'     => '11',
        'اسفند'    => '12',
    ];

    /**
     * Year label (1404) → ASP.NET option value (33030)
     * Extracted from the page HTML you provided.
     */
    private const YEAR_OPTION_MAP = [
        '1404' => '33030',
        '1403' => '30380',
        '1402' => '27853',
        '1401' => '23808',
        '1400' => '21999',
        '1399' => '20392',
        '1398' => '19437',
        '1397' => '18129',
        '1396' => '16609',
        '1395' => '14973',
        '1394' => '13368',
        '1393' => '12113',
        '1392' => '11198',
        '1391' => '9949',
        '1390' => '9289',
        '1389' => '7911',
        '1388' => '6602',
        '1387' => '5377',
        '1386' => '4120',
        '1385' => '3267',
    ];

    public function handle(): int {
        // ── 1. Get VIEWSTATE from the category page ──────────────────────────
        $this->info('Fetching category page to obtain __VIEWSTATE...');
        $categoryHtml = $this->get(self::CATEGORY_URL);

        if ( $categoryHtml === null ) {
            $this->error('Could not reach ' . self::CATEGORY_URL);
            return self::FAILURE;
        }

        $viewState          = $this->extractHidden($categoryHtml, '__VIEWSTATE');
        $viewStateGenerator = $this->extractHidden($categoryHtml, '__VIEWSTATEGENERATOR');

        if ( $viewState === null ) {
            $this->error('Could not extract __VIEWSTATE from category page.');
            return self::FAILURE;
        }

        // ── 2. Determine which years to process ──────────────────────────────
        $onlyYear = $this->argument('year');
        $years    = $onlyYear ? [ $onlyYear => self::YEAR_OPTION_MAP[$onlyYear] ?? null ] : self::YEAR_OPTION_MAP;

        if ( $onlyYear && !isset(self::YEAR_OPTION_MAP[$onlyYear]) ) {
            $this->error("Year {$onlyYear} is not in the known year list.");
            return self::FAILURE;
        }

        // ── 3. Loop years ─────────────────────────────────────────────────────
        foreach ( $years as $yearLabel => $optionValue ) {
            $this->info("");
            $this->info("══════════════════════════════════════");
            $this->info("  Year: {$yearLabel}  (option={$optionValue})");
            $this->info("══════════════════════════════════════");

            // POST to change year → get month list HTML
            $yearPageHtml = $this->postYearChange($viewState, $viewStateGenerator, $optionValue);

            if ( $yearPageHtml === null ) {
                $this->error("  Failed to load months for year {$yearLabel}. Skipping.");
                continue;
            }

            // Extract new VIEWSTATE for subsequent posts
            $viewState          = $this->extractHidden($yearPageHtml, '__VIEWSTATE') ?? $viewState;
            $viewStateGenerator = $this->extractHidden($yearPageHtml, '__VIEWSTATEGENERATOR') ?? $viewStateGenerator;

            // Parse month links  e.g. /simplelist/33031.aspx → فروردین
            $months = $this->parseMonthLinks($yearPageHtml);

            if ( empty($months) ) {
                $this->warn("  No months found for year {$yearLabel}.");
                continue;
            }

            $this->info("  Found " . count($months) . " month(s).");

            // ── 4. Loop months ─────────────────────────────────────────────
            foreach ( $months as $monthName => $monthUrl ) {
                $monthNumber = self::MONTH_MAP[trim($monthName)] ?? null;

                if ( $monthNumber === null ) {
                    $this->warn("  Unknown month name: '{$monthName}'. Skipping.");
                    continue;
                }

                $fileBaseName = "{$yearLabel}{$monthNumber}"; // e.g. 140401
                $this->line("  Month: {$monthName} ({$monthNumber}) → {$fileBaseName}");

                // Skip if already downloaded (unless --fresh)
                if ( $this->alreadyExists($fileBaseName) ) {
                    $this->line("    ↳ Already in DB. Skipping.");
                    continue;
                }

                // GET the month simplelist page to find the Excel link
                $monthHtml = $this->get(self::BASE_URL . $monthUrl);

                if ( $monthHtml === null ) {
                    $this->saveError($yearLabel, $monthName, $fileBaseName, "Could not load month page: {$monthUrl}");
                    continue;
                }

                $excelPagePath = $this->findExcelLink($monthHtml);

                if ( $excelPagePath === null ) {
                    $this->warn("    ↳ No Excel link found on {$monthUrl}.");
                    $this->saveError($yearLabel, $monthName, $fileBaseName, "No Excel link found on {$monthUrl}");
                    continue;
                }

                // Extract the node ID from /page/33058.aspx → 33058
                $excelNodeId = $this->extractNodeId($excelPagePath);
                $this->line("    ↳ Excel page: {$excelPagePath}");

                // Download the Excel file directly from /page/{id}.aspx
                $downloadUrl = self::BASE_URL . $excelPagePath;
                [ $fileContent, $extension, $downloadError ] = $this->downloadFile($downloadUrl);

                if ( $fileContent === null ) {
                    $this->error("    ↳ Download failed: {$downloadError}");
                    $this->saveError($yearLabel, $monthName, $fileBaseName, $downloadError);
                    continue;
                }

                $savedFileName = "{$fileBaseName}.{$extension}";

                // Save to cbi disk
                Storage::disk('cbi')
                    ->put($savedFileName, $fileContent);
                $this->line("    ↳ Saved: {$savedFileName}");

                // Upsert into cbi_reports
                Report::updateOrCreate([ 'file_name' => $savedFileName ], [
                    'name'   => $excelNodeId ?? $fileBaseName,
                    'title'  => "بخش پولی و بانکی - {$yearLabel} - {$monthName}",
                    'period' => $monthName,
                    'error'  => null,
                ]);

                $this->info("    ↳ ✓ Saved to DB.");

                // Brief pause to be polite to the server
                sleep(5);                                     // 5 s
            }
        }

        $this->info('');
        $this->info('Done.');
        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HTTP helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function headers(): array {
        return [
            'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language'           => 'en-US,en;q=0.9,fa;q=0.8',
            'Accept-Encoding'           => 'gzip, deflate, br',
            'Connection'                => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    private function get( string $url ): ?string {
        try {
            $response = Http::withHeaders($this->headers())
                ->withoutVerifying()
                ->timeout(60)
                ->get($url);

            if ( $response->successful() ) {
                return $response->body();
            }

            $this->warn("  GET {$url} returned HTTP " . $response->status());
            return null;
        } catch ( \Throwable $e ) {
            $this->warn("  GET {$url} threw: " . $e->getMessage());
            return null;
        }
    }

    /**
     * POST the year-change __doPostBack to get the month list for that year.
     */
    private function postYearChange( string $viewState, ?string $viewStateGenerator, string $optionValue ): ?string {
        try {
            $response = Http::withHeaders(array_merge($this->headers(), [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Referer'      => self::CATEGORY_URL,
            ]))
                ->withoutVerifying()
                ->timeout(60)
                ->asForm()
                ->post(self::CATEGORY_URL, [
                    '__EVENTTARGET'                            => 'ctl00$ucBody$ucContent$ctl00$ddlYearList',
                    '__EVENTARGUMENT'                          => '',
                    '__LASTFOCUS'                              => '',
                    '__VIEWSTATE'                              => $viewState,
                    '__VIEWSTATEGENERATOR'                     => $viewStateGenerator ?? '',
                    '__VIEWSTATEENCRYPTED'                     => '',
                    'ctl00$ucBody$ucContent$ctl00$ddlYearList' => $optionValue,
                ]);

            if ( $response->successful() ) {
                return $response->body();
            }

            $this->warn("  POST year change returned HTTP " . $response->status());
            return null;
        } catch ( \Throwable $e ) {
            $this->warn("  POST year change threw: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Download a file. Returns [content, extension, errorMessage].
     * The /page/{id}.aspx URL directly serves the file.
     */
    private function downloadFile( string $url ): array {
        try {
            $response = Http::withHeaders(array_merge($this->headers(), [
                'Accept'  => 'application/vnd.ms-excel,application/octet-stream,*/*',
                'Referer' => self::CATEGORY_URL,
            ]))
                ->withoutVerifying()
                ->timeout(120)
                ->get($url);

            if ( !$response->successful() ) {
                return [ null, null, "HTTP " . $response->status() . " from {$url}" ];
            }

            $contentType = $response->header('Content-Type') ?? '';
            $contentDisp = $response->header('Content-Disposition') ?? '';

            // Determine extension from Content-Type or Content-Disposition
            $extension = $this->guessExtension($contentType, $contentDisp, $response->body());

            return [ $response->body(), $extension, null ];
        } catch ( \Throwable $e ) {
            return [ null, null, $e->getMessage() ];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HTML parsers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Extract value of a hidden input field from HTML.
     */
    private function extractHidden( string $html, string $name ): ?string {
        // e.g. <input type="hidden" name="__VIEWSTATE" ... value="abc123">
        if ( preg_match('/<input[^>]+name=["\']' . preg_quote($name, '/') . '["\'][^>]+value=["\']([^"\']*)["\'][^>]*>/i', $html, $m) ) {
            return $m[1];
        }

        // Try alternate attribute order (value before name)
        if ( preg_match('/<input[^>]+value=["\']([^"\']*)["\'][^>]+name=["\']' . preg_quote($name, '/') . '["\'][^>]*>/i', $html, $m) ) {
            return $m[1];
        }

        return null;
    }

    /**
     * Parse the simplelist on the category page to get month name → /simplelist/{id}.aspx
     * Returns ['فروردین' => '/simplelist/33031.aspx', ...]
     */
    private function parseMonthLinks( string $html ): array {
        $months = [];

        if ( preg_match_all('/<a\s+href=["\'](\/simplelist\/\d+\.aspx)["\'][^>]*>\s*([^<]+?)\s*<\/a>/u', $html, $matches, PREG_SET_ORDER) ) {
            foreach ( $matches as $m ) {
                $monthName = trim($m[2]);
                if ( isset(self::MONTH_MAP[$monthName]) ) {
                    $months[$monthName] = $m[1];
                }
            }
        }

        return $months;
    }

    /**
     * Find the Excel download link on the month's simplelist page.
     * Looks for a link next to icon_xls.gif  →  /page/{id}.aspx
     */
    private function findExcelLink(string $html): ?string
    {
        // Find all <li> blocks (allow any attributes)
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/si', $html, $liMatches)) {
            foreach ($liMatches[1] as $li) {
                if (stripos($li, 'icon_xls.gif') !== false) {
                    // Find anchor with href, allow both quotes
                    if (preg_match('/<a\s+href=["\'](\/page\/\d+\.aspx)["\'][^>]*>/i', $li, $m)) {
                        return $m[1];
                    }
                }
            }
        }
        return null;
    }

    /**
     * Extract node numeric ID from /page/33058.aspx → "33058"
     */
    private function extractNodeId( string $path ): ?string {
        if ( preg_match('/\/page\/(\d+)\.aspx/i', $path, $m) ) {
            return $m[1];
        }
        return null;
    }

    /**
     * Guess file extension from response headers / magic bytes.
     */
    private function guessExtension( string $contentType, string $contentDisp, string $body ): string {
        // From Content-Disposition filename
        if ( preg_match('/filename=["\']?([^"\';\s]+)/i', $contentDisp, $m) ) {
            $ext = strtolower(pathinfo($m[1], PATHINFO_EXTENSION));
            if ( $ext ) return $ext;
        }

        // From Content-Type
        $ctMap = [
            'application/vnd.ms-excel'                                          => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/octet-stream'                                          => 'xls', // assume xls for CBI
        ];
        foreach ( $ctMap as $mime => $ext ) {
            if ( stripos($contentType, $mime) !== false ) {
                return $ext;
            }
        }

        // Magic bytes: XLSX starts with PK (zip), XLS starts with D0CF
        if ( str_starts_with($body, "PK\x03\x04") ) {
            return 'xlsx';
        }
        if ( str_starts_with($body, "\xD0\xCF\x11\xE0") ) {
            return 'xls';
        }

        // Default: CBI historically publishes .xls
        return 'xls';
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DB helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function alreadyExists( string $fileBaseName ): bool {
        return Report::where('file_name', 'like', $fileBaseName . '.%')
            ->exists();
    }

    private function saveError( string $year, string $monthName, string $fileBaseName, string $error ): void {
        Report::updateOrCreate([ 'file_name' => $fileBaseName ], [
            'name'   => $fileBaseName,
            'title'  => "بخش پولی و بانکی - {$year} - {$monthName}",
            'period' => $monthName,
            'error'  => $error,
        ]);
    }
}


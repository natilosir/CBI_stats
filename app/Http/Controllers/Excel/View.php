<?php

namespace App\Http\Controllers\Excel;

use App\Models\CBI\Report;
use App\Models\CBI\Sheet;
use Illuminate\Support\Collection;

trait View {
    use SmartSheetParserTrait;

    public function extractId( $id ) {
        $targetMonth = request('month', '140403');
        $report      = Report::where('id', $id)
            ->firstOrFail();

        $sheet = Sheet::where('report_id', $report->id)
            ->where('index', 0)
            ->first();

        if ( !$sheet ) {
            return response()->json([]);
        }

        $parsed = $this->parseMonetarySheet($sheet, $targetMonth);
        if ( !$parsed ) {
            return response()->json([]);
        }

        $result = $this->buildTreeFromGrid($parsed['grid'], $parsed['cols'] ?? []);
        $result = $this->filterEmptyChildren($result);

        return response()->json($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * حذف گره‌هایی که children خالی دارند با استفاده از کلکشن
     */
    private function filterEmptyChildren( array $nodes ): array {
        return collect($nodes)
            ->map(function ( $node ) {
                if ( isset($node['children']) && is_array($node['children']) ) {
                    $node['children'] = $this->filterEmptyChildren($node['children']);
                }
                return $node;
            })
            ->filter(function ( $node ) {
                if ( isset($node['children']) ) {
                    return !empty($node['children']);
                }
                return true;
            })
            ->values()
            ->toArray();
    }
}
<?php

namespace App\Http\Controllers\Excel;
use App\Core\Excel\SmartSheetParserTrait;
use App\Models\CBI\Report;
use App\Models\CBI\Sheet;

trait View {
    use SmartSheetParserTrait;

    public function extractPortfolioId( $id ) {
        return response()->json($this->extractPortfolio($id), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function extractPortfoliodate( $id, $date ) {
        $Fund = Fund::where('stock_id', $id)
            ->where('period_end_date', $date)
            ->firstOrFail();

        return response()->json([$Fund,$this->extractPortfolio($Fund->id)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function extractPortfolio( $id ) {
        $fund = Report::where('id', $id)
            ->firstOrFail();

        $sheets = Sheet::where('fund_id', $fund->id)
            ->cursor();

        $result = [];

        foreach ( $sheets as $sheet ) {
            $parsed = $this->parseSmartSheet($sheet);

            $filtered = array_filter($parsed, function ( $item ) {
                return !( (int) ( $item['count'] ?? 0 ) === 0
                          && (float) str_replace(',', '', $item['market_price'] ?? '0') === 0.0
                          && (float) str_replace(',', '', $item['total_cost'] ?? '0') === 0.0
                          && (float) str_replace(',', '', $item['net_sale'] ?? '0') === 0.0
                          && (float) ( $item['asset_ratio'] ?? 0 ) === 0.0
                          && (float) ( $item['ratio'] ?? 0 ) === 0.0 );
            });

            // محاسبه مجموع net_sale فقط برای همین شیت
            $totalNetValue = array_sum(array_map(function ( $item ) {
                return (float) str_replace(',', '', $item['net_sale'] ?? '0');
            }, $filtered));

            // افزودن فیلد ratio به هر شرکت در همین شیت
            $filtered = array_map(function ( $item ) use ( $totalNetValue ) {
                $value         = (float) str_replace(',', '', $item['net_sale'] ?? '0');
                $item['ratio'] = $totalNetValue > 0 ? round(( $value / $totalNetValue ) * 100, 2) : 0;
                return $item;
            }, $filtered);

            $result[$sheet->name_value] = $filtered;
        }

        return $result;
    }

}

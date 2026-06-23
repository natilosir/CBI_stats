<?php

namespace App\Core\Excel;

use App\Models\Fund\Fund;
use App\Models\Fund\Sheet;
use Illuminate\Http\Request;

trait View {
    use SmartSheetParserTrait;

    public function extractPortfolioId( Request $request ) {
        $id = $request->id;

        return response()->json($this->extractPortfolio($id), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function extractPortfolio($id)
    {
        $fund = Fund::where('id', $id)->firstOrFail();

        $result = Sheet::where('fund_id', $fund->id)
            ->get()
            ->map(fn ($sheet) => $this->parseSmartSheet($sheet))
            ->filter()
            ->reduce(function ($carry, $item) {
                // merge همه sheetها توی یک associative array
                return array_merge($carry, $item);
            }, []);

        // ---------------------------------------------------------
        // حذف آیتم‌هایی که همه فیلدها صفر هستند (با حفظ key)
        // ---------------------------------------------------------

        $result = collect($result)->filter(function ($item) {

            return !(
                (int) ($item['count'] ?? 0) === 0 &&
                (float) str_replace(',', '', $item['market_price'] ?? '0') === 0.0 &&
                (float) str_replace(',', '', $item['total_cost'] ?? '0') === 0.0 &&
                (float) str_replace(',', '', $item['net_sale'] ?? '0') === 0.0 &&
                (float) ($item['asset_ratio'] ?? 0) === 0.0 &&
                (float) ($item['ratio'] ?? 0) === 0.0
            );

        });

        // ---------------------------------------------------------
        // محاسبه ratio بر اساس net_sale
        // ---------------------------------------------------------

        $totalNetValue = $result->sum(function ($item) {
            return (float) str_replace(',', '', $item['net_sale'] ?? '0');
        });

        $result = $result->map(function ($data) use ($totalNetValue) {

            $value = (float) str_replace(',', '', $data['net_sale'] ?? '0');

            $data['ratio'] = $totalNetValue > 0
                ? round(($value / $totalNetValue) * 100, 2)
                : 0;

            return $data;
        });

        return $result->toArray();
    }

}

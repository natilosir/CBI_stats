<?php

namespace App\Models\Fund;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Model;

class Portfolio extends Model {
    protected $table      = 'fund_portfolios';
    protected $fillable   = [
        'fund_id',
        'stock_id',
        'stock_dic',
        'count',
        'market_price',
        'total_cost',
        'net_sale',
        'ratio',
    ];
    public    $timestamps = false;

    public function fund() {
        return $this->belongsTo(Fund::class);
    }

    public function stock() {
        return $this->belongsTo(Stock::class);
    }
}

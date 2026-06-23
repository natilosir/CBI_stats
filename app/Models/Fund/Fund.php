<?php

namespace App\Models\Fund;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fund extends Model {
    protected $table = 'funds';

    protected $fillable = [
        'hash',
        'name',
        'source',
        'stock_id',
        'title',
        'period',
        'period_end_date',
        'letter_serial',
        'error',
    ];

    public function nameDictionary(): BelongsTo {
        return $this->belongsTo(Dictionary::class, 'name_hash', 'hash_key');
    }

    public function sheets(): HasMany {
        return $this->hasMany(Sheet::class, 'fund_id');
    }

    public function portfolios() {
        return $this->hasMany(Portfolio::class);
    }

    public function funds() {
        return $this->hasMany(Fund::class);
    }

    public function getNameAttribute() {
        return $this->nameDictionary->original_value ?? 'Unknown';
    }
}

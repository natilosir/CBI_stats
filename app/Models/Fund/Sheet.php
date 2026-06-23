<?php

namespace App\Models\Fund;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sheet extends Model {
    public $timestamps = false;

    protected $table = 'fund_sheets';

    protected $fillable = [
        'fund_id',
        'hash',
        'index',
        'total_rows',
        'total_columns',
    ];

    /* ================= Relations ================= */

    public function fund(): BelongsTo {
        return $this->belongsTo(Fund::class);
    }

    public function rows(): HasMany {
        return $this->hasMany(Row::class, 'sheet_id');
    }

    public function name() {
        return $this->belongsTo(Dictionary::class, 'hash', 'key');
    }

    /* ================= Accessors ================= */

    public function getNameValueAttribute(): ?string {
        return $this->name?->value;
    }
}

<?php

namespace App\Models\CBI;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sheet extends Model {
    public $timestamps = false;

    protected $table = 'cbi_sheets';

    protected $fillable = [
        'report_id',
        'hash',
        'index',
        'total_rows',
        'total_columns',
    ];

    /* ================= Relations ================= */

    public function report(): BelongsTo {
        return $this->belongsTo(Report::class, 'report_id');
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

<?php

namespace App\Models\CBI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Row extends Model {
    public    $timestamps = false;
    protected $table      = 'cbi_rows';
    protected $fillable   = [
        'sheet_id',
        'row_index',
        'cell_count',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function sheet(): BelongsTo {
        return $this->belongsTo(Sheet::class, 'sheet_id');
    }

    public function cells(): HasMany {
        return $this->hasMany(Cell::class, 'row_id');
    }
}

<?php

namespace App\Models\CBI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cell extends Model {
    public    $timestamps = false;
    protected $table      = 'cbi_cells';
    protected $fillable   = [
        'row_id',
        'column_index',
        'column_name',
        'hash',
        'row_span',
        'col_span',
        'is_hidden',
        'styles',
        'metadata',
        'is_merged',
    ];

    protected $casts = [
        'metadata'  => 'array',
        'is_merged' => 'boolean',
        'styles'    => 'array',
        'is_hidden' => 'boolean',
    ];

    public function row(): BelongsTo {
        return $this->belongsTo(Row::class, 'row_id');
    }

    public function dictionary(): BelongsTo {
        return $this->belongsTo(Dictionary::class, 'hash', 'key');
    }

    public function getRealValueAttribute() {
        return $this->dictionary->value ?? null;
    }
}
<?php

namespace App\Models\CBI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model {
    protected $table = 'cbi_reports';

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
        'name_hash',
        'id',
        'file_name',
    ];

    public function nameDictionary(): BelongsTo {
        return $this->belongsTo(Dictionary::class, 'name_hash', 'key');
    }

    public function sheets(): HasMany {
        return $this->hasMany(Sheet::class, 'report_id');
    }

    public function funds() {
        return $this->hasMany(Report::class);
    }

    public function getNameAttribute() {
        return $this->nameDictionary->value ?? 'Unknown';
    }
}

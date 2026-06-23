<?php

namespace App\Models\CBI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dictionary extends Model {
    public    $timestamps = false;
    protected $table      = 'cbi_dictionary';
    protected $fillable   = [
        'value',
        'key',
    ];

    public function cells(): HasMany {
        return $this->hasMany(Cell::class, 'hash', 'key');
    }
}

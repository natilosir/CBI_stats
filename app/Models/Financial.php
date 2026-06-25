<?php

namespace App\Models;

use App\Models\CBI\Report;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Financial extends Model {
    protected $table      = 'financial_items';
    public    $timestamps = false;

    protected $fillable = [
        'report_id',
        'parent_id',
        'title',
        'value',
        'growth',
        'growth_yoy',
        'growth_end',
        'share_current',
        'share_previous',
        'share_growth_end',
        'level',
        'month',
    ];
    protected $with     = [ 'subtree' ];

    public function report(): BelongsTo {
        return $this->belongsTo(Report::class, 'report_id');
    }

    // والد
    public function parent(): BelongsTo {
        return $this->belongsTo(self::class, 'parent_id');
    }

    // فرزندان مستقیم
    public function children(): HasMany {
        return $this->hasMany(self::class, 'parent_id');
    }

    // کل زیردرخت
    public function subtree() {
        return $this->children()
            ->with('subtree');
    }

    // اسکوپ‌ها
    public function scopeMonth( $query, $month ) {
        return $query->where('month', $month);
    }

    public function scopeRoots( $query ) {
        return $query->where('level', 0);
    }

    public function scopeLevel( $query, $level ) {
        return $query->where('level', $level);
    }

    public function scopeSearchTitle( $query, $title ) {
        return $query->where('title', 'LIKE', "%{$title}%");
    }

    public function scopeForReport( $query, $reportId ) {
        return $query->where('report_id', $reportId);
    }
}
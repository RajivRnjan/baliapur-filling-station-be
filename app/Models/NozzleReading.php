<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NozzleReading extends Model
{
    protected $fillable = [
        'daily_sale_id',
        'nozzle_id',
        'closing_reading',
        'testing_qty',
        'sale_qty',
        'amount',
    ];

    protected $casts = [
        'closing_reading' => 'decimal:2',
        'sale_qty' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    /**
     * Relationship: A nozzle reading belongs to a daily sale.
     */
    public function dailySale(): BelongsTo
    {
        return $this->belongsTo(DailySale::class);
    }

    /**
     * Relationship: A nozzle reading belongs to a nozzle.
     */
    public function nozzle(): BelongsTo
    {
        return $this->belongsTo(Nozzle::class);
    }
}

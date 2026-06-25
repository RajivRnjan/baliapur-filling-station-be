<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySaleLubricant extends Model
{
    protected $fillable = [
        'daily_sale_id',
        'lubricant_id',
        'quantity',
        'price',
        'amount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the daily sale that owns this lubricant sale.
     */
    public function dailySale(): BelongsTo
    {
        return $this->belongsTo(DailySale::class);
    }

    /**
     * Get the lubricant product details.
     */
    public function lubricant(): BelongsTo
    {
        return $this->belongsTo(Lubricant::class);
    }
}

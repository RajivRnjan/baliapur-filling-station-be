<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySalePumpExpense extends Model
{
    protected $fillable = [
        'daily_sale_id',
        'amount',
        'remark'
    ];

    public function dailySale()
    {
        return $this->belongsTo(DailySale::class);
    }
}

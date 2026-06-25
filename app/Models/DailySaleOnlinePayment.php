<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySaleOnlinePayment extends Model
{
    protected $fillable = ['daily_sale_id', 'name', 'amount'];
    
    public function dailySale() {
        return $this->belongsTo(DailySale::class);
    }
}

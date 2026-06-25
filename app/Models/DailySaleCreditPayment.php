<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySaleCreditPayment extends Model
{
    protected $fillable = ['daily_sale_id', 'creditor_id', 'amount'];
    
    public function dailySale() {
        return $this->belongsTo(DailySale::class);
    }
    
    public function creditor() {
        return $this->belongsTo(Creditor::class);
    }
}

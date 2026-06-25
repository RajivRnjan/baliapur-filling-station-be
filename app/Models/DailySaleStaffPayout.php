<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySaleStaffPayout extends Model
{
    protected $fillable = ['daily_sale_id', 'user_id', 'name', 'amount', 'salary_month', 'salary_year'];
    
    public function dailySale() {
        return $this->belongsTo(DailySale::class);
    }
}

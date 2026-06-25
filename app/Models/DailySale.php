<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailySale extends Model
{
    protected $fillable = [
        'date',
        'rate_ms',
        'rate_hsd',
        'rate_xp',
        'lubricant_sale',
        'density',
        'credit_payment_received',
        'ac_transfer',
        'online',
        'online_creditor_name',
        'creditor',
        'expense',
        'staff',
        'owner_1',
        'owner_2',
        'cash_collection',
        'coin',
        'miss',
        'balance_in_pump',
    ];

    protected $casts = [
        'date' => 'date:d/m/Y',
        'rate_ms' => 'decimal:2',
        'rate_hsd' => 'decimal:2',
        'rate_xp' => 'decimal:2',
        'lubricant_sale' => 'decimal:2',
        'density' => 'decimal:2',
        'credit_payment_received' => 'decimal:2',
        'ac_transfer' => 'decimal:2',
        'online' => 'decimal:2',
        'creditor' => 'decimal:2',
        'expense' => 'decimal:2',
        'staff' => 'decimal:2',
        'owner_1' => 'decimal:2',
        'owner_2' => 'decimal:2',
        'cash_collection' => 'decimal:2',
        'coin' => 'decimal:2',
        'miss' => 'decimal:2',
        'balance_in_pump' => 'decimal:2',
    ];

    /**
     * Relationship: A daily sale has many nozzle readings.
     */
    public function nozzleReadings(): HasMany
    {
        return $this->hasMany(NozzleReading::class);
    }

    /**
     * Relationship: A daily sale has many lubricant item sales.
     */
    public function lubricants()
    {
        return $this->hasMany(DailySaleLubricant::class);
    }
    
    public function creditPayments()
    {
        return $this->hasMany(DailySaleCreditPayment::class);
    }
    
    public function creditGivens()
    {
        return $this->hasMany(DailySaleCreditGiven::class);
    }
    
    public function onlinePayments()
    {
        return $this->hasMany(DailySaleOnlinePayment::class);
    }
    
    public function staffPayouts()
    {
        return $this->hasMany(DailySaleStaffPayout::class);
    }

    public function pumpExpenses()
    {
        return $this->hasMany(DailySalePumpExpense::class);
    }

    public function setDateAttribute($value)
    {
        if (is_string($value) && str_contains($value, '/')) {
            $this->attributes['date'] = \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
        } else {
            $this->attributes['date'] = $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : null;
        }
    }
}

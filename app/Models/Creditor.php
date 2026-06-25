<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Creditor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'opening_balance',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
    ];

    protected $appends = [
        'current_balance',
    ];

    public function transactions()
    {
        return $this->hasMany(CreditorTransaction::class);
    }

    /**
     * Get the dynamic current running balance of the creditor.
     * Balance = opening_balance + sum(credit_amount) - sum(payment_amount)
     */
    public function getCurrentBalanceAttribute()
    {
        $totalCredit = floatval($this->transactions()->sum('credit_amount'));
        $totalPayment = floatval($this->transactions()->sum('payment_amount'));
        return round(floatval($this->opening_balance) + $totalCredit - $totalPayment, 2);
    }
}

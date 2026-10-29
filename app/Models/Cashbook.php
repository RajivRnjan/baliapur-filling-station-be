<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cashbook extends Model
{
    protected $fillable = [
        'date',
        'type',
        'particulars',
        'cash_amount',
        'bank_amount',
        'coin',
        'remark'
    ];

    protected $casts = [
        'date' => 'date',
        'cash_amount' => 'decimal:2',
        'bank_amount' => 'decimal:2',
        'coin' => 'decimal:2',
    ];
}

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
        'remark'
    ];
}

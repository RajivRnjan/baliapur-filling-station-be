<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditorTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'creditor_id',
        'date',
        'credit_amount',
        'payment_amount',
        'remark',
    ];

    protected $casts = [
        'date' => 'date:d/m/Y',
        'credit_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
    ];

    public function creditor()
    {
        return $this->belongsTo(Creditor::class);
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

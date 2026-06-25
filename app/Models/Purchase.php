<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $primaryKey = 'sl_no';
    
    protected $fillable = [
        'date',
        'ms',
        'hsd',
        'xp',
        'is_opening',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date:d/m/Y',
        'is_opening' => 'boolean',
    ];

    public function setDateAttribute($value)
    {
        if (is_string($value) && str_contains($value, '/')) {
            $this->attributes['date'] = \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
        } else {
            $this->attributes['date'] = $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : null;
        }
    }
}

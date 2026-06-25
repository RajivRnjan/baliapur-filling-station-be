<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nozzle extends Model
{
    protected $fillable = [
        'nozzle_no',
        'fuel_type',
        'opening_reading',
    ];
}

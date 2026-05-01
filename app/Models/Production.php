<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Production extends Model
{
    protected $fillable = [
        'variety_type',
        'total_length',
        'ready_production',
        'machine_id',
        'employee_id',
        'factory_id',
        'manager_id',
        'shift_start',
        'shift_end'
    ];
}

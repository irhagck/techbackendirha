<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'employee_id',
        'shift_starttime',
        'shift_endtime',
        'timestamp',
        'user_id'
    ];
}
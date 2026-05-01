<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendence extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'timestamp',
        'production_id'
    ];
}
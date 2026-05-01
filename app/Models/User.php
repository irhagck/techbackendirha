<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
     'name',
     'email',
     'password',
     'phone_no',
     'cnic',
     'address',
     'pic',
     'role',
     'employee_details',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tickets extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'priorty',
        'status',


    ];
}

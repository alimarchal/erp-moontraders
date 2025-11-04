<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Uom extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uom_name',
        'symbol',
        'description',
        'must_be_whole_number',
        'enabled',
    ];

    protected $casts = [
        'must_be_whole_number' => 'boolean',
        'enabled' => 'boolean',
    ];
}

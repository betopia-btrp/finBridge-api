<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Borrower extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'mfi_id',
        'name',
        'phone',
        'nid',
        'address',
        'status'
    ];
}

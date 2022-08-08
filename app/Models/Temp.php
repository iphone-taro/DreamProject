<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temp extends Model {
    use HasFactory;
    protected $primaryKey = 'temp_id';
    public $incrementing = false;
}

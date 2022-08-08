<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mute extends Model
{
    use HasFactory;

    // プライマリキー設定
    protected $primaryKey = ['user_id', 'mute_id'];
    
    // increment無効化
    public $incrementing = false;
}

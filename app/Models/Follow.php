<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Follow extends Model
{
    use HasFactory;

    // プライマリキー設定
    protected $primaryKey = ['user_id', 'follow_id'];
    
    // increment無効化
    public $incrementing = false;

}

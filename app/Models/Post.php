<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;
    
    protected $primaryKey = 'post_id'; //pkの変更
    public $incrementing = false;   //pkが自動増加整数じゃない宣言
}

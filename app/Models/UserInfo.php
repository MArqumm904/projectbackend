<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInfo extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'contact',
        'email',
        'languages_spoken',
        'website',
        'social_link',
        'gender',
        'date_of_birth',
    ];
}

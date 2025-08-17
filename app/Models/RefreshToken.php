<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    //
    protected $fillable = ['email', 'token', 'revoked', 'expires_at'];

    // relationship to user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

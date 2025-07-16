<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    //
    protected $fillable = ['email', 'token', 'revoked', 'expires_at'];

    // Relation to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

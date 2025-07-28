<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Support\Str;

class Blog extends Model
{
    //
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'author',
        'content',
        'image'
    ];

    // serializtion (converting it to be more readable)
    protected function serializeDate(DateTimeInterface $date) {
        return $date->format('d-m-Y');
    }

    // blog's author maps to user's email
    public function author(){
        return $this->belongsTo(User::class, 'author', 'email');
    }

}

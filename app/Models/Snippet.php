<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Snippet extends Model
{
    protected $guarded = [];

    public function forks()
    {
        return $this->hasMany(Snippet::class, 'forked_id');
    }

    public function isAfork()
    {
        return !!$this->parent();
    }
    public function parent()
    {
        return $this->belongsTo(Snippet::class, 'forked_id');
    }
}

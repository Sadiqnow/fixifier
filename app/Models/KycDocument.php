<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycDocument extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['storage_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

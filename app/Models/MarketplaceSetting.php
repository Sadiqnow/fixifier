<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['values' => 'array', 'version' => 'integer'];
    }
}

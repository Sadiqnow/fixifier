<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class AppendOnlyModel extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('This record is append-only.'));
        static::deleting(fn () => throw new \LogicException('This record is append-only.'));
    }
}

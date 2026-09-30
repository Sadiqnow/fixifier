<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TechnicianProfile extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_available' => 'boolean', 'verified_at' => 'datetime', 'verification_version' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function decisions()
    {
        return $this->hasMany(TechnicianDecision::class);
    }
}

<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public function technicianProfile()
    {
        return $this->hasOne(TechnicianProfile::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'technician_id');
    }

    public function assignedBookings()
    {
        return $this->hasMany(Booking::class, 'technician_id');
    }

    public function documents()
    {
        return $this->hasMany(KycDocument::class);
    }

    use HasApiTokens,HasFactory;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'password' => 'hashed', 'role' => UserRole::class, 'email_verified_at' => 'datetime'];
    }
}

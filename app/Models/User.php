<?php
namespace App\Models;
use App\Enums\UserRole; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Foundation\Auth\User as Authenticatable; use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable { use HasApiTokens,HasFactory; protected $fillable=['name','email','phone','password','role']; protected $hidden=['password','remember_token']; protected function casts():array{return ['password'=>'hashed','role'=>UserRole::class,'email_verified_at'=>'datetime'];} }

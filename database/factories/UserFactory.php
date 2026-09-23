<?php
namespace Database\Factories;
use App\Enums\UserRole; use Illuminate\Database\Eloquent\Factories\Factory; use Illuminate\Support\Facades\Hash; use Illuminate\Support\Str;
class UserFactory extends Factory { protected static ?string $password; public function definition():array{return ['name'=>fake()->name(),'email'=>fake()->unique()->safeEmail(),'phone'=>fake()->unique()->numerify('+23480########'),'email_verified_at'=>now(),'password'=>static::$password??=Hash::make('ChangeMe123!'),'role'=>UserRole::Customer,'remember_token'=>Str::random(10)];} }

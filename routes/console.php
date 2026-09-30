<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

Artisan::command('fixifier:admin', function () {
    $name = $this->ask('Administrator name');
    $email = strtolower(trim((string) $this->ask('Administrator email')));
    $password = $this->secret('Password (at least 12 characters)');
    $confirmation = $this->secret('Confirm password');
    $validator = Validator::make(compact('name', 'email', 'password'), ['name' => 'required|string|max:100', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12']);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

return 1;
    }
    if ($password !== $confirmation) {
        $this->error('Passwords did not match.');

        return 1;
    }
    User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => 'admin', 'is_active' => true]);
    $this->info('Administrator created. Sign in at /admin/login.');

    return 0;
})->purpose('Create an administrator interactively without a shared default password');

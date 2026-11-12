<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'cubiertas:create-admin';

    protected $description = 'Crea un administrador sin contraseña predeterminada (entrada interactiva privada).';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Nombre'), 'email' => $this->ask('Correo'), 'password' => $this->secret('Contraseña (mínimo 12 caracteres)'), 'password_confirmation' => $this->secret('Repetir contraseña')];
        $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        $user = new User;
        $user->forceFill([...array_intersect_key($data, array_flip(['name', 'email', 'password'])), 'role' => 'admin', 'active' => true, 'can_verify_payments' => true])->save();
        $this->info('Administrador creado.');

        return self::SUCCESS;
    }
}

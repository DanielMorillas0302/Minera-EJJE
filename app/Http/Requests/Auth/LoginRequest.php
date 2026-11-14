<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'Ingresa tu contraseña.',
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $usuarioExistente = User::where('email', $this->string('email'))->first();
        if ($usuarioExistente && $usuarioExistente->estaBloqueado()) {
            $minutosRestantes = (int) ceil(now()->diffInSeconds($usuarioExistente->bloqueado_hasta) / 60);

            throw ValidationException::withMessages([
                'email' => "Tu cuenta ha sido bloqueada temporalmente por exceso de intentos fallidos. Intenta nuevamente en {$minutosRestantes} minuto(s).",
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            if ($usuarioExistente) {
                $usuarioExistente->registrarIntentoFallido();
                if ($usuarioExistente->estaBloqueado()) {
                    activity()
                        ->performedOn($usuarioExistente)
                        ->withProperties(['ip' => $this->ip(), 'email' => $this->string('email')])
                        ->log('Cuenta bloqueada temporalmente tras 5 intentos fallidos');

                    throw ValidationException::withMessages([
                        'email' => 'Tu cuenta ha sido bloqueada temporalmente por 15 minutos debido a 5 intentos fallidos de inicio de sesión.',
                    ]);
                }
            }

            throw ValidationException::withMessages([
                'email' => 'Las credenciales ingresadas no son válidas.',
            ]);
        }

        $usuario = Auth::user();
        if (! $usuario || ! $usuario->activo) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Tu cuenta se encuentra desactivada. Contacta al administrador.',
            ]);
        }

        $usuario->reiniciarIntentosFallidos();
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Demasiados intentos fallidos. Intenta nuevamente en {$seconds} segundos.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}

<?php

namespace App\Http\Requests\Auth;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Services\Auth\CollaboratorAccessService;
use Illuminate\Auth\Events\Lockout;
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

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'identifier' => ['nullable', 'string'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $identifier = (string) ($this->input('identifier') ?: $this->session()->get('login.identifier'));

        if ($identifier === '') {
            throw ValidationException::withMessages([
                'identifier' => AuthenticatedSessionController::GENERIC_LOGIN_ERROR,
            ]);
        }

        $user = app(CollaboratorAccessService::class)->findUserByIdentifier($identifier);

        if (! $user || ! $user->active) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'password' => trans('auth.failed'),
            ]);
        }

        if (! Auth::attempt(['email' => $user->email, 'password' => $this->input('password')], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'password' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        $identifier = (string) ($this->input('identifier') ?: $this->session()->get('login.identifier', ''));

        return Str::transliterate(Str::lower($identifier).'|'.$this->ip());
    }
}

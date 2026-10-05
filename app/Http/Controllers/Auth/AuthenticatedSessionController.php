<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\CollaboratorAccessService;
use App\Support\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public const GENERIC_LOGIN_ERROR = 'Não foi possível continuar com os dados informados.';

    public function __construct(private CollaboratorAccessService $access) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->boolean('change')) {
            $request->session()->forget(['login.step', 'login.identifier', 'login.user_id', 'login.collaborator_id']);

            return redirect()->route('login');
        }

        return view('auth.login', [
            'step' => session('login.step', 'identifier'),
            'identifier' => session('login.identifier'),
        ]);
    }

    public function identify(Request $request, CollaboratorAccessService $access): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $identifier = trim($validated['identifier']);

        $user = $access->findUserByIdentifier($identifier);

        if ($user) {
            if (! $user->active) {
                throw ValidationException::withMessages([
                    'identifier' => self::GENERIC_LOGIN_ERROR,
                ]);
            }

            $request->session()->put('login.step', 'password');
            $request->session()->put('login.identifier', $user->email);
            $request->session()->put('login.user_id', $user->id);

            return redirect()->route('login');
        }

        if (Document::looksLikeCpf($identifier)) {
            $collaborator = $access->findCollaboratorByCpf($identifier);

            if ($collaborator && $access->canStartFirstAccess($collaborator)) {
                $request->session()->put('login.step', 'first_access');
                $request->session()->put('login.identifier', $identifier);
                $request->session()->put('login.collaborator_id', $collaborator->id);

                return redirect()->route('login.first-access');
            }
        }

        throw ValidationException::withMessages([
            'identifier' => self::GENERIC_LOGIN_ERROR,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->forget(['login.step', 'login.identifier', 'login.user_id', 'login.collaborator_id']);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function createFirstAccess(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('login.step') !== 'first_access' || ! $request->session()->get('login.collaborator_id')) {
            return redirect()->route('login');
        }

        return view('auth.first-access', [
            'identifier' => $request->session()->get('login.identifier'),
        ]);
    }

    public function storeFirstAccess(Request $request): RedirectResponse
    {
        if ($request->session()->get('login.step') !== 'first_access') {
            return redirect()->route('login');
        }

        $collaboratorId = $request->session()->get('login.collaborator_id');
        $collaborator = $this->access->findCollaboratorByCpf((string) $request->session()->get('login.identifier'));

        if (! $collaborator || $collaborator->id !== $collaboratorId || ! $this->access->canStartFirstAccess($collaborator)) {
            throw ValidationException::withMessages([
                'email' => self::GENERIC_LOGIN_ERROR,
            ]);
        }

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where('active', true),
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $this->access->createFromFirstAccess($collaborator, $validated['email'], $validated['password']);

        Auth::login($user);
        $request->session()->forget(['login.step', 'login.identifier', 'login.user_id', 'login.collaborator_id']);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

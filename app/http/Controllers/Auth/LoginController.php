<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\SettingService;

class LoginController extends Controller {
    use AuthenticatesUsers;

    public function __construct(protected SettingService $settingService) {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Normalize credentials.
     */
    protected function credentials(Request $request): array {
        return [
            'email' => strtolower(trim($request->input('email'))),
            'password' => $request->input('password'),
        ];
    }

    /**
     * Custom authentication flow.
     */
    protected function attemptLogin(Request $request): bool {        
        $email = strtolower(trim($request->input('email')));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        
        $maxAttempts = $this->settingService->get('max_login_attempts', 5);
        $lockMinutes = $this->settingService->get('account_lock_minutes', 30);

        /*
        |--------------------------------------------------------------------------
        | User Not Found
        |--------------------------------------------------------------------------
        */
        if (!$user) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Soft Deleted
        |--------------------------------------------------------------------------
        */
        if ($user->deleted_at !== null) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Automatic Unlock
        |--------------------------------------------------------------------------
        */
        if ($user->locked_until && now()->greaterThanOrEqualTo($user->locked_until)) {
            $user->update([
                'failed_attempts' => 0,
                'locked_until'    => null,
                'status'          => 'active',
            ]);

            $user->refresh();
        }

        /*
        |--------------------------------------------------------------------------
        | Status Validation
        |--------------------------------------------------------------------------
        */
        if ($user->status !== 'active') {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Account Still Locked
        |--------------------------------------------------------------------------
        */
        if ($user->locked_until && now()->lt($user->locked_until)) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Password Validation
        |--------------------------------------------------------------------------
        */
        if (!Hash::check($request->password, $user->password)) {
            $attempts = $user->failed_attempts + 1;
            if ($attempts >= $maxAttempts) {
                $user->update([
                    'failed_attempts' => $attempts,
                    'locked_until'    => now()->addMinutes($lockMinutes),
                    'status'          => 'locked',
                ]);
            } else {
                $user->update([
                    'failed_attempts' => $attempts,
                ]);
            }

            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Perform Login
        |--------------------------------------------------------------------------
        */
        return Auth::loginUsingId(
            $user->id,
            $request->filled('remember')
        ) ? true : false;
    }

    /**
     * Login validation.
     */
    protected function validateLogin(Request $request) {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
    }

    /**
     * Failed login responses.
     */
    protected function sendFailedLoginResponse(Request $request) {
        $email = strtolower(trim($request->input('email')));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        $maxAttempts = $this->settingService->get('max_login_attempts', 5);
        
        if ($user) {
            if ($user->locked_until && now()->lt($user->locked_until)) {
                throw ValidationException::withMessages([
                    'email' => [
                        'Your account is locked until '
                        . $user->locked_until->format('d M Y h:i A')
                    ],
                ]);
            }

            switch ($user->status) {
                case 'inactive':
                    throw ValidationException::withMessages([
                        'email' => ['Your account is inactive.'],
                    ]);

                case 'suspended':
                    throw ValidationException::withMessages([
                        'email' => ['Your account has been suspended.'],
                    ]);

                case 'locked':
                    throw ValidationException::withMessages([
                        'email' => ['Your account is locked.'],
                    ]);
            }

            if ($user->failed_attempts > 0 && $user->failed_attempts < $maxAttempts) {
                $remaining = $maxAttempts - $user->failed_attempts;
                throw ValidationException::withMessages([
                    'email' => [
                        "Invalid credentials. {$remaining} attempt(s) remaining."
                    ],
                ]);
            }
        }

        throw ValidationException::withMessages([
            'email' => ['Invalid credentials.'],
        ]);
    }

    /**
     * Successful login response.
     */
    protected function sendLoginResponse(Request $request) {
        $request->session()->regenerate();
        $user = auth()->user();

        $request->session()->put([
            'user_id' => $user->id,
            'login_at' => now(),
        ]);
        
        $user->update([
            'failed_attempts'     => 0,
            'locked_until'        => null,
            'status'              => 'active',
            'last_login_at'       => now(),
            'last_login_ip'       => $request->ip(),
            'last_user_agent'     => substr(
                (string) $request->userAgent(),
                0,
                65535
            ),
            'device_fingerprint'  => hash(
                'sha256',
                $request->ip() . '|' . $request->userAgent()
            ),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Load Roles
        |--------------------------------------------------------------------------
        */
        $user->load('roles');

        /*
        |--------------------------------------------------------------------------
        | Redirect Through Limitless
        |--------------------------------------------------------------------------
        */
        return redirect('dashboard');
    }

    /**
     * Logout.
     */
    public function logout(Request $request) {
        $user = $this->guard()->user();
        if ($user) {
            $user->update([
                'last_logout_at' => now(),
            ]);
        }

        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
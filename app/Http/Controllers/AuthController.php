<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Show the login form
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Show the register form
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Handle user registration
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Sync user to E-Commerce app
        $this->syncUserToEcommerce($user, $request->password);

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Registration successful! You are now logged in to both Foodpanda and E-Commerce systems.');
    }

    /**
     * Handle user login with SSO
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            // Generate SSO token for cross-app authentication
            $ssoToken = $this->generateSSOToken($user);

            // Store SSO token in session
            session(['sso_token' => $ssoToken]);

            // Notify E-Commerce app about the login
            $this->notifyEcommerceLogin($user, $ssoToken);

            return redirect()->route('dashboard')
                ->with('success', 'Login successful! You are now logged in to both systems.');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Handle SSO auto-login from E-Commerce
     */
    public function ssoAutoLogin(Request $request)
    {
        $ssoToken = $request->input('token');

        if (!$ssoToken) {
            return redirect()->route('login')
                ->with('error', 'Invalid SSO token');
        }

        $user = $this->validateSSOToken($ssoToken);

        if (!$user) {
            return redirect()->route('login')
                ->with('error', 'SSO token expired or invalid. Please login again.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        session(['sso_token' => $ssoToken]);

        return redirect()->route('dashboard')
            ->with('success', 'Automatically logged in via SSO from E-Commerce!');
    }

    /**
     * Handle user logout
     */
    public function logout(Request $request)
    {
        // Notify E-Commerce app about logout
        if (session('sso_token')) {
            $this->notifyEcommerceLogout(session('sso_token'));
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'You have been logged out from both systems.');
    }

    /**
     * Show dashboard
     */
    public function dashboard()
    {
        $user = Auth::user();
        $ecommerceUrl = env('ECOMMERCE_APP_URL', 'http://localhost:8000');
        $ssoToken = session('sso_token', '');

        return view('dashboard', compact('user', 'ecommerceUrl', 'ssoToken'));
    }

    /**
     * Generate SSO token
     */
    private function generateSSOToken($user)
    {
        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'timestamp' => time(),
        ];

        $secretKey = env('SSO_SECRET_KEY', 'your-shared-secret-key');
        $tokenData = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $tokenData, $secretKey);

        return $tokenData . '.' . $signature;
    }

    /**
     * Sync user to E-Commerce app
     */
    private function syncUserToEcommerce($user, $plainPassword)
    {
        try {
            $ecommerceUrl = env('ECOMMERCE_APP_URL');
            if (!$ecommerceUrl) {
                return;
            }

            Http::timeout(5)->post($ecommerceUrl . '/api/sync-user', [
                'name' => $user->name,
                'email' => $user->email,
                'password' => $plainPassword,
                'secret' => env('SSO_SECRET_KEY'),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to sync user to E-Commerce: ' . $e->getMessage());
        }
    }

    /**
     * Notify E-Commerce app about login
     */
    private function notifyEcommerceLogin($user, $ssoToken)
    {
        try {
            $ecommerceUrl = env('ECOMMERCE_APP_URL');
            if (!$ecommerceUrl) {
                return;
            }

            Http::timeout(5)->post($ecommerceUrl . '/api/sso-login', [
                'sso_token' => $ssoToken,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to notify E-Commerce about login: ' . $e->getMessage());
        }
    }

    /**
     * Notify E-Commerce app about logout
     */
    private function notifyEcommerceLogout($ssoToken)
    {
        try {
            $ecommerceUrl = env('ECOMMERCE_APP_URL');
            if (!$ecommerceUrl) {
                return;
            }

            Http::timeout(5)->post($ecommerceUrl . '/api/sso-logout', [
                'sso_token' => $ssoToken,
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to notify E-Commerce about logout: ' . $e->getMessage());
        }
    }

    /**
     * API endpoint to handle SSO login from E-Commerce
     */
    public function apiSSOLogin(Request $request)
    {
        $ssoToken = $request->input('sso_token');

        if (!$ssoToken) {
            return response()->json(['error' => 'SSO token required'], 400);
        }

        $user = $this->validateSSOToken($ssoToken);

        if (!$user) {
            return response()->json(['error' => 'Invalid SSO token'], 401);
        }

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    /**
     * Validate SSO token
     */
    private function validateSSOToken($token)
    {
        try {
            list($tokenData, $signature) = explode('.', $token);
            $secretKey = env('SSO_SECRET_KEY', 'your-shared-secret-key');
            $expectedSignature = hash_hmac('sha256', $tokenData, $secretKey);

            if (!hash_equals($expectedSignature, $signature)) {
                return null;
            }

            $payload = json_decode(base64_decode($tokenData), true);

            // Check if token is not too old (1 hour expiry)
            if (time() - $payload['timestamp'] > 3600) {
                return null;
            }

            return User::where('email', $payload['email'])->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * API endpoint to sync user from E-Commerce
     */
    public function apiSyncUser(Request $request)
    {
        $secret = $request->input('secret');

        if ($secret !== env('SSO_SECRET_KEY')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);
        }

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }
}

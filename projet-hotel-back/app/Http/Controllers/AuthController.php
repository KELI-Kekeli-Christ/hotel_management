<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRules;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function __construct()
    {
        // Middleware JWTAuth : protège toutes les routes sauf celles listées
        $this->middleware('auth:api', ['except' => ['login', 'register', 'forgotPassword', 'resetPassword']]);
    }

    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'     => 'required|string|max:255',
                'email'    => 'required|string|email|max:255|unique:users',
                'password' => ['required', 'confirmed', 'min:8'],
            ]);

            DB::beginTransaction();

            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // Vérifier que le rôle existe avant de l'assigner
            $clientRole = Role::where('name', 'Client')
                ->where('guard_name', 'api')
                ->first();

            if (!$clientRole) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Le rôle Client n\'existe pas dans le système. Veuillez contacter l\'administrateur.',
                ], 500);
            }

            $user->assignRole($clientRole);

            DB::commit();

            // Recharger l'utilisateur avec les relations
            $user->load('roles');

            return response()->json([
                'message' => 'Inscription réussie. Veuillez vous connecter...',
                'user'    => $user,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur de validation',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de l\'inscription: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Une erreur est survenue lors de l\'inscription. Veuillez réessayer.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Les identifiants fournis sont incorrects.'], 401);
        }

        $user = auth()->user();
        $user->load('roles');
        $user->permissions_list = $user->getAllPermissions()->pluck('name');

        return $this->createNewToken($token, $user);
    }

    public function logout()
    {
        try {
            JWTAuth::parseToken()->invalidate();
            return response()->json(['message' => 'Déconnexion réussie.']);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token invalide ou manquant.'], 400);
        }
    }

    public function me()
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $user->load('roles');
        $user->permissions_list = $user->getAllPermissions()->pluck('name');

        return response()->json($user);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Un lien de réinitialisation a été envoyé à votre adresse e-mail.'])
            : response()->json(['message' => 'Impossible d\'envoyer le lien de réinitialisation.'], 500);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
        ]);

        $status = Password::reset(
            $validated,
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Le token de réinitialisation est invalide ou a expiré.'], 400);
        }

        $user = User::where('email', $validated['email'])->first();
        if (! $user) {
            return response()->json(['message' => 'Utilisateur introuvable après reset.'], 500);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message'      => 'Votre mot de passe a été réinitialisé avec succès.',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ]);
    }

    protected function createNewToken($token, $user = null)
    {
        $response = [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => JWTAuth::factory()->getTTL() * 60,
        ];

        if ($user) {
            $response['user'] = $user;
        }

        return response()->json($response);
    }

    public function refresh()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            return $this->createNewToken($newToken, auth()->user());
        } catch (JWTException $e) {
            return response()->json(['message' => 'Impossible de rafraîchir le token.'], 401);
        }
    }
}

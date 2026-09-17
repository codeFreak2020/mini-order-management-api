<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\UserIdentifierResolverManager;
use App\Support\ApiMessages;
use App\Support\ApiResponse;
use App\Support\HttpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Contracts\Providers\JWT as JWTProvider;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserIdentifierResolverManager $identifierResolver,
    ) {
    }

    // register a new user and return a JSON response with the user data and an access/refresh token pair
    public function register(RegisterRequest $request): JsonResponse
    {
        //i can use here $request->all() but it will use the parameters that are not validated, so i will use $request->validated() to get only the validated parameters
        $user = User::create($request->validated());
        //array destructuring to get the access token and refresh token from the issueTokenPair method
        [$token, $refreshToken] = $this->issueTokenPair($user);
        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
            'refresh_token' => $refreshToken,
        ], ApiMessages::USER_REGISTERED, HttpStatus::CREATED);
    }

    // login an existing user and return a JSON response with the user data and an access/refresh token pair
    public function login(LoginRequest $request): JsonResponse
    {
        $guard = auth('api');
        $guard->claims([]);
        $guard->factory()->emptyClaims();
        $guard->setTTL(config('jwt.ttl'));
        $identifier = $request->validated('identifier');
        $password = $request->validated('password');
        //this is for the dependency injection of the UserIdentifierResolverManager class, so this feature follow the solid principle O in future extensions if we add new parameter so our code is open for extension but closed for modifications
        $user = $this->identifierResolver->resolve($identifier);
        $token = $user ? $guard->attempt(['email' => $user->email, 'password' => $password]) : false;
        if (!$token) {
            throw ValidationException::withMessages([
                'identifier' => [ApiMessages::INVALID_CREDENTIALS],
            ]);
        }
        $user = $guard->user();
        $refreshToken = $this->issueRefreshToken($user);
        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
            'refresh_token' => $refreshToken,
        ], ApiMessages::LOGGED_IN);
    }

    // exchange a valid refresh token for a new access/refresh token pair
    public function refresh(RefreshRequest $request): JsonResponse
    {
        $refreshToken = $request->validated('refresh_token');
        $guard = auth('api');
        try {
            // Fully validate the token (signature, required claims, expiry, blacklist).
            $guard->setToken($refreshToken)->payload();
            // Read the raw claims straight from the token, avoiding any stale claims that may have leaked into the payload factory within the same process.
            $claims = app(JWTProvider::class)->decode($refreshToken);
            if (($claims['token_type'] ?? null) !== 'refresh') {
                throw ValidationException::withMessages([
                    'refresh_token' => [ApiMessages::INVALID_REFRESH_TOKEN],
                ]);
            }
            $user = User::find($claims['sub'] ?? null);
            if (!$user) {
                throw ValidationException::withMessages([
                    'refresh_token' => [ApiMessages::INVALID_REFRESH_TOKEN],
                ]);
            }
            // Rotate the refresh token blacklist the old one before issuing a new pair.
            $guard->invalidate();
            [$token, $newRefreshToken] = $this->issueTokenPair($user);
            return ApiResponse::success([
                'user' => new UserResource($user),
                'token' => $token,
                'refresh_token' => $newRefreshToken,
            ], ApiMessages::TOKEN_REFRESHED);
        } catch (JWTException $e) {
            throw ValidationException::withMessages([
                'refresh_token' => [ApiMessages::INVALID_REFRESH_TOKEN],
            ]);
        }
    }

    // logout the authenticated user by invalidating their JWT and return a JSON response confirming the logout
    public function logout(): JsonResponse
    {
        auth('api')->logout();
        return ApiResponse::success(message: ApiMessages::LOGGED_OUT);
    }

    private function issueTokenPair(User $user): array
    {
        return [
            $this->issueAccessToken($user),
            $this->issueRefreshToken($user),
        ];
    }

    private function issueAccessToken(User $user): string
    {
        $guard = auth('api');
        $guard->factory()->emptyClaims();
        $guard->claims([]);
        return $guard->setTTL(config('jwt.ttl'))->login($user);
    }

    private function issueRefreshToken(User $user): string
    {
        $guard = auth('api');
        $guard->factory()->emptyClaims();
        $guard->claims(['token_type' => 'refresh']);
        return $guard->setTTL(config('jwt.refresh_ttl'))->tokenById($user->getAuthIdentifier());
    }
}

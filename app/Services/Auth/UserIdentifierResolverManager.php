<?php

namespace App\Services\Auth;

use App\Contracts\UserIdentifierResolver;
use App\Models\User;

class UserIdentifierResolverManager
{
    
    public function __construct(private readonly iterable $resolvers)
    {
    }

    public function resolve(string $identifier): ?User
    {
        foreach ($this->resolvers as $resolver) {
            if ($user = $resolver->resolve($identifier)) {
                return $user;
            }
        }

        return null;
    }
}


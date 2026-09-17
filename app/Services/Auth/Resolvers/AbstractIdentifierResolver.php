<?php

namespace App\Services\Auth\Resolvers;

use App\Contracts\UserIdentifierResolver;
use App\Models\User;

abstract class AbstractIdentifierResolver implements UserIdentifierResolver
{
   
    abstract protected function column(): string;

    public function resolve(string $identifier): ?User
    {
        return User::query()->where($this->column(), $identifier)->first();
    }
}


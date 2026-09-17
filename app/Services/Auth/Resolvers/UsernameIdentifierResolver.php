<?php

namespace App\Services\Auth\Resolvers;

final class UsernameIdentifierResolver extends AbstractIdentifierResolver
{
    protected function column(): string
    {
        return 'username';
    }
}


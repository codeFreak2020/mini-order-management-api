<?php

namespace App\Services\Auth\Resolvers;

final class EmailIdentifierResolver extends AbstractIdentifierResolver
{
    protected function column(): string
    {
        return 'email';
    }
}

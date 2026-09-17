<?php

namespace App\Services\Auth\Resolvers;

final class PhoneIdentifierResolver extends AbstractIdentifierResolver
{
    protected function column(): string
    {
        return 'phone';
    }
}


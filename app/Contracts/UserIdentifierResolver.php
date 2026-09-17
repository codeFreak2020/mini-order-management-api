<?php

namespace App\Contracts;

use App\Models\User;

interface UserIdentifierResolver
{
    public function resolve(string $identifier): ?User;
}

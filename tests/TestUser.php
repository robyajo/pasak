<?php

namespace Internal\Pasak\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Internal\Pasak\Concerns\HasSecurityRelations;

class TestUser extends Authenticatable
{
    use HasSecurityRelations;

    protected $table = 'users';

    protected $guarded = [];

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}

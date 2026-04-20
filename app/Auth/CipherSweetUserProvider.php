<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Custom user provider that uses CipherSweet blind indexes
 * to look up users by encrypted email.
 *
 * This replaces the default WHERE email = ? query with
 * whereBlind('email', 'email_index', $value) so that
 * Auth::attempt(), password reset, email verification,
 * and all other auth flows work with encrypted emails.
 *
 * Registered in AuthServiceProvider::boot().
 */
class CipherSweetUserProvider extends EloquentUserProvider
{
    /**
     * Retrieve a user by the given credentials.
     *
     * This is called by Auth::attempt() and Auth::validate().
     * The default implementation does WHERE col = val for each credential
     * except 'password'. We override it to use blind index for 'email'.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials) || (count($credentials) === 1 && array_key_exists('password', $credentials))) {
            return null;
        }

        $query = $this->newModelQuery();

        foreach ($credentials as $key => $value) {
            if (str_contains($key, 'password')) {
                continue;
            }

            if ($key === 'email') {
                // Use blind index for encrypted email lookup
                $query->whereBlind('email', 'email_index', $value);
            } else {
                // Standard query for non-encrypted fields
                $query->where($key, $value);
            }
        }

        return $query->first();
    }
}

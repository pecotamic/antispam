<?php

namespace Pecotamic\Antispam;

use Illuminate\Support\Facades\Crypt;

/**
 * An encrypted "this happened now" marker, and the reading of it.
 *
 * Both the timing cookie and the interaction proof are the same thing at heart:
 * a moment the server vouched for, handed to the client and presented back on
 * submit. Encryption keeps the client from inventing or backdating one.
 */
class SignedTimestamp
{
    public function issue(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    /**
     * Seconds elapsed since the marker was issued, or null when it is missing
     * or cannot be decrypted — which includes anything the client made up.
     */
    public function age(?string $token): ?int
    {
        if (!$token) {
            return null;
        }

        try {
            return now()->timestamp - (int) Crypt::decryptString($token);
        } catch (\Throwable) {
            return null;
        }
    }
}

<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AccountCredentials;
use Illuminate\Support\Facades\Log;

class AccountCredentialsSender
{
    public function send(User $user, string $password): bool
    {
        // The log transport would write the temporary password to application logs.
        if (config('mail.default') === 'log') {
            Log::warning('Account credentials email was not sent: mail transport is set to log.', ['user_id' => $user->id]);
            return false;
        }

        try {
            $user->notify(new AccountCredentials($user->username, $password));
            return true;
        } catch (\Throwable $exception) {
            Log::warning('Account credentials email could not be sent.', ['user_id' => $user->id, 'exception' => get_class($exception)]);
            return false;
        }
    }
}

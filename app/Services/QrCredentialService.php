<?php

namespace App\Services;

use App\Models\QrCredential;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QrCredentialService
{
    public function issueIfMissing(User $user, ?User $administrator = null): ?QrCredential
    {
        return DB::transaction(function () use ($user, $administrator): ?QrCredential {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->qrCredentials()->exists()) {
                return null;
            }

            return $this->issue($lockedUser, $administrator);
        });
    }

    public function issue(User $user, ?User $administrator = null): QrCredential
    {
        return DB::transaction(function () use ($user, $administrator) {
        $user = User::query()->lockForUpdate()->findOrFail($user->id);
        $this->assertEligible($user);
        if ($user->qrCredentials()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['qr_code' => 'An active QR code already exists.']);
        }

        do {
            $code = 'IQAMS-'.Str::random(43);
            $hash = hash('sha256', $code);
        } while (QrCredential::where('code_hash', $hash)->exists());

        $credential = QrCredential::create([
            'user_id' => $user->id,
            'code_hash' => $hash,
            'encrypted_code' => Crypt::encryptString($code),
            'status' => 'active',
            'issued_by' => $administrator?->id,
            'issued_at' => now(),
        ]);

        app(AuditLogger::class)->record('qr.issued', $user, [
            'status' => 'active',
            'issued_by' => $administrator?->id,
        ], $administrator);

        return $credential;
        }, 3);
    }

    public function activeFor(User $user): QrCredential
    {
        return DB::transaction(function () use ($user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $active = $user->qrCredentials()->where('status', 'active')->first();
            if ($active) return $active;
            if ($user->qrCredentials()->exists()) {
                throw ValidationException::withMessages(['qr_code' => 'Your QR code is revoked. Please contact the administrator.']);
            }
            return $this->issue($user);
        }, 3);
    }

    public function plainText(QrCredential $credential): string
    {
        return Crypt::decryptString($credential->encrypted_code);
    }

    public function regenerate(User $user, ?User $administrator = null): QrCredential
    {
        return DB::transaction(function () use ($user, $administrator) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->assertEligible($lockedUser);
            $this->retireLegacy($lockedUser, $administrator);

            $activeCredentials = QrCredential::query()
                ->where('user_id', $lockedUser->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            if ($activeCredentials->isNotEmpty()) {
                QrCredential::query()
                    ->whereKey($activeCredentials->modelKeys())
                    ->update([
                        'status' => 'revoked',
                        'revoked_by' => $administrator?->id,
                        'revoked_at' => now(),
                        'revocation_reason' => 'reissuance',
                    ]);

                app(AuditLogger::class)->record('qr.revoked', $lockedUser, [
                    'count' => $activeCredentials->count(),
                    'reason' => 'reissuance', 'previous_status' => 'active', 'updated_status' => 'revoked',
                ], $administrator);
            }

            $replacement = $this->issue($lockedUser, $administrator);

            app(AuditLogger::class)->record('qr.regenerated', $lockedUser, [
                'reason' => 'reissuance', 'previous_status' => $activeCredentials->isNotEmpty() ? 'active' : 'revoked', 'updated_status' => 'active',
            ], $administrator);

            return $replacement;
        });
    }

    public function revoke(User $user, User $administrator, string $reason): void
    {
        DB::transaction(function () use ($user, $administrator, $reason) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->assertEligible($user);
            $legacy = $this->retireLegacy($user, $administrator, $reason);
            $active = $user->qrCredentials()->where('status', 'active')->lockForUpdate()->get();
            if ($active->isEmpty() && ! $legacy) {
                throw ValidationException::withMessages(['qr_code' => 'There is no active QR code to revoke.']);
            }
            $user->qrCredentials()->where('status', 'active')->update([
                'status' => 'revoked', 'revoked_by' => $administrator->id,
                'revoked_at' => now(), 'revocation_reason' => $reason,
            ]);
            app(AuditLogger::class)->record('qr.revoked', $user, [
                'reason' => $reason, 'previous_status' => 'active', 'updated_status' => 'revoked',
            ], $administrator);
        }, 3);
    }

    private function retireLegacy(User $user, ?User $administrator, string $reason = 'reissuance'): bool
    {
        $retired = false;
        foreach (['student', 'instructor', 'nonTeachingStaff'] as $relation) {
            $profile = $user->{$relation}()->lockForUpdate()->first();
            if (! $profile || ! $profile->qr_code) continue;
            $code = $profile->qr_code;
            QrCredential::firstOrCreate(['code_hash' => hash('sha256', $code)], [
                'user_id' => $user->id, 'encrypted_code' => Crypt::encryptString($code),
                'status' => 'revoked', 'issued_at' => now(), 'revoked_at' => now(),
                'revoked_by' => $administrator?->id, 'revocation_reason' => $reason,
            ]);
            $profile->update(['qr_code' => null]);
            $retired = true;
        }
        return $retired;
    }

    private function assertEligible(User $user): void
    {
        if (! $user->isAccountActive() || ! $user->hasAnyRole(['student', 'instructor', 'staff'])) {
            throw ValidationException::withMessages([
                'qr_code' => 'Only active student, instructor, and staff accounts may receive QR credentials.',
            ]);
        }
    }
}

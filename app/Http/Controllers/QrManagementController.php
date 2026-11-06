<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\QrCredential;
use App\Services\QrCredentialService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QrManagementController extends Controller
{
    public function show(User $user)
    {
        $user->loadMissing(['roles', 'student', 'instructor', 'nonTeachingStaff']);
        $profile = match ($user->primaryRoleName()) {
            'student' => $user->student,
            'instructor' => $user->instructor,
            'staff' => $user->nonTeachingStaff,
            default => null,
        };
        abort_unless($profile, 404);
        $credential = $user->qrCredentials()->latest('id')->first();
        $audit = AuditLog::query()->where('subject_type', User::class)->where('subject_id', $user->id);
        $issuances = (clone $audit)->where('action', 'qr.issued')->count();
        $reissuances = (clone $audit)->where('action', 'qr.regenerated')->count();
        $history = QrCredential::query()
            ->leftJoin('users as administrators', 'administrators.id', '=', 'qr_credentials.revoked_by')
            ->where('qr_credentials.user_id', $user->id)
            ->whereNotNull('qr_credentials.revoked_at')
            ->orderByDesc('qr_credentials.revoked_at')->orderByDesc('qr_credentials.id')
            ->get(['qr_credentials.revoked_at', 'qr_credentials.revocation_reason', 'administrators.name as administrator_name'])
            ->map(fn ($entry) => [
                'date' => $entry->revoked_at?->toISOString(),
                'reason' => $entry->revocation_reason,
                'administrator' => $entry->administrator_name,
            ]);
        return response()->json([
            'name' => $profile->fullName(),
            'identifier' => $profile->student_no ?? $profile->employee_no ?? '',
            'status' => $credential?->status ?? ($profile->qr_code ? 'active' : 'unissued'),
            'issued_at' => $credential?->issued_at?->toISOString(),
            'version' => $issuances ?: null,
            'total_reissuances' => $reissuances,
            'revocation_history' => $history,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function revoke(Request $request, User $user, QrCredentialService $service)
    {
        $data = $request->validate(['reason' => ['required', Rule::in(['Lost ID', 'Stolen ID', 'Damaged ID', 'Suspected Compromise', 'Other'])]]);
        $service->revoke($user, $request->user(), $data['reason']);
        return response()->json(['message' => 'QR code revoked successfully.']);
    }

    public function reissue(Request $request, User $user, QrCredentialService $service)
    {
        $service->regenerate($user, $request->user());
        return response()->json(['message' => 'QR code reissued successfully.']);
    }
}

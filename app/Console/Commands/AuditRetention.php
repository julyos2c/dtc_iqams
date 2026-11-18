<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AuditRetention extends Command
{
    protected $signature = 'audit:retention {--dry-run : Count records older than the configured period without changing them}';

    protected $description = 'Report audit retention candidates; disposal is blocked pending approved hold controls';

    public function handle(): int
    {
        if (! $this->option('dry-run')) {
            $this->error('Audit disposal is unavailable: approved legal-hold controls and maintenance authorization are required.');

            return self::FAILURE;
        }

        $days = config('audit_retention.days');
        if ((! is_int($days) && (! is_string($days) || ! ctype_digit($days))) || (int) $days < 1 || (int) $days > 36500) {
            $this->error('Set an approved AUDIT_RETENTION_DAYS value between 1 and 36500 before estimating.');

            return self::FAILURE;
        }

        // Match AuditLogger's application-time timestamps; deployment must verify DB timezone.
        $cutoff = Carbon::now()->subDays((int) $days);
        $count = AuditLog::query()->where('created_at', '<', $cutoff)->count();
        $this->line('Age-based candidates: '.$count);
        $this->line('Cutoff (application timezone, exclusive): '.$cutoff->toDateTimeString());
        $this->warn('Not a disposal eligibility count: investigation and legal holds have not been evaluated. No records were changed.');

        return self::SUCCESS;
    }
}

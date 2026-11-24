<?php

namespace App\Services;

class ClassCancellationReportFilter
{
    public static function exclude($query, string $alias = 'attendance_logs')
    {
        ScannerCoverage::filterStored($query,$alias);
        return $query->whereNotExists(function ($q) use ($alias) {
            $q->selectRaw('1')->from('class_cancellation_requests as cancellation')
                ->where('cancellation.status', 'approved')
                ->whereColumn('cancellation.schedule_id', "$alias.schedule_id")
                ->whereColumn('cancellation.opens_at', '<=', "$alias.scan_time")
                ->whereColumn('cancellation.ends_at', '>=', "$alias.scan_time");
        });
    }
}

<?php

use App\Notifications\EnrollmentApprovedNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $notifications = DB::table('notifications')
                ->where('type', EnrollmentApprovedNotification::class)
                ->orderBy('created_at')->orderBy('id')->get();
            $groups = [];
            foreach ($notifications as $notification) {
                $data = json_decode($notification->data, true) ?: [];
                $groupId = $data['group_id'] ?? null;
                if (! $groupId && ! empty($data['enrollment_request_id'])) {
                    $groupId = DB::table('enrollment_requests')->where('id', $data['enrollment_request_id'])->value('recurring_schedule_group_id');
                }
                if (! $groupId) continue;
                $key = $notification->notifiable_type.'|'.$notification->notifiable_id.'|'.$groupId;
                $groups[$key][] = [$notification, $data, $groupId];
            }

            foreach ($groups as $items) {
                [$first, $data, $groupId] = $items[0];
                $data['group_id'] = $groupId;
                $data['approved_count'] = array_sum(array_map(fn ($item) => $item[1]['approved_count'] ?? 1, $items));
                unset($data['batch_id'], $data['student_name'], $data['enrollment_request_id']);
                $unread = collect($items)->contains(fn ($item) => $item[0]->read_at === null);
                DB::table('notifications')->where('id', $first->id)->update([
                    'data' => json_encode($data),
                    'read_at' => $unread ? null : $first->read_at,
                    'updated_at' => now(),
                ]);
                $duplicates = array_map(fn ($item) => $item[0]->id, array_slice($items, 1));
                if ($duplicates) DB::table('notifications')->whereIn('id', $duplicates)->delete();
            }
        });
    }

    public function down(): void {}
};

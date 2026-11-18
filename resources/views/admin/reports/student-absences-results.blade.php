<div data-report-results>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 text-xs font-semibold uppercase text-gray-500">
                <tr><th class="py-3 pr-3">Student</th><th class="px-2 py-3">Absences</th><th class="px-2 py-3">Status</th><th class="py-3 pl-2 text-right">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($students as $student)
                    <tr>
                        <td class="py-3 pr-3">
                            <div class="flex items-center gap-2.5">
                                <img src="{{ $student->avatar_path ? asset('storage/'.$student->avatar_path) : asset('images/default-avatar.svg') }}" alt="" class="hidden h-9 w-9 shrink-0 rounded-full object-cover sm:block">
                                <div class="min-w-0"><span class="block font-semibold text-gray-900">{{ implode(' ', array_filter([$student->first_name, $student->middle_name, $student->last_name, $student->name_suffix])) }}</span><span class="block text-xs text-gray-500">{{ $student->student_no }} · {{ $student->course_code ?? '—' }} / {{ $student->section_name ?? '—' }}</span></div>
                            </div>
                        </td>
                        <td class="px-2 py-3 font-semibold text-gray-900">{{ $student->total_absences }}</td>
                        <td class="px-2 py-3"><span @class(['text-xs font-semibold', 'text-red-700' => $student->max_subject_absences >= \App\Services\StudentAbsenceWarningService::THRESHOLD, 'text-gray-600' => $student->max_subject_absences < \App\Services\StudentAbsenceWarningService::THRESHOLD])>{{ $student->max_subject_absences >= \App\Services\StudentAbsenceWarningService::THRESHOLD ? 'At Risk' : 'Normal' }}</span></td>
                        <td class="py-3 pl-2 text-right"><button type="button" @click="show({{ $student->id }})" class="font-semibold text-indigo-700 hover:text-indigo-900">View<span class="sr-only"> details for {{ $student->first_name }} {{ $student->last_name }}</span></button></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-gray-500">No student absences match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div data-pages class="border-t border-gray-100 pt-4">{{ $students->links() }}</div>
</div>

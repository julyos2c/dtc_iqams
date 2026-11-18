<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Manage Students</h1>
            <p class="mt-1 text-sm text-gray-500">Maintain student profiles, QR credentials, and instructor submissions.</p>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
        availableSections: @js($sections->map(fn ($section) => ['id' => (string) $section->id, 'course_id' => (string) $section->course_id, 'name' => $section->section_name])->values()),
        editModal: { show: false, id: null, course_id: '', section_id: '', enrollment_type: 'regular', enrollment_group_ids: [], student_no: '', email: '', first_name: '', last_name: '', middle_name: '', name_suffix: '', status: '', avatar_url: '' },
        deleteModal: { show: false, id: null, name: '' },
        statusModal: { show: false, userId: null, name: '', status: '' },
        selectedIds: [],
        allIds: @js($studentUserIds),
        toggleAll() { this.selectedIds = this.selectedIds.length === this.allIds.length ? [] : [...this.allIds]; }
    }" @user-management-results.window="allIds = $event.detail; selectedIds = []">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">


            @include('students.partials.navigation', ['activeTab' => 'students'])

            <div data-repeatable-list class="bg-white shadow-sm rounded-lg">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4">
                    <span class="text-sm text-gray-500" data-user-search-count>{{ $students->total() }} total</span>
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
                        <x-user-management-search />
                        <button type="button"
                            @click="window.ensureIqamsQrCode().then(() => window.printIqamsIdCards(selectedIds.map(id => '{{ url('admin/id-cards') }}/' + id))).catch(error => window.alert(error.message))"
                            :disabled="selectedIds.length === 0"
                            class="rounded border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">Print
                            selected ID cards</button></div>
                </div>


                <div class="overflow-x-auto" data-user-search-table data-user-ids="{{ $studentUserIds->toJson() }}">
                    <table class="w-full table-fixed text-left text-sm [&_th]:px-2 [&_th]:py-3 [&_th]:align-middle [&_th]:text-xs [&_th]:font-semibold [&_th]:tracking-wide [&_td]:px-2 [&_td]:py-3 [&_td]:align-top sm:[&_th]:px-3 sm:[&_td]:px-3">
                        <colgroup>
                            <col class="w-[52%] md:w-[34%]">
                            <col class="hidden md:table-column md:w-[17%]">
                            <col class="hidden md:table-column md:w-[19%]">
                            <col class="w-[30%] md:w-[20%]">
                            <col class="w-[18%] md:w-[10%]">
                        </colgroup>
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th><div class="flex items-center gap-2"><input type="checkbox" aria-label="Select all students on this page" :checked="selectedIds.length === allIds.length && allIds.length > 0" @change="toggleAll()" class="mr-2 rounded border-gray-300 text-indigo-600"><span>Student</span></div></th>
                                <th class="hidden md:table-cell">Student No.</th>
                                <th class="hidden md:table-cell">Academic Info</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($students as $student)
                                <tr class="transition-colors hover:bg-gray-50/80">
                                    <td><x-user-management-identity :user="$student->user" :name="$student->fullName()" :identifier="$student->student_no" :detail="($student->course->course_code ?? '—').' · '.($student->section->section_name ?? '—')" :detail-title="($student->course->course_code ?? '—').' · '.($student->section->section_name ?? '—')" /></td>
                                    <td class="hidden break-words font-medium text-gray-800 !align-top md:table-cell">{{ $student->student_no }}</td>
                                    <td class="hidden break-words text-gray-600 !align-top md:table-cell" title="{{ ($student->course->course_code ?? '—').' · '.($student->section->section_name ?? '—') }}">{{ ($student->course->course_code ?? '—').' · '.($student->section->section_name ?? '—') }}</td>
                                    <td>
                                        <x-student-status :status="$student->user->status" />
                                        <span class="mt-1 block text-xs font-medium text-gray-600">{{ ucfirst($student->enrollment_type) }}</span>
                                        @if ($student->user->must_change_password)
                                            <span class="mt-1 block text-xs font-medium text-amber-700">Password setup required</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <x-action-menu :delete-action="route('students.destroy', $student)" :toggle-action="route('users.status.update', $student->user)" :next-status="$student->user->isAccountActive() ? 'inactive' : 'active'"
                                            :is-active="$student->user->isAccountActive()" :requires-password-confirmation="true" :delete-name="$student->fullName()">
                                            <x-slot:qr><button type="button" @click="$dispatch('manage-qr', {{ Illuminate\Support\Js::from(['id' => $student->user_id, 'role' => 'Student', 'avatar' => $student->user->avatar_thumbnail_url ?? asset('images/default-avatar.svg'), 'detail_label' => 'Academic Info', 'detail' => ($student->course->course_code ?? 'Not assigned').' / '.($student->section->section_name ?? 'Not assigned')]) }})">Manage QR Code</button></x-slot:qr>
                                            <x-slot:edit><button type="button"
                                                    @click="editModal = {{ Illuminate\Support\Js::from(['show' => true, 'id' => $student->id, 'course_id' => (string) $student->course_id, 'section_id' => (string) $student->section_id, 'enrollment_type' => $student->enrollment_type, 'enrollment_group_ids' => $student->scheduleEnrollments->pluck('recurring_schedule_group_id')->values(), 'student_no' => $student->student_no, 'email' => $student->user->email ?? '', 'first_name' => $student->first_name, 'last_name' => $student->last_name, 'middle_name' => $student->middle_name, 'name_suffix' => $student->name_suffix ?? '', 'status' => $student->status, 'avatar_url' => $student->user->avatar_thumbnail_url ?? asset('images/default-avatar.svg')]) }}">Edit</button></x-slot:edit>
                                        </x-action-menu>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-400">
                                        {{ trim((string) request()->query('search', '')) !== '' ? 'No users found matching your search' : 'No approved student registrations yet.' }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-6 py-4" data-user-search-pagination>
                    {{ $students->links() }}
                </div>
            </div>
        </div>

        {{-- Edit Student Modal --}}
        <div x-show="editModal.show" x-cloak
            class="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto px-4 py-4 sm:px-6"
            style="background: rgba(0,0,0,0.4);">
            <div @click.outside="editModal.show = false"
                class="my-auto w-full max-w-4xl rounded-lg bg-white p-5 shadow-xl sm:p-8">

                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Student</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <form method="POST" :action="'{{ url('students') }}/' + editModal.id" enctype="multipart/form-data"
                    data-password-confirmation-required>
                    @csrf
                    @method('PUT')
                    <div class="mb-5 flex items-center gap-4"><img :src="editModal.avatar_url"
                            alt="Current profile photo" class="h-14 w-14 rounded-full object-cover">
                        <div><label class="mb-1 block text-sm font-medium text-gray-700">Replace Profile
                                Photo</label><input type="file" name="avatar" accept="image/jpeg,image/png"
                                class="block w-full text-sm text-gray-600"></div>
                    </div>

                    <div class="mb-5 grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2 md:grid-cols-3">
                        <div class="min-w-0">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                            <select name="course_id" x-model="editModal.course_id" @change="editModal.section_id = ''"
                                class="w-full min-w-0 truncate rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- Select Course --</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}">{{ $course->course_code }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="min-w-0">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Section</label>
                            <select name="section_id" x-model="editModal.section_id" :disabled="!editModal.course_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- No Section --</option>
                                <template x-for="section in availableSections.filter(section => section.course_id === editModal.course_id)" :key="section.id">
                                    <option :value="section.id" x-text="section.name"></option>
                                </template>
                            </select>
                        </div>

                        <div class="min-w-0">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Enrollment type</label>
                            <select name="enrollment_type" x-model="editModal.enrollment_type"
                                class="w-full min-w-0 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="regular">Regular</option>
                                <option value="irregular">Irregular</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500" x-text="editModal.enrollment_type === 'irregular' ? 'Selected class offerings' : 'Home section'"></p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Student No.</label>
                            <input type="text" x-model="editModal.student_no" disabled
                                class="w-full rounded-md border-gray-200 bg-gray-50 text-gray-500 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" x-model="editModal.first_name"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                            <input type="text" name="middle_name" x-model="editModal.middle_name"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" x-model="editModal.last_name"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Suffix <span class="font-normal text-gray-400">(optional)</span></label>
                            <input type="text" name="name_suffix" x-model="editModal.name_suffix" maxlength="100" placeholder="e.g. Jr., III"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" x-model="editModal.email" disabled
                                class="w-full rounded-md border-gray-200 bg-gray-50 text-gray-500 shadow-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" x-model="editModal.status"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="graduated">Graduated</option>
                                <option value="dropped">Dropped</option>
                            </select>
                        </div>
                    </div>

                    @if(app(\App\Services\EnrollmentWorkflow::class)->enabled())
                        <div class="mb-3 rounded border bg-slate-50 p-3 text-sm">
                            <p class="font-semibold">Approved class offerings</p>
                            @foreach($offerings as $offering)
                                <p x-show="editModal.enrollment_group_ids.includes('{{ $offering['id'] }}')" x-cloak class="mt-1 text-slate-600">{{ $offering['label'] }}</p>
                            @endforeach
                            <p x-show="editModal.enrollment_group_ids.length === 0" class="text-slate-500">No approved offerings.</p>
                            <p class="mt-2 text-xs text-slate-500">Enrollments are maintained through instructor requests and admin approval.</p>
                        </div>
                    @else
                    <div x-show="editModal.enrollment_type === 'irregular'" x-cloak
                        class="mb-2 rounded-md border border-amber-200 bg-amber-50 p-3">
                        <p class="mb-2 text-sm font-medium text-amber-900">Class offerings</p>
                        <div class="max-h-40 space-y-2 overflow-y-auto">
                            @foreach ($offerings as $offering)
                                <label class="flex gap-2 text-sm text-gray-700"><input type="checkbox"
                                        name="enrollment_group_ids[]" value="{{ $offering['id'] }}"
                                        :checked="editModal.enrollment_group_ids.includes('{{ $offering['id'] }}')"
                                        @change="editModal.enrollment_group_ids = $event.target.checked ? [...editModal.enrollment_group_ids, '{{ $offering['id'] }}'] : editModal.enrollment_group_ids.filter(id => id !== '{{ $offering['id'] }}')"><span>{{ $offering['label'] }}</span></label>
                            @endforeach
                        </div>
                    </div>

                    @endif
                    <p class="text-xs text-gray-400 mb-1">Email and login credentials can't be changed here yet.</p>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-2">
                        <button type="button" @click="editModal.show = false"
                            class="rounded px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Update Student
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Delete Confirmation Modal --}}
        <div x-show="deleteModal.show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4"
            style="background: rgba(0,0,0,0.4);">
            <div @click.outside="deleteModal.show = false" class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">

                <h3 class="text-lg font-semibold text-gray-800 mb-2">Deactivate Student</h3>
                <p class="text-sm text-gray-500 mb-6">
                    Are you sure you want to deactivate <span class="font-medium text-gray-700"
                        x-text="deleteModal.name"></span>?
                    Their login will be disabled and attendance history will be retained.
                </p>

                <form method="POST" :action="'{{ url('students') }}/' + deleteModal.id"
                    data-password-confirmation-required>
                    @csrf
                    @method('DELETE')

                    <div class="flex items-center justify-end gap-3">
                        <button type="button" @click="deleteModal.show = false"
                            class="text-sm text-gray-500 hover:text-gray-700">
                            Cancel
                        </button>
                        <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 rounded">
                            Deactivate / Archive
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <x-account-status-modal />
    </div>
    <x-qr-management-modal />
</x-app-layout>

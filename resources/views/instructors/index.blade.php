<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Manage Instructors</h1>
            <p class="mt-1 text-sm text-gray-500">Add, update, and manage instructor accounts.</p>
        </div>
    </x-slot>

    <div class="py-8" x-data="{
        showCreateModal: {{ ($errors->any() || session('keep_create_modal_open')) ? 'true' : 'false' }},
        editModal: { show: false, id: null, employee_no: '', email: '', department_id: '', name_prefix: '', first_name: '', middle_name: '', last_name: '', professional_credentials: '', avatar_url: '' },
        deleteModal: { show: false, id: null, name: '' },
        statusModal: { show: false, userId: null, name: '', status: '' },
        selectedIds: [],
        allIds: @js($instructorUserIds),
        toggleAll() { this.selectedIds = this.selectedIds.length === this.allIds.length ? [] : [...this.allIds]; }
    }" @user-management-results.window="allIds = $event.detail; selectedIds = []"
        @keydown.escape.window="showCreateModal = false; editModal.show = false; deleteModal.show = false; statusModal.show = false">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div data-repeatable-list class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4">
                    <span class="text-sm text-gray-500" data-user-search-count>{{ $instructors->total() }} total</span>
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
                        <x-user-management-search />
                        <button type="button"
                            @click="window.ensureIqamsQrCode().then(() => window.printIqamsIdCards(selectedIds.map(id => '{{ url('admin/id-cards') }}/' + id))).catch(error => window.alert(error.message))"
                            :disabled="selectedIds.length === 0"
                            class="whitespace-nowrap rounded border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">Print
                            selected ID cards</button><button type="button" @click="showCreateModal = true"
                            class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            + Add Instructor
                        </button></div>
                </div>


                <div class="overflow-x-auto" data-user-search-table data-user-ids="{{ $instructorUserIds->toJson() }}">
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
                                <th><div class="flex items-center gap-2"><input type="checkbox" aria-label="Select all instructors on this page" :checked="selectedIds.length === allIds.length && allIds.length > 0" @change="toggleAll()" class="mr-2 rounded border-gray-300 text-indigo-600"><span>Instructor</span></div></th>
                                <th class="hidden md:table-cell">Instructor ID</th>
                                <th class="hidden md:table-cell">Department</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($instructors as $instructor)
                                <tr class="transition-colors hover:bg-gray-50/80">
                                    <td><x-user-management-identity :user="$instructor->user" :name="$instructor->fullName()" :identifier="$instructor->employee_no" :detail="$instructor->department->department_code ?? $instructor->department->department_name ?? '—'" :detail-title="$instructor->department->department_name ?? '—'" /></td>
                                    <td class="hidden break-words font-medium text-gray-800 !align-top md:table-cell">{{ $instructor->employee_no }}</td>
                                    <td class="hidden break-words text-gray-600 !align-top md:table-cell" title="{{ $instructor->department->department_name ?? '—' }}">{{ $instructor->department->department_code ?? $instructor->department->department_name ?? '—' }}</td>
                                    <td>
                                        <x-student-status :status="$instructor->user->status" />
                                        @if ($instructor->user->must_change_password)
                                            <span class="mt-1 block text-xs font-medium text-amber-700">Password setup required</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <x-action-menu :delete-action="route('instructors.destroy', $instructor)" :toggle-action="route('users.status.update', $instructor->user)" :next-status="$instructor->user->isAccountActive() ? 'inactive' : 'active'"
                                            :is-active="$instructor->user->isAccountActive()" :requires-password-confirmation="true" :delete-name="$instructor->fullName()">
                                            <x-slot:qr><button type="button" @click="$dispatch('manage-qr', {{ Illuminate\Support\Js::from(['id' => $instructor->user_id, 'role' => 'Instructor', 'avatar' => $instructor->user->avatar_thumbnail_url ?? asset('images/default-avatar.svg'), 'detail_label' => 'Department', 'detail' => $instructor->department->department_code ?? $instructor->department->department_name ?? 'Not assigned']) }})">Manage QR Code</button></x-slot:qr>
                                            <x-slot:edit><button type="button"
                                                    @click="editModal = {{ Illuminate\Support\Js::from(['show' => true, 'id' => $instructor->id, 'employee_no' => $instructor->employee_no, 'email' => $instructor->user->email ?? '', 'department_id' => (string) $instructor->department_id, 'name_prefix' => $instructor->name_prefix ?? '', 'first_name' => $instructor->first_name, 'middle_name' => $instructor->middle_name ?? '', 'last_name' => $instructor->last_name, 'professional_credentials' => $instructor->professional_credentials ?? '', 'avatar_url' => $instructor->user->avatar_thumbnail_url ?? asset('images/default-avatar.svg')]) }}">Edit</button></x-slot:edit>
                                        </x-action-menu>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-400">
                                        {{ trim((string) request()->query('search', '')) !== '' ? 'No users found matching your search' : 'No instructors yet. Add your first one.' }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-6 py-4" data-user-search-pagination>
                    {{ $instructors->links() }}
                </div>
            </div>
        </div>

        {{-- Create Instructor Modal --}}
        <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-[70] overflow-y-auto px-4 py-4 sm:px-6"
            style="background: rgba(0, 0, 0, 0.4)">
            <div class="flex min-h-full items-center justify-center">
                <div @click.outside="showCreateModal = false"
                    class="w-full max-w-2xl rounded-lg bg-white p-4 shadow-xl sm:p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Add Instructor</h3>
                        <button type="button" @click="showCreateModal = false"
                            class="text-gray-400 hover:text-gray-600">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    <form method="POST" action="{{ route('instructors.store') }}" enctype="multipart/form-data" data-repeatable-create>
                        @csrf
                        <div class="grid grid-cols-1 gap-x-5 gap-y-3 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Profile Photo</label>
                                <input type="file" name="avatar" accept="image/jpeg,image/png"
                                    class="block w-full text-sm text-gray-600">
                                @error('avatar')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="employee_no" class="mb-1 block text-sm font-medium text-gray-700">Instructor
                                    ID</label>
                                <input id="employee_no" type="text" name="employee_no"
                                    value="{{ old('employee_no') }}" placeholder="e.g. INS2026001"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="mt-1 text-xs leading-4 text-gray-400">This will also become the instructor's
                                    login username.</p>
                                @error('employee_no')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="department_id"
                                    class="mb-1 block text-sm font-medium text-gray-700">Department</label>
                                <select id="department_id" name="department_id"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">-- Select Department --</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>
                                            {{ $department->department_code }} - {{ $department->department_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('department_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="name_prefix" class="mb-1 block text-sm font-medium text-gray-700">Title /
                                    Prefix</label>
                                <input id="name_prefix" type="text" name="name_prefix"
                                    value="{{ old('name_prefix') }}" placeholder="e.g. Engr."
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('name_prefix')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="first_name" class="mb-1 block text-sm font-medium text-gray-700">First
                                    Name</label>
                                <input id="first_name" type="text" name="first_name"
                                    value="{{ old('first_name') }}"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('first_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="middle_name" class="mb-1 block text-sm font-medium text-gray-700">Middle
                                    Name <span class="font-normal text-gray-400">(optional)</span></label>
                                <input id="middle_name" type="text" name="middle_name"
                                    value="{{ old('middle_name') }}"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('middle_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="last_name" class="mb-1 block text-sm font-medium text-gray-700">Last
                                    Name</label>
                                <input id="last_name" type="text" name="last_name"
                                    value="{{ old('last_name') }}"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('last_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="professional_credentials"
                                    class="mb-1 block text-sm font-medium text-gray-700">Professional
                                    Credentials</label>
                                <input id="professional_credentials" type="text" name="professional_credentials"
                                    value="{{ old('professional_credentials') }}" placeholder="e.g. LPT, MATVE"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="mt-1 text-xs leading-4 text-gray-400">Displayed after the name using
                                    conventional formatting.</p>
                                @error('professional_credentials')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email"
                                    class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                                <input id="email" type="email" name="email" value="{{ old('email') }}"
                                    placeholder="instructor@email.com"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-end gap-3 border-t border-gray-100 pt-3">
                            <button type="button" @click="showCreateModal = false"
                                class="rounded px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700">Cancel</button>
                            <button type="submit"
                                class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save
                                Instructor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Edit Instructor Modal --}}
        <div x-show="editModal.show" x-cloak
            class="fixed inset-0 z-[70] flex items-center justify-center overflow-y-auto px-4 py-6"
            style="background: rgba(0, 0, 0, 0.4)">
            <div @click.outside="editModal.show = false"
                class="w-full max-w-2xl rounded-lg bg-white p-4 shadow-xl sm:p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">Edit Instructor</h3>
                    <button type="button" @click="editModal.show = false" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <form method="POST" :action="'{{ url('instructors') }}/' + editModal.id"
                    enctype="multipart/form-data" data-password-confirmation-required>
                    @csrf
                    @method('PUT')
                    <div class="mb-4 flex items-center gap-4"><img :src="editModal.avatar_url"
                            alt="Current profile photo" class="h-14 w-14 rounded-full object-cover">
                        <div><label class="mb-1 block text-sm font-medium text-gray-700">Replace Profile
                                Photo</label><input type="file" name="avatar" accept="image/jpeg,image/png"
                                class="block w-full text-sm text-gray-600"></div>
                    </div>

                    <div class="grid grid-cols-1 gap-x-5 gap-y-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Instructor ID</label>
                            <input type="text" x-model="editModal.employee_no" disabled
                                class="w-full rounded-md border-gray-200 bg-gray-50 text-gray-500 shadow-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Department</label>
                            <select name="department_id" x-model="editModal.department_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">-- Select Department --</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->department_code }} -
                                        {{ $department->department_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Title / Prefix</label>
                            <input type="text" name="name_prefix" x-model="editModal.name_prefix"
                                placeholder="e.g. Engr."
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">First Name</label>
                            <input type="text" name="first_name" x-model="editModal.first_name"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Middle Name</label>
                            <input type="text" name="middle_name" x-model="editModal.middle_name"
                                placeholder="Optional"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Last Name</label>
                            <input type="text" name="last_name" x-model="editModal.last_name"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Professional
                                Credentials</label>
                            <input type="text" name="professional_credentials"
                                x-model="editModal.professional_credentials" placeholder="e.g. LPT, MATVE"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" x-model="editModal.email" disabled
                                class="w-full rounded-md border-gray-200 bg-gray-50 text-gray-500 shadow-sm">
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-end gap-3 border-t border-gray-100 pt-3">
                        <button type="button" @click="editModal.show = false"
                            class="rounded px-4 py-2 text-sm font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-700">Cancel</button>
                        <button type="submit"
                            class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Update
                            Instructor</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Delete Confirmation Modal --}}
        <div x-show="deleteModal.show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4"
            style="background: rgba(0, 0, 0, 0.4)">
            <div @click.outside="deleteModal.show = false" class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-2 text-lg font-semibold text-gray-800">Deactivate Instructor</h3>
                <p class="mb-6 text-sm text-gray-500">
                    Are you sure you want to deactivate <span class="font-medium text-gray-700"
                        x-text="deleteModal.name"></span>?
                    Their login will be disabled and attendance history will be retained.
                </p>
                <form method="POST" :action="'{{ url('instructors') }}/' + deleteModal.id"
                    data-password-confirmation-required>
                    @csrf
                    @method('DELETE')
                    <div class="flex items-center gap-3">
                        <button type="submit"
                            class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Deactivate
                            / Archive</button>
                        <button type="button" @click="deleteModal.show = false"
                            class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <x-account-status-modal />
    </div>
    <x-qr-management-modal />
</x-app-layout>

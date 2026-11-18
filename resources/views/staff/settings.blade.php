<x-staff-layout title="Account Settings">
    <div class="mx-auto max-w-[1500px] space-y-5">
        <div><h2 class="text-lg font-bold text-[#10294b]">Account settings</h2><p class="mt-1 text-sm text-slate-500">Update your account information and password.</p></div>
        <form method="POST" action="{{ route('staff.settings.update') }}" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @csrf @method('PATCH')
            <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-2">
                <section>
                    <h3 class="text-base font-semibold text-gray-900">Personal information</h3>
                    <p class="mt-1 text-sm text-gray-500">Update the name and email attached to your account.</p>

                    <div class="mt-6 space-y-5">
                        <div>
                            <x-input-label for="name" value="Name" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                :value="old('name', $user->name)" required autocomplete="name" />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                :value="old('email', $user->email)" required autocomplete="email" />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>

                        @if ('staff' === 'student')
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <x-input-label value="Student No." />
                                    <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                        {{ $user->student?->student_no ?? '—' }}</p>
                                </div>
                                <div>
                                    <x-input-label value="Course" />
                                    <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                        {{ $user->student?->course?->course_code ?? '—' }}</p>
                                </div>
                                <div>
                                    <x-input-label value="Section" />
                                    <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                        {{ $user->student?->section?->section_name ?? '—' }}</p>
                                </div>
                            </div>
                        @elseif ('staff' === 'instructor')
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Employee No." />
                                    <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                        {{ $user->instructor?->employee_no ?? '—' }}</p>
                                </div>
                                <div>
                                    <x-input-label value="Department" />
                                    <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                        {{ $user->instructor?->department?->department_name ?? '—' }}</p>
                                </div>
                            </div>
                        @elseif ('staff' === 'staff')
                            <div>
                                <x-input-label value="Employee No." />
                                <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                    {{ $user->nonTeachingStaff?->employee_no ?? '—' }}</p>
                            </div>
                        @endif
                    </div>
                </section>

                <section>
                    <h3 class="text-base font-semibold text-gray-900">Change password</h3>
                    <p class="mt-1 text-sm text-gray-500">Leave these fields blank to keep your current password.</p>

                    <div class="mt-6 space-y-5">
                        <div>
                            <x-input-label for="current_password" value="Current password" />
                            <x-text-input id="current_password" name="current_password" type="password"
                                class="mt-1 block w-full" autocomplete="current-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('current_password')" />
                        </div>

                        <div>
                            <x-input-label for="password" value="New password" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full"
                                autocomplete="new-password" />
                            <x-input-error class="mt-2" :messages="$errors->get('password')" />
                        </div>

                        <div>
                            <x-input-label for="password_confirmation" value="Confirm new password" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                                class="mt-1 block w-full" autocomplete="new-password" />
                        </div>
                    </div>
                </section>
            </div>

            <div class="flex justify-end border-t border-gray-100 bg-gray-50 px-6 py-4 sm:px-8">
                <x-primary-button>Save changes</x-primary-button>
            </div>
        </form>
    </div>
</x-staff-layout>

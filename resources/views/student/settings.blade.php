<x-student-layout title="Account Settings">
    <div class="mb-6">
        <h2 class="font-sans text-2xl font-semibold text-slate-900">Account settings</h2>
        <p class="mt-1 text-sm text-slate-500">Manage your password.</p>
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="border border-slate-200 bg-white p-6">
            <h3 class="font-sans font-semibold">Change password</h3>
            <p class="mt-1 text-sm text-slate-500">Confirm your current password before choosing a new one.</p>
            <form method="POST" action="{{ route('student.password') }}" class="mt-6 space-y-4">@csrf @method('PUT')
                <div><label for="current_password" class="text-sm font-medium">Current password</label><input
                        id="current_password" name="current_password" type="password" autocomplete="current-password"
                        required class="mt-1 w-full rounded-xl border-slate-300">
                    @error('current_password', 'passwordUpdate')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div><label for="password" class="text-sm font-medium">New password</label><input id="password"
                        name="password" type="password" autocomplete="new-password" required
                        class="mt-1 w-full rounded-xl border-slate-300">
                    @error('password', 'passwordUpdate')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div><label for="password_confirmation" class="text-sm font-medium">Confirm new password</label><input
                        id="password_confirmation" name="password_confirmation" type="password"
                        autocomplete="new-password" required class="mt-1 w-full rounded-xl border-slate-300"></div>
                <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white">Change
                    password</button>
            </form>
        </section>
    </div>
</x-student-layout>

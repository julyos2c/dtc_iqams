<x-guest-layout>
    <div class="rounded-[22px] border border-slate-200 bg-white p-6 shadow-[0_18px_50px_rgba(15,23,42,0.08)] sm:p-8">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Change your password</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">A new password is required before you can continue to IQAMS.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.force-change.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <x-password-input id="current_password" name="current_password" label="Current password"
                :error-messages="$errors->forcePasswordChange->get('current_password')"
                autocomplete="current-password" required autofocus
                placeholder="Enter your current password"
                class="rounded-xl border-slate-200 py-3 text-sm shadow-sm placeholder:text-slate-400 focus:border-slate-400 focus:ring-slate-300" />
        </div>

        <div>
            <x-password-input id="password" name="password" label="New password"
                :error-messages="$errors->forcePasswordChange->get('password')"
                autocomplete="new-password" required
                placeholder="Enter your new password"
                class="rounded-xl border-slate-200 py-3 text-sm shadow-sm placeholder:text-slate-400 focus:border-slate-400 focus:ring-slate-300" />
        </div>

        <div>
            <x-password-input id="password_confirmation" name="password_confirmation" label="Confirm new password"
                :error-messages="$errors->forcePasswordChange->get('password_confirmation')"
                autocomplete="new-password" required 
                placeholder="Confirm your new password"
                class="rounded-xl border-slate-200 py-3 text-sm shadow-sm placeholder:text-slate-400 focus:border-slate-400 focus:ring-slate-300" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <a class="text-sm text-teal-700 underline hover:text-teal-900" href="{{ route('password.request') }}">Forgot
                current password?</a>
            <x-primary-button>Update password</x-primary-button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-right">
        @csrf
        <button type="submit" class="text-sm text-slate-500 underline hover:text-slate-700">Logout</button>
    </form>
    </div>
</x-guest-layout>

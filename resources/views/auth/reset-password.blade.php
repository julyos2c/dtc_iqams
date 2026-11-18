<x-guest-layout>
    <div class="rounded-[22px] border border-slate-200 bg-white p-6 shadow-[0_18px_50px_rgba(15,23,42,0.08)] sm:p-8">
        <div class="mb-3">
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ __('Create a new password') }}</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('This link can be used once.') }}</p>
        </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label class="mb-1.5 text-sm font-semibold text-slate-700" for="email" :value="__('Email')" />
            <x-text-input id="email" class="block w-full rounded-xl border-slate-200 px-4 py-2.5 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" type="email" name="email" :value="old('email', $request->email)" required
                autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label class="mb-1.5 text-sm font-semibold text-slate-700" for="password" :value="__('Password')" />
            <x-password-input id="password" name="password" :error-messages="$errors->get('password')" required
                autocomplete="new-password" class="rounded-xl border-slate-200 px-4 py-2.5 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" />
        </div>

        <div>
            <x-input-label class="mb-1.5 text-sm font-semibold text-slate-700" for="password_confirmation" :value="__('Confirm Password')" />

            <x-password-input id="password_confirmation" name="password_confirmation" :error-messages="$errors->get('password_confirmation')" required
                autocomplete="new-password" class="rounded-xl border-slate-200 px-4 py-2.5 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" />
        </div>

        <a class="block text-right text-sm font-semibold text-teal-700 transition hover:text-teal-900" href="{{ route('password.request') }}">{{ __('Link expired?') }}</a>

        <div>
            <x-primary-button class="flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm normal-case tracking-normal shadow-lg shadow-slate-900/20 transition hover:bg-slate-800 focus:bg-slate-800">
                <svg aria-hidden="true" class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 0 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                {{ __('Set Password') }}
            </x-primary-button>
        </div>
    </form>
    </div>
</x-guest-layout>

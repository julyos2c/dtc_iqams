<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <div x-data="{ show: false }">
            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
            <div class="relative mt-1">
                <x-text-input id="update_password_current_password" name="current_password" type="password" x-bind:type="show ? 'text' : 'password'" class="block w-full pr-12" autocomplete="current-password" />
                <button type="button" @click="show = !show" :aria-label="show ? 'Hide current password' : 'Show current password'" :aria-pressed="show.toString()" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700"><x-heroicon-o-eye x-show="!show" class="h-5 w-5" /><x-heroicon-o-eye-slash x-show="show" x-cloak class="h-5 w-5" /></button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <div class="relative mt-1">
                <x-text-input id="update_password_password" name="password" type="password" x-bind:type="show ? 'text' : 'password'" class="block w-full pr-12" autocomplete="new-password" />
                <button type="button" @click="show = !show" :aria-label="show ? 'Hide new password' : 'Show new password'" :aria-pressed="show.toString()" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700"><x-heroicon-o-eye x-show="!show" class="h-5 w-5" /><x-heroicon-o-eye-slash x-show="show" x-cloak class="h-5 w-5" /></button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <div class="relative mt-1">
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" x-bind:type="show ? 'text' : 'password'" class="block w-full pr-12" autocomplete="new-password" />
                <button type="button" @click="show = !show" :aria-label="show ? 'Hide password confirmation' : 'Show password confirmation'" :aria-pressed="show.toString()" class="absolute inset-y-0 right-0 px-3 text-gray-500 hover:text-gray-700"><x-heroicon-o-eye x-show="!show" class="h-5 w-5" /><x-heroicon-o-eye-slash x-show="show" x-cloak class="h-5 w-5" /></button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>
        </div>

    </form>
</section>


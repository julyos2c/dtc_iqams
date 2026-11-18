<x-instructor-layout title="Account Settings">
    <div class="py-6">
        <div class="space-y-6">
            <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <section class="max-w-xl">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900">Email Address</h2>
                        <p class="mt-1 text-sm text-gray-600">Update your account email address.</p>
                    </header>
                    <form method="post" action="{{ route('instructor.settings.email') }}" class="mt-6 space-y-6">
                        @csrf @method('patch')
                        <div>
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', auth()->user()->email)" required autocomplete="email" />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </form>
                </section>
            </div>
            <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>
</x-instructor-layout>

<x-instructor-layout title="Profile">
    <div x-data="studentProfileQr" data-qr-url="{{ route('id-card.show') }}" data-student-number="{{ $user->instructor?->employee_no }}" class="mb-6 space-y-4 sm:space-y-6">
        <section class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-teal-950 via-teal-800 to-emerald-600 text-white shadow-lg">
            <div class="absolute inset-0 bg-cover bg-center opacity-10" style="background-image: url('{{ asset('images/dtc-building.jpg') }}')"></div>
            <div class="relative flex min-w-0 flex-col gap-4 p-4 sm:gap-6 sm:p-8 xl:flex-row xl:items-center">
                <div class="flex min-w-0 flex-1 flex-col items-center gap-2 text-center sm:flex-row sm:gap-5 sm:text-left">
                    <div class="relative shrink-0">
                        <div class="grid h-[88px] w-[88px] place-items-center overflow-hidden rounded-full border-4 border-white bg-teal-100 text-3xl font-semibold text-teal-900 sm:h-32 sm:w-32 sm:text-5xl">
                            @if ($user->avatar_url)
                                <img src="{{ $user->avatar_url }}" alt="Current profile photo" class="h-full w-full object-cover">
                            @else
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            @endif
                        </div>
                        <form method="POST" action="{{ route('my-profile.update') }}" enctype="multipart/form-data" class="absolute bottom-0 right-0">
                            @csrf @method('PATCH')
                            <input type="hidden" name="name" value="{{ $user->name }}">
                            <input type="hidden" name="email" value="{{ $user->email }}">
                            <input name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" x-ref="avatarInput" @change="if ($event.target.files.length) $el.form.requestSubmit()">
                            <button type="button" @click="$refs.avatarInput.click()" aria-label="Change profile photo" title="Change profile photo" class="grid h-11 w-11 place-items-center rounded-full border-2 border-white bg-teal-950 text-white hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-white"><x-heroicon-o-camera class="h-5 w-5" /></button>
                        </form>
                    </div>
                    <div class="min-w-0">
                        <h2 class="break-words text-xl font-bold sm:text-3xl">{{ $user->instructor?->fullName() ?? $user->name }}</h2>
                        <span @class(['mt-2 inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold sm:mt-3', 'bg-emerald-100 text-emerald-800' => $user->isAccountActive(), 'bg-red-100 text-red-800' => ! $user->isAccountActive()])><span class="h-2 w-2 rounded-full bg-current"></span>{{ $user->isAccountActive() ? 'Active' : 'Inactive' }}</span>
                        <p class="mt-2 break-words text-xs text-teal-50 sm:mt-3 sm:text-base">Teaching Personnel &ndash; {{ $user->instructor?->department?->department_name ?? 'Department not assigned' }}</p>
                    </div>
                </div>
                <div class="flex min-w-0 items-center justify-center gap-3 border-t border-white/40 pt-4 text-left sm:gap-6 sm:pt-6 xl:justify-start xl:border-l xl:border-t-0 xl:pl-8 xl:pt-0">
                    <div class="flex aspect-square w-[100px] max-w-[40%] shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-2 text-center text-xs text-slate-600 min-[375px]:w-[120px] sm:w-[140px] sm:max-w-full"><canvas x-ref="qr" class="block !h-auto !w-full max-w-full object-contain" x-show="ready" aria-label="Instructor attendance QR code"></canvas><span x-show="!ready" x-text="message"></span></div>
                    <div class="min-w-0"><p class="text-sm text-teal-50">Employee ID</p><p class="mt-1 break-words text-xl font-bold">{{ $user->instructor?->employee_no ?? 'Not assigned' }}</p>
                        <button type="button" @click="download" :disabled="!ready" class="mt-2 inline-flex min-h-10 max-w-full items-center justify-center gap-1 rounded-full border border-white px-3 py-1.5 text-xs font-semibold hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white disabled:cursor-not-allowed disabled:opacity-50 sm:mt-4"><x-heroicon-o-arrow-down-tray class="h-4 w-4 shrink-0" /> Download QR</button>
                    </div>
                </div>
            </div>
        </section>
        @error('avatar') <p role="alert" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</p> @enderror
        <div class="grid min-w-0 gap-4 sm:gap-6 xl:grid-cols-2">
            <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex items-center gap-4 border-b border-slate-200 pb-5"><span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-teal-100 text-teal-800"><x-heroicon-o-academic-cap class="h-6 w-6" /></span><div><h3 class="text-lg font-bold text-slate-900">Employment Information</h3><p class="text-sm text-slate-500">Your current employment details.</p></div></div>
                <dl class="divide-y divide-slate-200">
                    @foreach ([['Employee ID', $user->instructor?->employee_no ?? 'Not assigned', 'identification'], ['Department', $user->instructor?->department?->department_name ?? 'Not assigned', 'building-library']] as [$label, $value, $icon])
                        <div class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 py-4 md:grid-cols-[auto_minmax(0,1fr)_minmax(0,1.5fr)] md:items-center"><x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5 text-slate-500" /><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="col-start-2 mt-1 min-w-0 break-words text-sm font-semibold text-slate-900 md:col-start-auto md:mt-0">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </section>
            <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex items-center gap-4 border-b border-slate-200 pb-5"><span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-teal-100 text-teal-800"><x-heroicon-o-envelope class="h-6 w-6" /></span><div><h3 class="text-lg font-bold text-slate-900">Contact Information</h3><p class="text-sm text-slate-500">Your registered email address.</p></div></div>
                <dl><div class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 border-b border-slate-200 py-4 md:grid-cols-[auto_minmax(0,1fr)_minmax(0,1.5fr)] md:items-center"><x-heroicon-o-envelope class="h-5 w-5 text-slate-500" /><dt class="text-sm text-slate-500">Email</dt><dd class="col-start-2 mt-1 min-w-0 break-all text-sm font-semibold text-slate-900 md:col-start-auto md:mt-0">{{ $user->email }}</dd></div></dl>
            </section>
        </div>
    </div>
</x-instructor-layout>

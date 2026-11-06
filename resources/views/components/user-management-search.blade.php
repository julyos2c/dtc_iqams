<form method="GET" action="{{ url()->current() }}" class="w-full sm:w-80 sm:shrink-0" x-data="{ ...userManagementSearch(), query: @js((string) request()->query('search', '')) }" @submit.prevent="submit($el)">
    @foreach (request()->except(['search', 'page']) as $key => $value)
        @if (is_scalar($value))
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @elseif (is_array($value))
            @foreach (\Illuminate\Support\Arr::dot([$key => $value]) as $nestedKey => $nestedValue)
                @php($parts = explode('.', $nestedKey))
                <input type="hidden" name="{{ array_shift($parts) }}{{ collect($parts)->map(fn ($part) => '['.$part.']')->implode('') }}" value="{{ $nestedValue }}">
            @endforeach
        @endif
    @endforeach
    <div class="relative">
        <label for="user-management-search" class="sr-only">Search users by name, number, or email</label>
        <input id="user-management-search" name="search" type="text" value="{{ request()->query('search', '') }}" x-model="query" placeholder="Search by name, ID, or email" autocomplete="off" @input="schedule($el.form)" class="w-full rounded-md border-gray-300 py-2 pl-3 pr-16 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <button type="button" x-cloak x-show="query.length > 0" @click="clear($el.form)" aria-label="Clear search" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-xs text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <x-heroicon-o-x-mark class="h-4 w-4" aria-hidden="true" />
        </button>
    </div>
    <p x-cloak x-show="error" x-text="error" role="alert" class="mt-1 text-xs text-red-700"></p>
</form>

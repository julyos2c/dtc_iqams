@props(['endpoint', 'name', 'model', 'selected' => null, 'placeholder' => 'Search...', 'lookupKey' => null, 'emptyLabel' => null])

<div x-data="attendanceLookupSearch({ endpoint: @js($endpoint), selected: @js($selected), model: 'lookupValue', label: @js(str_replace(['Search ', '...'], '', $placeholder)) })"
    @repeatable-form-reset.window="reset()"
    @lookup-refresh.window="if (@js($lookupKey) && $event.detail?.key === @js($lookupKey)) { reset(); load($event.detail?.values?.[@js($name)]) }"
    @click.outside="open = false" class="relative">
    <input type="hidden" name="{{ $name }}" x-model="{{ $model }}">
    <input type="text" x-model="search" @input="{{ $model }} = ''; change()"
        @focus="if (!{{ $model }}) { open = true; load() }" @keydown.escape="open = false"
        placeholder="{{ $placeholder }}" autocomplete="off" aria-label="{{ $placeholder }}" :aria-expanded="open.toString()"
        class="w-full rounded-md border-gray-300 pr-10 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    <button type="button" x-cloak x-show="search.length" @click="search = ''; {{ $model }} = ''; change(); $el.previousElementSibling.focus()" aria-label="Clear search" class="absolute right-2 top-2 rounded p-1 text-gray-400 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"><x-heroicon-o-x-mark class="h-4 w-4" aria-hidden="true" /></button>
    <div x-cloak x-show="open" class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg">
        <p x-show="loading" class="px-3 py-2 text-sm text-gray-500">Searching...</p>
        <p x-show="error" x-text="error" role="alert" class="px-3 py-2 text-sm text-red-700"></p>
        <p x-show="!loading && !error && searched && options.length === 0" class="px-3 py-2 text-sm text-gray-500">No matches found.</p>
        <template x-for="option in options" :key="option.id">
            <button type="button" @click="select(option); {{ $model }} = String(option.id)" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-indigo-50 focus:bg-indigo-50" x-text="option.label"></button>
        </template>
    </div>
</div>

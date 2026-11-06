@props(['user', 'name', 'identifier', 'detail', 'detailTitle' => null])

<div class="flex min-w-0 items-start gap-2">
    <input type="checkbox" value="{{ $user->id }}" aria-label="Select {{ $name }}"
        :checked="selectedIds.includes({{ $user->id }})"
        @change="selectedIds = $event.target.checked ? [...selectedIds, {{ $user->id }}] : selectedIds.filter(id => id !== {{ $user->id }})"
        class="mr-2 mt-1 shrink-0 rounded border-gray-300 text-indigo-600">
    <img loading="lazy" width="40" height="40" src="{{ $user->avatar_thumbnail_url ?? asset('images/default-avatar.svg') }}"
        data-fallback-src="{{ asset('images/default-avatar.svg') }}" onerror="this.onerror = null; this.src = this.dataset.fallbackSrc"
        alt="" class="h-10 w-10 shrink-0 rounded-full object-cover">
    <div class="min-w-0">
        <div class="line-clamp-2 break-words font-medium text-gray-800" title="{{ $name }}">{{ $name }}</div>
        <div class="truncate text-xs text-gray-500" title="{{ $user->email }}" tabindex="0">{{ $user->email }}</div>
        <div class="mt-1 break-words text-xs text-gray-500 md:hidden" title="{{ $detailTitle ?? $detail }}">{{ $identifier }} · {{ $detail }}</div>
    </div>
</div>

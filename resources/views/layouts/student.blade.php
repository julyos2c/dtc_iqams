@php
    $portalUser = Auth::user(); $portalStudent = $portalUser->student;
    $initials = strtoupper(substr($portalStudent?->first_name ?? $portalUser->name, 0, 1));
    $descriptions = ['Dashboard'=>'Overview of your attendance and class schedule','My Attendance'=>'Review and filter your official attendance history','Schedule'=>'View your weekly class schedule','Excuse Requests'=>'Submit and track requests for upcoming classes','Student Profile'=>'Your personal and academic information','Account Settings'=>'Manage your password'];
    $excuseUnread = $portalUser->unreadNotifications()->where('type', \App\Notifications\StudentExcuseNotification::class)->count();
    $navigation = [
        'Navigation' => [
            ['student.dashboard','Dashboard','M4 5h6v6H4V5zm0 10h6v5H4v-5zm10-10h6v6h-6V5zm0 10h6v5h-6v-5z'],
            ['student.attendance','Attendance','M9 11l2 2 4-4m4-4v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2h8l4 4z'],
            ['student.excuses.index','Excuse Requests','M7 3h10a2 2 0 012 2v16l-7-3-7 3V5a2 2 0 012-2z'],
            ['student.schedule','Schedule','M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 011 1v13a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z'],
        ],
    ];
@endphp
<x-portal-document :title="$title" body-class="bg-[#f5f7f7] font-sans text-slate-800 antialiased" alpine-data="{ sidebarOpen: false, userMenuOpen: false }">
<x-toast-notifications />
<div class="min-h-screen lg:flex">
 <div x-show="sidebarOpen" x-cloak @click="sidebarOpen=false" class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden"></div>
 <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex h-screen w-60 flex-col border-r border-[#18504e] bg-[#093f3d] text-white transition-transform lg:translate-x-0">
  <div class="flex h-[76px] items-center border-b border-white/10 px-5"><div class="grid h-9 w-9 place-items-center border border-teal-300/40 bg-teal-300/10 text-sm font-bold text-teal-200">IQ</div><div class="ml-3"><p class="text-sm font-bold tracking-[.16em]">IQAMS</p><p class="text-xs text-teal-100/60">Student Portal</p></div><button @click="sidebarOpen=false" class="ml-auto p-2 lg:hidden" aria-label="Close navigation">&times;</button></div>
  <nav class="flex-1 overflow-y-auto px-3 py-6" aria-label="Student navigation">
   @foreach($navigation as $group => $items)
    <p @class(['px-3 text-[11px] font-semibold uppercase tracking-[.16em] text-teal-100/45','mt-8'=>$loop->index])>{{ $group }}</p><div class="mt-2 space-y-1">
    @foreach($items as $item) @php $active = request()->routeIs($item[0]); @endphp
     <a href="{{ route($item[0]).($item[3] ?? '') }}" @class(['relative flex h-10 items-center gap-3 px-3 text-sm transition','bg-white/10 font-semibold text-white before:absolute before:inset-y-2 before:left-0 before:w-0.5 before:bg-cyan-300'=>$active,'text-teal-50/70 hover:bg-white/5 hover:text-white'=>!$active])><svg class="h-[18px] w-[18px] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="{{ $item[2] }}"/></svg>{{ $item[1] }} @if($item[0] === 'student.excuses.index' && $excuseUnread)<span class="ml-auto rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white" aria-label="{{ $excuseUnread }} unread excuse notifications">{{ $excuseUnread }}</span>@endif</a>
    @endforeach</div>
   @endforeach
  </nav>
  <form method="POST" action="{{ route('logout') }}" class="border-t border-white/10 p-3">@csrf<button class="flex h-10 w-full items-center gap-3 px-3 text-sm text-teal-50/70 hover:bg-white/5 hover:text-white"><x-heroicon-o-arrow-right-start-on-rectangle class="h-[18px] w-[18px]" />Logout</button></form>
 </aside>
 <div class="min-w-0 flex-1 lg:ml-60">
  <header class="sticky top-0 z-30 flex min-h-[76px] items-center border-b border-slate-200 bg-white px-4 sm:px-7 lg:px-9"><button @click="sidebarOpen=true" class="mr-3 p-2 text-slate-600 lg:hidden" aria-label="Open navigation"><x-heroicon-o-bars-3 class="h-5 w-5" /></button><div class="min-w-0"><h1 class="truncate text-xl font-semibold text-slate-900 sm:text-2xl">{{ $title }}</h1><p class="hidden text-sm text-slate-500 sm:block">{{ $descriptions[$title] ?? 'IQAMS Student Portal' }}</p></div>
    <x-leave-notification-bell class="ml-auto mr-3" />
    <div class="relative"><button @click="userMenuOpen=!userMenuOpen" class="flex items-center gap-3 border-l border-slate-200 pl-4 text-left sm:pl-6"><span class="hidden max-w-52 text-right sm:block"><span class="block truncate text-sm font-semibold">{{ $portalStudent?->fullName() ?? $portalUser->name }}</span><span class="block text-xs text-slate-500">Student</span></span><span class="h-9 w-9 overflow-hidden rounded-full bg-teal-100 text-teal-800">@if($portalUser->avatar_thumbnail_url)<img loading="lazy" width="36" height="36" src="{{ $portalUser->avatar_thumbnail_url }}" alt="Profile photo" class="h-full w-full object-cover">@else<span class="grid h-full place-items-center text-sm font-semibold">{{ $initials }}</span>@endif</span><x-heroicon-o-chevron-down class="hidden h-4 w-4 text-slate-400 sm:block" /></button><div x-show="userMenuOpen" x-cloak @click.outside="userMenuOpen=false" class="absolute right-0 mt-3 w-52 border border-slate-200 bg-white py-1 shadow-lg"><a href="{{ route('student.profile') }}" class="block px-4 py-2.5 text-sm hover:bg-slate-50">Profile</a><a href="{{ route('student.settings') }}" class="block px-4 py-2.5 text-sm hover:bg-slate-50">Settings</a></div></div>
  </header>
  <main id="app-content" class="mx-auto max-w-[1440px] p-4 sm:p-7 lg:p-9">{{ $slot }}</main>
 </div>
</div>
</x-portal-document>

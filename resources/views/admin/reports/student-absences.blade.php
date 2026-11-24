<x-app-layout>
    <x-slot name="header"><h1 class="text-2xl font-bold text-gray-900">Student Absences</h1><p class="mt-1 text-sm text-gray-500">Students with five or more absences in a subject.</p></x-slot>
    <div class="py-6" x-data="absenceReport()">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div><h2 class="text-lg font-semibold text-gray-900">Student Absences</h2><p class="mt-1 text-sm text-gray-500">Absences are counted separately for each subject.</p></div>
                <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                    <button type="button" @click="open = !open" :aria-expanded="open" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"><x-heroicon-o-arrow-down-tray class="h-4 w-4" /> Download Report</button>
                    <div x-show="open" x-cloak class="absolute right-0 z-10 mt-1 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                        <button type="button" @click="open = false; download('summary')" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Summary PDF</button>
                        <button type="button" @click="open = false; download('detailed')" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Detailed PDF</button>
                    </div>
                </div>
            </div>
            <div x-ref="summary">@include('admin.reports.student-absences-summary')</div>
            <form x-ref="filters" @submit.prevent="load()" class="mb-5">
                <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <label class="text-sm text-gray-700">Search<input name="search" value="{{ request('search') }}" @input.debounce.300ms="load()" placeholder="Name or student ID" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
                <label class="text-sm text-gray-700">Department<select name="department" @change="$refs.filters.course.value=''; $refs.filters.section.value=''; load()" class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="">All departments</option>@foreach($departments as $item)<option value="{{ $item->id }}" @selected(request('department') == $item->id)>{{ $item->department_name }}</option>@endforeach</select></label>
                <label class="text-sm text-gray-700">Course<select name="course" @change="$refs.filters.section.value=''; load()" class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="">All courses</option>@foreach($courses as $item)<option value="{{ $item->id }}" data-department="{{ $item->department_id }}" @selected(request('course') == $item->id)>{{ $item->course_code }}</option>@endforeach</select></label>
                <label class="text-sm text-gray-700">Section<select name="section" @change="load()" class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="">All sections</option>@foreach($sections as $item)<option value="{{ $item->id }}" data-course="{{ $item->course_id }}" @selected(request('section') == $item->id)>{{ $item->section_name }}</option>@endforeach</select></label>
                <button type="button" @click="moreFilters = !moreFilters" :aria-expanded="moreFilters" aria-controls="additional-absence-filters" class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">More filters <span x-show="extraCount" x-cloak x-text="'(' + extraCount + ')'" class="text-indigo-700"></span><x-heroicon-o-chevron-down class="h-4 w-4" /></button>
                </div>
                <div id="additional-absence-filters" x-show="moreFilters" x-cloak class="mt-3 grid grid-cols-1 gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="text-sm text-gray-700">Subject<select name="subject" @change="load()" class="mt-1 w-full rounded-lg border-gray-300 text-sm"><option value="">All subjects</option>@foreach($subjects as $item)<option value="{{ $item->id }}" @selected(request('subject') == $item->id)>{{ $item->subject_code }} — {{ $item->subject_name }}</option>@endforeach</select></label>
                <label class="text-sm text-gray-700">From<input type="date" name="from" value="{{ request('from') }}" @change="load()" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
                <label class="text-sm text-gray-700">To<input type="date" name="to" value="{{ request('to') }}" @change="load()" class="mt-1 w-full rounded-lg border-gray-300 text-sm"></label>
                </div>
                <div class="mt-3 flex justify-end"><button type="button" @click="$refs.filters.reset(); for (const field of $refs.filters.elements) if (field.name) field.value=''; load()" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Clear filters</button></div>
                <hr class="mt-5 border-gray-200">
            </form>
            <p x-show="error" x-text="error" role="alert" class="mb-3 text-sm text-red-700"></p>
            <div x-ref="results" @click="const a = $event.target.closest('[data-pages] a'); if (a) { $event.preventDefault(); load(a.href) }" :aria-busy="loading">
                @include('admin.reports.student-absences-results')
            </div>
            </div>
        </div>
        <div x-show="modal" x-cloak @keydown.escape.window="modal=false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-label="Student absence details">
            <div @click.outside="modal=false" class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
                <div class="flex justify-between gap-4"><h2 class="text-lg font-bold">Absence Details</h2><button type="button" @click="modal=false" aria-label="Close" class="text-gray-600">✕</button></div>
                <p x-show="detailLoading" class="py-5 text-sm">Loading details…</p><p x-show="detailError" x-text="detailError" role="alert" class="py-5 text-sm text-red-700"></p>
                <template x-if="detail"><div class="mt-4"><div class="flex items-center gap-3"><img :src="detail.student.avatar_path ? '{{ asset('storage') }}/' + detail.student.avatar_path : '{{ asset('images/default-avatar.svg') }}'" alt="" class="h-12 w-12 rounded-full object-cover"><div><h3 class="font-semibold" x-text="[detail.student.first_name, detail.student.middle_name, detail.student.last_name, detail.student.name_suffix].filter(Boolean).join(' ')"></h3><p class="text-sm text-gray-600" x-text="detail.student.student_no + ' · ' + (detail.student.course_code || '—') + ' / ' + (detail.student.section_name || '—')"></p><p class="text-sm" x-text="detail.student.total_absences + ' absences · ' + (detail.student.max_subject_absences >= 5 ? 'At Risk' : 'Normal')"></p></div></div>
                    <template x-for="group in grouped()" :key="group.id"><section class="mt-4 rounded-lg border p-3"><h4 class="font-semibold" x-text="group.code + ' — ' + group.name"></h4><p class="text-sm text-gray-600" x-text="group.rows.length + ' absences · ' + (group.rows.length >= 5 ? 'At Risk' : 'Normal')"></p><ul class="mt-2 space-y-1 text-sm"><template x-for="(row, index) in group.rows" :key="index"><li x-text="row.attendance_date + ' · ' + row.start_time + '–' + row.end_time + ' · ' + [row.instructor_first, row.instructor_last].filter(Boolean).join(' ')"></li></template></ul></section></template>
                    <p x-show="detail.records.length === 0" class="mt-4 text-sm text-gray-500">No matching records.</p></div></template>
            </div>
        </div>
    </div>
    <script>
    function absenceReport() { return {
        loading:false, error:'', modal:false, detail:null, detailLoading:false, detailError:'', controller:null, sequence:0, moreFilters:false, extraCount:0,
        params() { return new URLSearchParams([...new FormData(this.$refs.filters)].filter(([k,v]) => v)); },
        dependent() { const f=this.$refs.filters; for (const o of f.course.options) if(o.value) o.hidden=!!f.department.value && o.dataset.department!==f.department.value; for (const o of f.section.options) if(o.value) o.hidden=!!f.course.value && o.dataset.course!==f.course.value; },
        updateExtraCount() { const f=this.$refs.filters; this.extraCount=Number(!!f.subject.value)+Number(!!(f.from.value || f.to.value)); },
        init() { this.dependent(); this.updateExtraCount(); },
        async load(url=null) { this.dependent(); this.updateExtraCount(); this.controller?.abort(); const seq=++this.sequence; this.controller=new AbortController(); this.loading=true; this.error=''; const target=url || '{{ route('admin.reports.student-absences.index') }}?' + this.params(); try { const r=await fetch(target,{signal:this.controller.signal,headers:{'X-Requested-With':'XMLHttpRequest'}}); if(!r.ok || r.redirected) throw new Error(); const doc=new DOMParser().parseFromString(await r.text(),'text/html'); if(seq!==this.sequence)return; const next=doc.querySelector('[data-report-results]'); const summary=doc.querySelector('[data-report-summary]'); if(!next || !summary)throw new Error(); this.$refs.results.innerHTML=next.outerHTML; this.$refs.summary.innerHTML=summary.outerHTML; history.replaceState(null,'',target); } catch(e) { if(e.name!=='AbortError' && seq===this.sequence) this.error='Unable to load results. Check the filters and try again.'; } finally { if(seq===this.sequence)this.loading=false; } },
        download(type) { const p=this.params(); p.set('type',type); window.location='{{ route('admin.reports.student-absences.export') }}?' + p; },
        async show(id, subject) { this.modal=true; this.detail=null; this.detailError=''; this.detailLoading=true; try { const p=this.params(); p.set('subject', subject); const r=await fetch('{{ url('admin/reports/student-absences') }}/'+id+'?'+p,{headers:{Accept:'application/json'}}); if(!r.ok)throw new Error(); this.detail=await r.json(); } catch(e) { this.detailError='Unable to load details. Please try again.'; } finally { this.detailLoading=false; } },
        grouped() { const groups={}; for(const r of this.detail.records) { groups[r.subject_id] ||= {id:r.subject_id,code:r.subject_code,name:r.subject_name,rows:[]}; groups[r.subject_id].rows.push(r); } return Object.values(groups); }
    }; }
    </script>
</x-app-layout>

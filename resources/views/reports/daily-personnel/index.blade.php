<x-app-layout>
    <x-slot name="header">
        <div class="no-print">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Daily Personnel Attendance</h1>
            <p class="mt-1 text-sm text-gray-500">Combined daily attendance for teaching and non-teaching personnel.</p>
        </div>
    </x-slot>

    <style>
        .attendance-document { box-sizing: border-box; background: #fff; color: #111827; padding: 2rem; }
        .report-heading { margin-bottom: 1.5rem; text-align: center; }
        .report-heading h2 { font-size: 1.2rem; font-weight: 700; letter-spacing: .04em; }
        .report-heading h3 { margin-top: .25rem; font-size: 1.05rem; font-weight: 700; }
        .report-heading p { margin-top: .5rem; font-size: .875rem; }
        .attendance-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: .8rem; }
        .attendance-table th, .attendance-table td { border: 1px solid #374151; padding: .55rem .6rem; }
        .attendance-table th { background: #f3f4f6; text-align: center; vertical-align: middle; font-weight: 700; }
        .attendance-table td:not(:first-child) { text-align: center; white-space: nowrap; }
        .attendance-table .name-column { width: 36%; }
        .attendance-table .empty-report { padding: 1.5rem; text-align: center; color: #6b7280; }
        .report-signatures { display: grid; grid-template-columns: 1fr 1fr .75fr; gap: 2rem; margin-top: 2rem; font-size: .8rem; }
        .report-signatures p { display: flex; align-items: end; gap: .4rem; white-space: nowrap; }
        .report-signatures span { display: inline-block; min-width: 10rem; flex: 1; border-bottom: 1px solid #111827; }
        .report-signatures .date-line { min-width: 7rem; }
        [data-report-filter] .grid > div { min-width: 0; }
        [data-report-filter] select { min-width: 0; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            html, body { height: auto !important; overflow: visible !important; background: #fff !important; }
            body > div, #app-content, main { height: auto !important; min-height: 0 !important; margin: 0 !important; overflow: visible !important; }
            aside, body > div > div.lg\:hidden, #app-content > header, .no-print, [role="status"] { display: none !important; }
            *, *::before, *::after { box-sizing: border-box; }
            .report-shell { width: 100% !important; margin: 0 !important; padding: 0 !important; max-width: none !important; }
            .report-shell > .overflow-hidden { overflow: visible !important; border-radius: 0 !important; box-shadow: none !important; }
            .attendance-document { width: 100%; padding: 0; box-shadow: none !important; }
            .report-heading { margin-bottom: 6mm; }
            .attendance-table { width: 100%; font-size: 10pt; }
            .attendance-table th, .attendance-table td { padding: 3.5mm 2.5mm; }
            .attendance-table td:not(:first-child) { white-space: nowrap; }
            .attendance-table thead { display: table-header-group; }
            .attendance-table tr { break-inside: avoid; page-break-inside: avoid; }
            .report-signatures { margin-top: 8mm; break-inside: avoid; page-break-inside: avoid; }
        }
    </style>

    <div class="report-shell mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <form method="GET" action="{{ route('admin.reports.daily-personnel.index') }}" data-report-filter class="no-print mb-6 rounded-lg bg-white p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label for="date" class="mb-1 block text-sm font-medium text-gray-700">Date</label>
                    <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="personnel_type" class="mb-1 block text-sm font-medium text-gray-700">Personnel Type</label>
                    <select id="personnel_type" name="personnel_type" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Personnel</option>
                        <option value="instructor" @selected(($filters['personnel_type'] ?? '') === 'instructor')>Teaching Personnel</option>
                        <option value="staff" @selected(($filters['personnel_type'] ?? '') === 'staff')>Non-Teaching Staff</option>
                    </select>
                    @error('personnel_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="department_id" class="mb-1 block text-sm font-medium text-gray-700">Department</label>
                    <select id="department_id" name="department_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" title="{{ $department->department_name }}" @selected((string)($filters['department_id'] ?? '') === (string)$department->id)>{{ $department->department_code }}</option>
                        @endforeach
                    </select>
                    @error('department_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="office_unit_id" class="mb-1 block text-sm font-medium text-gray-700">Office/Unit</label>
                    <select id="office_unit_id" name="office_unit_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Offices/Units</option>
                        @foreach($officeUnits as $officeUnit)
                            @php($officeUnitLabel = $officeUnit->code.' — '.$officeUnit->name)
                            <option value="{{ $officeUnit->id }}" title="{{ $officeUnitLabel }}" @selected((string)($filters['office_unit_id'] ?? '') === (string)$officeUnit->id)>{{ \Illuminate\Support\Str::limit($officeUnitLabel, 22) }}</option>
                        @endforeach
                    </select>
                    @error('office_unit_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="personnel_id" class="mb-1 block text-sm font-medium text-gray-700">Personnel</label>
                    <select id="personnel_id" name="personnel_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Personnel</option>
                        @foreach($personnel as $person)
                            @php($personnelLabel = $person['name'].' ('.($person['personnel_type'] === 'instructor' ? 'Teaching' : 'Non-Teaching').')')
                            <option value="{{ $person['user_id'] }}" title="{{ $personnelLabel }}" @selected((string)($filters['personnel_id'] ?? '') === (string)$person['user_id'])>{{ \Illuminate\Support\Str::limit($personnelLabel, 22) }}</option>
                        @endforeach
                    </select>
                    @error('personnel_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Generate Report</button>
                <a href="{{ route('admin.reports.daily-personnel.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Reset</a>
                <div class="ml-auto flex flex-wrap gap-2">
                    @foreach(['pdf' => 'Export PDF', 'xlsx' => 'Export Excel'] as $format => $label)
                        <button type="button" data-report-export-format="{{ $format }}" data-report-export-url="{{ route('admin.reports.daily-personnel.exports.store') }}" class="rounded border px-4 py-2 text-sm font-medium {{ $format === 'pdf' ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100' }}">{{ $label }}</button>
                    @endforeach
                    <button type="button" onclick="window.print()" class="rounded border border-gray-300 bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Print</button>
                </div>
            </div>
            <div data-report-export-status class="mt-3 text-sm text-gray-600" aria-live="polite"></div>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            @include('reports.daily-personnel._report')
        </div>
    </div>

</x-app-layout>

<nav aria-label="Manage students" class="mb-5 flex gap-2 border-b border-gray-200">
    <a href="{{ route('students.index') }}" @class(['border-b-2 px-4 py-3 text-sm font-medium', 'border-indigo-600 text-indigo-700' => $activeTab === 'students', 'border-transparent text-gray-500 hover:text-gray-700' => $activeTab !== 'students']) @if($activeTab === 'students') aria-current="page" @endif>Students</a>
    @can('review-enrollment-requests')
        <a href="{{ route('admin.students.submissions') }}" @class(['border-b-2 px-4 py-3 text-sm font-medium', 'border-indigo-600 text-indigo-700' => $activeTab === 'submissions', 'border-transparent text-gray-500 hover:text-gray-700' => $activeTab !== 'submissions']) @if($activeTab === 'submissions') aria-current="page" @endif>Instructor submissions</a>
    @endcan
</nav>

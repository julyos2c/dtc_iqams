<x-instructor-layout title="Correct Enrollment Request">
    <a class="mb-4 inline-block text-sm text-blue-700 underline" href="{{ route('instructor.subjects.show', $enrollmentRequest->recurring_schedule_group_id) }}">Back to this subject</a>
    @include('instructor.class-lists.errors')
    <section class="rounded-xl border bg-white p-5"><h2 class="text-lg font-semibold">{{ $enrollmentRequest->snapshot['subject'] }} · {{ $enrollmentRequest->snapshot['section'] }}</h2><p class="mt-3 rounded bg-red-50 p-3 text-sm text-red-800">Admin reason: {{ $enrollmentRequest->review_notes }}</p><form method="POST" action="{{ route('instructor.enrollment-requests.resubmit', $enrollmentRequest) }}" class="mt-4 grid gap-4 sm:grid-cols-3">@csrf
        @if($enrollmentRequest->registration_request_id && !$enrollmentRequest->identity->student_id)@include('instructor.class-lists.registration-fields', ['details' => $enrollmentRequest->snapshot['student']])@else<p class="sm:col-span-3">{{ $enrollmentRequest->identity->student_no }} · Existing student. Account details are maintained by the admin.</p>@endif
        <label class="text-sm sm:col-span-3"><input type="checkbox" name="confirmed" value="1" required class="mr-2 rounded">I corrected the request and verified this subject roster against the official printed list.</label><button class="rounded bg-blue-700 px-4 py-2 text-white">Submit corrected request</button>
    </form></section>
</x-instructor-layout>

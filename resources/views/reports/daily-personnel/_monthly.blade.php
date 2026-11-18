<section class="attendance-document" aria-label="Monthly personnel attendance report">
<header class="report-heading"><h2>DANAO TECHNOLOGICAL COLLEGE</h2><h3>MONTHLY PERSONNEL ATTENDANCE REPORT</h3><p>Month: {{ $date->format('F Y') }}</p><p>Dates with recorded attendance scans</p></header>
<table class="attendance-table"><thead><tr><th>Name</th><th>Date</th><th>Morning In</th><th>Morning Out</th><th>Afternoon In</th><th>Afternoon Out</th></tr></thead><tbody>
@forelse ($rows as $row)
<tr><td>{{ $row['name'] }}</td><td>{{ $row['day'] }}</td><td>{{ $row['morning_time_in'] }}</td><td>{{ $row['morning_time_out'] }}</td><td>{{ $row['afternoon_time_in'] }}</td><td>{{ $row['afternoon_time_out'] }}</td></tr>
@empty
<tr><td colspan="6" class="empty-report">No attendance scans found for the selected month and filters.</td></tr>
@endforelse
</tbody></table><footer class="report-signatures"><p>Prepared by: <span></span></p><p>Checked by: <span></span></p><p>Date: <span class="date-line"></span></p></footer></section>
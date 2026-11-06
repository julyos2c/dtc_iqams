export async function loadClassAttendance(state, date, fetcher = fetch) {
    state.attendanceRequest?.abort();
    const controller = new AbortController();
    state.attendanceRequest = controller;
    state.selectedDate = date;
    state.loading = true;
    state.error = '';
    state.attendance = null;

    try {
        const url = `${state.endpoint.replace('__SCHEDULE__', state.selectedDay.schedule_id)}?date=${encodeURIComponent(date)}`;
        const response = await fetcher(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Attendance could not be loaded.');
        if (state.attendanceRequest === controller) state.attendance = data;
    } catch (error) {
        if (state.attendanceRequest === controller && error.name !== 'AbortError') state.error = error.message || 'Attendance could not be loaded.';
    } finally {
        if (state.attendanceRequest === controller) {
            state.loading = false;
            state.attendanceRequest = null;
        }
    }
}

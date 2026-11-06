export function installReportExports(doc, win) {
    doc.querySelectorAll('[data-report-export-format]').forEach((button) => {
        button.addEventListener('click', async () => {
            const status = doc.querySelector('[data-report-export-status]');
            const body = new FormData(doc.querySelector('[data-report-filter]'));
            body.set('format', button.dataset.reportExportFormat);
            button.disabled = true;
            status.textContent = 'Preparing export…';
            try {
                const response = await win.fetch(button.dataset.reportExportUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': doc.querySelector('meta[name="csrf-token"]').content },
                    body,
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'The export could not be queued.');
                status.textContent = 'Export queued. This page will provide the download when it is ready.';
                const poll = async () => {
                    try {
                        const result = await win.fetch(payload.status_url, { headers: { Accept: 'application/json' } });
                        if (!result.ok) throw new Error('Unable to check export status.');
                        const current = await result.json();
                        if (current.status === 'completed' && current.download_url) {
                            status.textContent = 'Export ready.';
                            const link = doc.createElement('a');
                            link.href = current.download_url;
                            link.download = '';
                            link.textContent = 'Download completed export';
                            link.className = 'ml-2 font-medium text-indigo-600 hover:text-indigo-800';
                            status.appendChild(link);
                            button.disabled = false;
                        } else if (current.status === 'failed' || current.status === 'expired') {
                            status.textContent = 'The export is no longer available. Please request it again.';
                            button.disabled = false;
                        } else {
                            win.setTimeout(poll, 2000);
                        }
                    } catch {
                        status.textContent = 'Unable to check export status. ';
                        const retry = doc.createElement('button');
                        retry.type = 'button';
                        retry.textContent = 'Retry';
                        retry.className = 'font-medium text-indigo-600 hover:text-indigo-800';
                        retry.addEventListener('click', () => {
                            status.textContent = 'Checking export status…';
                            poll();
                        }, { once: true });
                        status.appendChild(retry);
                        button.disabled = false;
                    }
                };
                win.setTimeout(poll, 1000);
            } catch (error) {
                status.textContent = error.message || 'The export could not be queued.';
                button.disabled = false;
            }
        });
    });
}

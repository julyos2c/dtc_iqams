<div x-data="{
    show: false, busy: false, loading: false, userId: null, profile: {}, info: {}, qrValue: '', reason: '', error: '', confirmation: '', previousOverflow: '',
    async open(profile) {
        this.profile = profile; this.userId = profile.id; this.info = {}; this.qrValue = ''; this.reason = ''; this.error = ''; this.confirmation = '';
        if (!this.show) { this.previousOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden'; }
        this.show = true; await this.refresh();
    },
    close() { if (this.busy) return; this.show = false; this.qrValue = ''; document.body.style.overflow = this.previousOverflow; },
    async refresh() {
        const id = this.userId; this.qrValue = ''; this.loading = true;
        try {
            const response = await fetch('{{ url('admin/users') }}/' + id + '/qr', { headers: { Accept: 'application/json' } });
            const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Unable to load QR information.');
            if (id !== this.userId || !this.show) return;
            this.info = data;
            if (data.status === 'active') {
                const cardResponse = await fetch('{{ url('admin/id-cards') }}/' + id, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const card = await cardResponse.json(); if (!cardResponse.ok) throw new Error(card.message || 'QR code unavailable.');
                if (id === this.userId && this.show && this.info.status === 'active') this.qrValue = card.qr_code;
            }
        } catch (e) { if (id === this.userId && this.show) this.error = e.message; }
        finally { if (id === this.userId) this.loading = false; }
    },
    async submit() {
        this.busy = true; this.error = '';
        try {
            const response = await fetch('{{ url('admin/users') }}/' + this.userId + '/qr/' + this.confirmation, {
                method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ reason: this.reason })
            });
            const data = await response.json(); if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'QR action failed.');
            window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'success', message: data.message } }));
            this.confirmation = ''; await this.refresh();
        } catch (e) { this.error = e.message; } finally { this.busy = false; }
    },
    async printQr() {
        try {
            await window.ensureIqamsQrCode();
            await window.printIqamsIdCard('{{ url('admin/id-cards') }}/' + this.userId);
        } catch (e) { this.error = e.message || 'Unable to print the ID card.'; }
    }
}" @manage-qr.window="open($event.detail)" x-cloak
    x-effect="if ($refs.qrTarget) { const value = qrValue; $refs.qrTarget.replaceChildren(); if (show && value) window.ensureIqamsQrCode().then(() => { if (show && qrValue === value && $refs.qrTarget) { $refs.qrTarget.replaceChildren(); new window.QRCode($refs.qrTarget, { text: value, width: 200, height: 200 }); } }).catch(() => { error = 'QR code could not be displayed.'; }); }">
    <div x-show="show" class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-900/50 p-3 sm:p-5" @keydown.escape.window="if (show) close()">
        <div @click.outside="close()" role="dialog" aria-modal="true" aria-labelledby="qr-management-title" aria-describedby="qr-management-description" class="flex max-h-[90vh] w-full max-w-[900px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <header class="flex shrink-0 items-start gap-3 border-b border-slate-100 px-5 py-4 sm:px-7">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700"><x-heroicon-o-qr-code class="h-7 w-7" /></div>
                <div class="min-w-0 flex-1"><h2 id="qr-management-title" class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Manage QR Code</h2><p id="qr-management-description" class="mt-1 text-sm text-slate-500">View, revoke, or reissue the attendance QR code for this user.</p></div>
                <button type="button" @click="close()" :disabled="busy" aria-label="Close" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"><x-heroicon-o-x-mark class="h-6 w-6" /></button>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-7">
                <div x-show="error" role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700" x-text="error"></div>
                <section class="flex items-center gap-4 rounded-xl border border-indigo-100 bg-indigo-50/60 p-4">
                    <img :src="profile.avatar || '{{ asset('images/default-avatar.svg') }}'" x-on:error="$el.onerror = null; $el.src = '{{ asset('images/default-avatar.svg') }}'" alt="" class="h-[72px] w-[72px] shrink-0 rounded-full border-2 border-indigo-200 bg-white object-cover">
                    <div class="min-w-0"><h3 class="break-words text-lg font-bold leading-tight text-slate-900" x-text="info.name || 'Loading...' "></h3><span class="mt-1 inline-flex rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-800" x-text="profile.role"></span><div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600"><span>ID Number: <strong class="font-medium text-slate-800" x-text="info.identifier || '—'"></strong></span><hr aria-hidden="true" class="h-4 border-0 border-l border-slate-300"><span><span x-text="profile.detail_label"></span>: <strong class="font-medium text-slate-800" x-text="profile.detail || 'Not assigned'"></strong></span></div></div>
                </section>
                <div class="mt-5 grid gap-5 border-t border-slate-200 pt-5 md:grid-cols-2">
                    <section class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
                        <h3 class="inline-flex rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">Current QR Code</h3>
                        <div class="mx-auto mt-4 flex min-h-[220px] w-full max-w-[220px] items-center justify-center rounded-xl border border-indigo-200 bg-white p-2">
                            <div x-ref="qrTarget" x-show="qrValue" aria-label="Current QR code" class="max-w-full [&_canvas]:max-w-full"></div>
                            <p x-show="!qrValue" class="px-3 text-sm text-slate-500" x-text="loading ? 'Loading QR code...' : (info.status === 'revoked' ? 'This QR code has been revoked.' : (info.status === 'unissued' ? 'No QR code has been issued.' : 'QR code unavailable.'))"></p>
                        </div>
                        <p class="mt-3 text-sm text-slate-500" x-text="qrValue ? 'Scan this code for attendance' : 'No active code to scan'"></p>
                        <button type="button" @click="printQr()" :disabled="!qrValue || busy" class="mt-4 inline-flex w-full max-w-[260px] items-center justify-center gap-2 rounded-lg bg-indigo-100 px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-200 disabled:cursor-not-allowed disabled:opacity-50"><x-heroicon-o-printer class="h-5 w-5" />Print ID Card</button>
                    </section>
                    <section class="min-w-0">
                        <div class="flex items-start gap-3 rounded-xl p-4" :class="info.status === 'active' ? 'bg-emerald-50 text-emerald-800' : (info.status === 'revoked' ? 'bg-red-50 text-red-800' : 'bg-slate-100 text-slate-700')">
                            <x-heroicon-o-check-circle x-show="info.status === 'active'" class="h-7 w-7 shrink-0" />
                            <x-heroicon-o-shield-exclamation x-show="info.status !== 'active'" class="h-7 w-7 shrink-0" />
                            <div><h3 class="font-bold capitalize" x-text="info.status || 'Loading...'"></h3><p class="mt-1 text-sm" x-text="info.status === 'active' ? 'This QR code is valid for attendance.' : (info.status === 'revoked' ? 'This QR code is no longer valid for attendance.' : 'No active QR code is available.')"></p></div>
                        </div>
                        <dl class="mt-4">
                            <div class="flex items-center gap-3 border-b border-slate-200 py-3 text-sm"><x-heroicon-o-calendar-days class="h-5 w-5 shrink-0 text-slate-500" /><dt class="text-slate-600">Latest Issuance</dt><dd class="ml-auto text-right font-semibold text-slate-900" x-text="info.issued_at ? new Date(info.issued_at).toLocaleString() : '—'"></dd></div>
                            <div class="flex items-center gap-3 border-b border-slate-200 py-3 text-sm"><x-heroicon-o-squares-2x2 class="h-5 w-5 shrink-0 text-slate-500" /><dt class="text-slate-600">QR Version</dt><dd class="ml-auto text-right font-semibold text-slate-900" x-text="info.version ? 'v' + info.version : 'Unavailable'"></dd></div>
                            <div class="flex items-center gap-3 border-b border-slate-200 py-3 text-sm"><x-heroicon-o-arrow-path class="h-5 w-5 shrink-0 text-slate-500" /><dt class="text-slate-600">Total Reissuances</dt><dd class="ml-auto text-right font-semibold text-slate-900" x-text="info.total_reissuances ?? '—'"></dd></div>
                            <div class="flex items-center gap-3 border-b border-slate-200 py-3 text-sm"><x-heroicon-o-shield-check class="h-5 w-5 shrink-0 text-slate-500" /><dt class="text-slate-600">Revocation History</dt><dd class="ml-auto text-right font-semibold text-slate-900" x-text="info.revocation_history?.length ? info.revocation_history.length + (info.revocation_history.length === 1 ? ' record' : ' records') : 'None'"></dd></div>
                        </dl>
                        <div x-show="info.revocation_history?.length" class="mt-3 space-y-2" aria-label="Revocation history">
                            <template x-for="(entry, index) in (info.revocation_history || [])" :key="index">
                                <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600">
                                    <div class="flex flex-wrap justify-between gap-x-3"><span class="font-semibold text-slate-800" x-text="entry.reason || 'Reason unavailable'"></span><time x-text="entry.date ? new Date(entry.date).toLocaleString() : 'Date unavailable'"></time></div>
                                    <p x-text="'By: ' + (entry.administrator || 'Unavailable')"></p>
                                </div>
                            </template>
                        </div>
                    </section>
                </div>
                <div x-show="confirmation" x-ref="confirmationPanel" class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-sm font-semibold text-slate-900" x-text="confirmation === 'revoke' ? 'Confirm revocation of this QR code?' : 'Confirm reissuance? The previous QR code will stop working.'"></p>
                    <label x-show="confirmation === 'revoke'" class="mt-3 block text-sm font-medium text-slate-700">Reason<select x-model="reason" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"><option value="">Select a reason</option><option>Lost ID</option><option>Stolen ID</option><option>Damaged ID</option><option>Suspected Compromise</option><option>Other</option></select></label>
                    <div class="mt-4 flex gap-2"><button type="button" @click="submit()" :disabled="busy || (confirmation === 'revoke' && !reason)" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" x-text="busy ? 'Please wait...' : 'Confirm'"></button><button type="button" @click="confirmation = ''" :disabled="busy" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-700 disabled:opacity-50">Cancel</button></div>
                </div>
            </div>
            <footer class="grid shrink-0 gap-3 border-t border-slate-200 bg-white px-5 py-4 sm:grid-cols-2 sm:px-7">
                <button type="button" @click="confirmation = 'revoke'; error = ''; $nextTick(() => $refs.confirmationPanel.scrollIntoView({ block: 'nearest' }))" :disabled="busy || loading || info.status !== 'active'" class="flex items-center gap-3 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-left text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"><x-heroicon-o-shield-exclamation class="h-7 w-7 shrink-0" /><span><span class="block text-sm font-bold">Revoke QR Code</span><span class="mt-1 block text-xs leading-4">Invalidate this QR code if lost, stolen, or compromised.</span></span></button>
                <button type="button" @click="confirmation = 'reissue'; error = ''; $nextTick(() => $refs.confirmationPanel.scrollIntoView({ block: 'nearest' }))" :disabled="busy || loading || !info.status" class="flex items-center gap-3 rounded-xl bg-indigo-700 px-4 py-3 text-left text-white hover:bg-indigo-800 disabled:cursor-not-allowed disabled:opacity-50"><x-heroicon-o-arrow-path class="h-7 w-7 shrink-0" /><span><span class="block text-sm font-bold">Reissue QR Code</span><span class="mt-1 block text-xs leading-4 text-indigo-100">Generate a new QR code and replace the current one.</span></span></button>
            </footer>
        </div>
    </div>
</div>

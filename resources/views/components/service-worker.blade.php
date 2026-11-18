<script>
    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(@json(asset('service-worker.js'))).catch(() => {});
        });
    }
</script>

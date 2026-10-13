// Register the PWA service worker only on secure contexts (localhost is allowed by browsers).
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.warn('Saflivo service worker registration failed.', error);
        });
    });
}

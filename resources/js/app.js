import './bootstrap';

// El secreto del enlace viaja en el fragmento: no llega a logs HTTP ni al Referer.
const trackingForm = document.querySelector('#tracking-access');
if (trackingForm && window.location.hash) {
    const params = new URLSearchParams(window.location.hash.slice(1));
    const number = params.get('number');
    const code = params.get('code');
    history.replaceState(null, '', window.location.pathname);
    if (number && /^[a-f0-9]{64}$/.test(code ?? '')) {
        trackingForm.elements.number.value = number;
        trackingForm.elements.code.value = code;
        trackingForm.requestSubmit();
    }
}

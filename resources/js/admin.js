import adminMap from './admin-map';

document.addEventListener('alpine:init', () => {
    window.Alpine.data('adminMap', adminMap);
});

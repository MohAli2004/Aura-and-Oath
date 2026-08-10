import './bootstrap';
import Alpine from 'alpinejs';
import checkoutPage from './checkout';

window.Alpine = Alpine;
Alpine.data('checkoutPage', checkoutPage);
Alpine.start();

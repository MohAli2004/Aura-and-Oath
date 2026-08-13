import './bootstrap';
import Alpine from 'alpinejs';
import checkoutPage from './checkout';
import pushNotifications from './push-notifications';
import { auraFetch, flashToast, notifyNotificationsChanged } from './aura-http';

window.Alpine = Alpine;
window.auraHttp = auraFetch;
window.auraFlash = flashToast;
window.auraNotifyNotificationsChanged = notifyNotificationsChanged;

Alpine.data('checkoutPage', checkoutPage);
Alpine.data('pushNotifications', pushNotifications);
Alpine.start();

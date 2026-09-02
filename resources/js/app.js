import './bootstrap';
import Alpine from 'alpinejs';
import checkoutPage from './checkout';
import pushNotifications from './push-notifications';
import { auraFetch, flashToast, notifyNotificationsChanged } from './aura-http';

window.Alpine = Alpine;
window.auraHttp = auraFetch;
window.auraFlash = flashToast;
window.auraNotifyNotificationsChanged = notifyNotificationsChanged;

window.addEventListener('aura:cart-count', (event) => {
    const count = Number(event.detail?.count ?? 0);
    document.querySelectorAll('[data-cart-count]').forEach((el) => {
        el.textContent = String(count);
        el.hidden = count < 1;
    });
});

Alpine.data('checkoutPage', checkoutPage);
Alpine.data('pushNotifications', pushNotifications);
Alpine.data('productCard', (config = {}) => ({
    productId: config.productId,
    variants: Array.isArray(config.variants) ? config.variants : [],
    variantIndex: Number(config.variantIndex || 0),
    fallbackImage: config.fallbackImage || '',
    basePriceLabel: config.basePriceLabel || '',
    baseCompareAt: config.baseCompareAt || null,
    baseStockLabel: config.baseStockLabel || '',
    baseCanBuy: Boolean(config.baseCanBuy),
    baseMaxQty: (config.baseMaxQty === null || config.baseMaxQty === undefined || config.baseMaxQty === '')
        ? null
        : (Number.isFinite(Number(config.baseMaxQty)) ? Number(config.baseMaxQty) : null),
    baseVariantId: config.baseVariantId ?? null,
    authenticated: Boolean(config.authenticated),
    cartUrl: config.cartUrl,
    wishlistUrl: config.wishlistUrl,
    loginUrl: config.loginUrl,
    adding: false,
    wishing: false,
    wished: Boolean(config.wished),
    quantity: 1,
    slideKey: 0,
    slideDirection: 1,
    touchStartX: null,
    get hasCarousel() {
        return this.variants.length > 1;
    },
    get currentVariant() {
        return this.variants[this.variantIndex] || this.variants[0] || null;
    },
    get variantId() {
        return this.currentVariant?.id ?? this.baseVariantId;
    },
    get canBuy() {
        if (this.currentVariant) {
            return Boolean(this.currentVariant.purchasable);
        }

        return this.baseCanBuy;
    },
    get activeImage() {
        return this.currentVariant?.image || this.fallbackImage;
    },
    get priceLabel() {
        return this.currentVariant?.priceLabel || this.basePriceLabel;
    },
    get compareAt() {
        return this.currentVariant ? (this.currentVariant.compareAt || null) : this.baseCompareAt;
    },
    get stockLabel() {
        return this.currentVariant?.stockLabel || this.baseStockLabel;
    },
    get maxQty() {
        if (this.currentVariant && this.currentVariant.stock != null) {
            return Math.max(1, Number(this.currentVariant.stock) || 1);
        }

        if (this.baseMaxQty != null) {
            return Math.max(1, this.baseMaxQty);
        }

        return null;
    },
    normalizeQty() {
        let value = Number.parseInt(this.quantity, 10);

        if (! Number.isFinite(value) || value < 1) {
            value = 1;
        }

        if (this.maxQty != null) {
            value = Math.min(value, this.maxQty);
        }

        this.quantity = value;
    },
    goToVariant(index, direction = 1) {
        if (! this.hasCarousel) {
            return;
        }

        const total = this.variants.length;
        this.slideDirection = direction;
        this.variantIndex = ((index % total) + total) % total;
        this.slideKey += 1;
    },
    nextVariant() {
        this.goToVariant(this.variantIndex + 1, 1);
    },
    prevVariant() {
        this.goToVariant(this.variantIndex - 1, -1);
    },
    onTouchStart(event) {
        if (! this.hasCarousel) {
            return;
        }

        this.touchStartX = event.changedTouches?.[0]?.clientX ?? null;
    },
    onTouchEnd(event) {
        if (! this.hasCarousel || this.touchStartX == null) {
            return;
        }

        const endX = event.changedTouches?.[0]?.clientX ?? this.touchStartX;
        const delta = endX - this.touchStartX;
        this.touchStartX = null;

        if (Math.abs(delta) < 36) {
            return;
        }

        if (delta < 0) {
            this.nextVariant();
        } else {
            this.prevVariant();
        }
    },
    async addToBag() {
        if (this.adding || ! this.canBuy) {
            return;
        }

        this.normalizeQty();
        this.adding = true;

        try {
            const body = {
                product_id: this.productId,
                quantity: this.quantity,
            };

            if (this.variantId) {
                body.product_variant_id = this.variantId;
            }

            const data = await window.auraHttp(this.cartUrl, { method: 'POST', body });
            window.auraFlash(data.message || 'Added to bag.');

            if (typeof data.count === 'number') {
                window.dispatchEvent(new CustomEvent('aura:cart-count', { detail: { count: data.count } }));
            }
        } catch (error) {
            window.auraFlash(error?.message || 'Could not add to bag.', 'error');
        } finally {
            this.adding = false;
        }
    },
    async toggleWishlist() {
        if (! this.authenticated) {
            window.location.href = this.loginUrl;
            return;
        }

        if (this.wishing) {
            return;
        }

        this.wishing = true;
        const next = ! this.wished;
        this.wished = next;

        try {
            const body = { product_id: this.productId };

            if (this.variantId) {
                body.product_variant_id = this.variantId;
            }

            const data = await window.auraHttp(this.wishlistUrl, { method: 'POST', body });
            this.wished = Boolean(data.wished);
            window.auraFlash(data.message || (this.wished ? 'Saved to wishlist.' : 'Removed from wishlist.'));

            if (! this.wished) {
                const wrap = this.$el.closest('[data-wishlist-item]');
                wrap?.remove();
                window.dispatchEvent(new CustomEvent('aura-wishlist-removed'));
            }
        } catch (error) {
            this.wished = ! next;
            window.auraFlash(error?.message || 'Could not update wishlist.', 'error');
        } finally {
            this.wishing = false;
        }
    },
}));
Alpine.start();

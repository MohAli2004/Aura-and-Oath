export default function checkoutPage(config) {
    return {
        regionId: String(config.regionId ?? ''),
        fees: config.fees ?? {},
        subtotal: Number(config.subtotal ?? 0),
        discount: Number(config.discount ?? 0),
        method: String(config.method ?? ''),
        currency: String(config.currency ?? 'USD'),
        formError: '',
        addresses: Array.isArray(config.addresses) ? config.addresses : [],
        storageKey: 'aura.checkoutDraft',

        init() {
            if (config.hasServerDraft) {
                this.persistDraft();
            } else {
                this.restoreDraft();
            }

            const persist = () => this.persistDraft();
            const form = document.getElementById('checkout-form');
            form?.addEventListener('input', persist);
            form?.addEventListener('change', persist);
            document
                .querySelectorAll('[form="checkout-form"]')
                .forEach((field) => field.addEventListener('change', persist));

            document.getElementById('checkout-coupon-form')?.addEventListener('submit', () => {
                this.syncDraftIntoCouponForm();
                this.persistDraft();
            });
        },

        applyAddress(address) {
            const form = document.getElementById('checkout-form');
            if (!form || !address) {
                return;
            }

            const setValue = (name, next) => {
                const field = form.elements[name];
                if (!field) {
                    return;
                }
                field.value = next == null ? '' : String(next);
                this.clearFieldError(field);
            };

            setValue('shipping[full_name]', address.full_name);
            setValue('shipping[phone]', address.phone);
            setValue('shipping[line1]', address.line1);
            setValue('shipping[line2]', address.line2);
            setValue('shipping[city]', address.city);
            setValue('shipping[governorate]', address.governorate);
            this.persistDraft();
        },

        format(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: this.currency,
            }).format(amount);
        },

        get fee() {
            return Number(this.fees[this.regionId] ?? 0);
        },

        get total() {
            return this.subtotal - this.discount + this.fee;
        },

        readDraftFromDom() {
            const form = document.getElementById('checkout-form');
            if (!form) {
                return null;
            }

            const value = (name) => (form.elements[name]?.value ?? '').toString();

            return {
                shipping: {
                    full_name: value('shipping[full_name]'),
                    phone: value('shipping[phone]'),
                    line1: value('shipping[line1]'),
                    line2: value('shipping[line2]'),
                    city: value('shipping[city]'),
                    governorate: value('shipping[governorate]'),
                },
                payment_method: this.method || value('payment_method'),
                delivery_region_id: this.regionId || value('delivery_region_id'),
                customer_note: value('customer_note'),
            };
        },

        persistDraft() {
            const draft = this.readDraftFromDom();
            if (!draft) {
                return;
            }

            try {
                sessionStorage.setItem(this.storageKey, JSON.stringify(draft));
            } catch {
                // Ignore quota / private mode failures.
            }
        },

        restoreDraft() {
            let draft = null;
            try {
                draft = JSON.parse(sessionStorage.getItem(this.storageKey) || 'null');
            } catch {
                draft = null;
            }

            if (!draft || typeof draft !== 'object') {
                return;
            }

            const form = document.getElementById('checkout-form');
            if (!form) {
                return;
            }

            const setValue = (name, next) => {
                const field = form.elements[name];
                if (!field || next === undefined || next === null) {
                    return;
                }
                const current = (field.value ?? '').toString().trim();
                if (current !== '') {
                    return;
                }
                field.value = String(next);
            };

            setValue('shipping[full_name]', draft.shipping?.full_name);
            setValue('shipping[phone]', draft.shipping?.phone);
            setValue('shipping[line1]', draft.shipping?.line1);
            setValue('shipping[line2]', draft.shipping?.line2);
            setValue('shipping[city]', draft.shipping?.city);
            setValue('shipping[governorate]', draft.shipping?.governorate);
            setValue('customer_note', draft.customer_note);

            if (draft.payment_method) {
                this.method = String(draft.payment_method);
            }

            if (draft.delivery_region_id) {
                this.regionId = String(draft.delivery_region_id);
            }
        },

        syncDraftIntoCouponForm() {
            const couponForm = document.getElementById('checkout-coupon-form');
            const draft = this.readDraftFromDom();
            if (!couponForm || !draft) {
                return;
            }

            const ensureHidden = (name, value) => {
                let input = couponForm.querySelector(`input[name="${name}"]`);
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    couponForm.appendChild(input);
                }
                input.value = value ?? '';
            };

            ensureHidden('shipping[full_name]', draft.shipping.full_name);
            ensureHidden('shipping[phone]', draft.shipping.phone);
            ensureHidden('shipping[line1]', draft.shipping.line1);
            ensureHidden('shipping[line2]', draft.shipping.line2);
            ensureHidden('shipping[city]', draft.shipping.city);
            ensureHidden('shipping[governorate]', draft.shipping.governorate);
            ensureHidden('payment_method', draft.payment_method);
            ensureHidden('delivery_region_id', draft.delivery_region_id);
            ensureHidden('customer_note', draft.customer_note);
        },

        clearFieldError(field) {
            field.classList.remove('border-red-500', 'ring-1', 'ring-red-400');
            const wrap = field.closest('[data-field-wrap]') || field.closest('label') || field.parentElement;
            wrap?.querySelector('[data-field-error]')?.remove();
        },

        showFieldError(field, message) {
            field.classList.add('border-red-500', 'ring-1', 'ring-red-400');
            const wrap = field.closest('[data-field-wrap]') || field.closest('label') || field.parentElement;
            if (!wrap) {
                return;
            }

            wrap.querySelector('[data-field-error]')?.remove();
            const error = document.createElement('p');
            error.dataset.fieldError = '1';
            error.className = 'mt-1 text-sm text-red-700';
            error.textContent = message;
            wrap.appendChild(error);
        },

        fieldValue(field) {
            if (field.type === 'radio') {
                return Array.from(document.getElementsByName(field.name)).some((input) => input.checked);
            }

            if (field.type === 'checkbox') {
                return field.checked;
            }

            return (field.value ?? '').toString().trim() !== '';
        },

        validateBeforeSubmit(event) {
            this.formError = '';
            const form = event.target;
            const fields = [
                ...form.querySelectorAll('[data-required]'),
                ...document.querySelectorAll('[data-required][form="checkout-form"]'),
            ];
            const seen = new Set();

            for (const field of fields) {
                this.clearFieldError(field);

                if (field.type === 'radio') {
                    if (seen.has(field.name)) {
                        continue;
                    }
                    seen.add(field.name);
                }

                if (this.fieldValue(field)) {
                    continue;
                }

                event.preventDefault();
                const label = field.dataset.requiredLabel || field.name || 'This field';
                this.formError = `${label} is required before placing your order.`;
                this.showFieldError(field, `${label} is required.`);
                this.$nextTick(() => {
                    field.focus?.();
                    field.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
                });

                return false;
            }

            this.persistDraft();

            return true;
        },
    };
}

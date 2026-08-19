@props([
    'profitToday' => 0,
    'profitMonth' => 0,
])

@php
    $symbol = config('aura.currency_symbol', '$');
@endphp

<div
    class="mb-8 border border-beige bg-[#FFFCFA] p-4 sm:p-6"
    x-data="{
        price: '',
        cost: '',
        quantity: 1,
        discount: '',
        delivery: '',
        symbol: @js($symbol),
        n(value) {
            const number = Number(value);
            return Number.isFinite(number) ? number : 0;
        },
        money(value) {
            const amount = this.n(value);
            const formatted = amount.toFixed(2);
            return this.symbol === '$' ? ('$' + formatted) : (this.symbol + ' ' + formatted);
        },
        get qty() {
            return Math.max(0, this.n(this.quantity));
        },
        get revenue() {
            return Math.max(0, (this.n(this.price) * this.qty) - this.n(this.discount) + this.n(this.delivery));
        },
        get cogs() {
            return this.n(this.cost) * this.qty;
        },
        get profit() {
            return this.revenue - this.cogs;
        },
        get margin() {
            return this.revenue > 0 ? (this.profit / this.revenue) * 100 : 0;
        },
        get perUnit() {
            return this.qty > 0 ? this.profit / this.qty : 0;
        },
        reset() {
            this.price = '';
            this.cost = '';
            this.quantity = 1;
            this.discount = '';
            this.delivery = '';
        }
    }"
>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl">Profit calculator</h2>
            <p class="mt-1 text-sm text-taupe">Estimate profit from selling price, cost, quantity, discount, and delivery.</p>
        </div>
        <div class="flex flex-wrap gap-4 text-sm">
            <div>
                <div class="text-[10px] uppercase tracking-widest text-taupe">Profit today</div>
                <div class="font-display text-xl">{{ money($profitToday) }}</div>
            </div>
            <div>
                <div class="text-[10px] uppercase tracking-widest text-taupe">Profit this month</div>
                <div class="font-display text-xl">{{ money($profitMonth) }}</div>
            </div>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label class="label" for="profit-price">Selling price</label>
            <input id="profit-price" class="input" type="number" min="0" step="0.01" x-model="price" placeholder="0.00">
        </div>
        <div>
            <label class="label" for="profit-cost">Cost</label>
            <input id="profit-cost" class="input" type="number" min="0" step="0.01" x-model="cost" placeholder="0.00">
        </div>
        <div>
            <label class="label" for="profit-qty">Quantity</label>
            <input id="profit-qty" class="input" type="number" min="0" step="1" x-model="quantity">
        </div>
        <div>
            <label class="label" for="profit-discount">Discount</label>
            <input id="profit-discount" class="input" type="number" min="0" step="0.01" x-model="discount" placeholder="0.00">
        </div>
        <div>
            <label class="label" for="profit-delivery">Delivery fee</label>
            <input id="profit-delivery" class="input" type="number" min="0" step="0.01" x-model="delivery" placeholder="0.00">
        </div>
    </div>

    <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="border border-beige p-3">
            <div class="text-[10px] uppercase tracking-widest text-taupe">Revenue</div>
            <div class="mt-1 font-display text-2xl" x-text="money(revenue)"></div>
        </div>
        <div class="border border-beige p-3">
            <div class="text-[10px] uppercase tracking-widest text-taupe">Cost of goods</div>
            <div class="mt-1 font-display text-2xl" x-text="money(cogs)"></div>
        </div>
        <div class="border border-beige p-3">
            <div class="text-[10px] uppercase tracking-widest text-taupe">Profit</div>
            <div class="mt-1 font-display text-2xl" :class="profit >= 0 ? 'text-[#2F5D32]' : 'text-[#8B3A3A]'" x-text="money(profit)"></div>
        </div>
        <div class="border border-beige p-3">
            <div class="text-[10px] uppercase tracking-widest text-taupe">Margin</div>
            <div class="mt-1 font-display text-2xl" x-text="margin.toFixed(1) + '%'"></div>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-taupe">
        <p>Profit per unit: <span class="text-charcoal" x-text="money(perUnit)"></span></p>
        <button type="button" class="btn btn-secondary btn-sm" @click="reset()">Clear</button>
    </div>
</div>

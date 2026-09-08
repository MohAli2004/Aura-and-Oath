<form method="GET" class="space-y-4">
    <div>
        <label class="label" for="shop-q">{{ __('storefront.search') }}</label>
        <input id="shop-q" class="input" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('storefront.search_placeholder') }}">
    </div>
    <div>
        <label class="label" for="shop-category">{{ __('storefront.categories') }}</label>
        <select id="shop-category" name="category" class="input">
            <option value="">{{ __('storefront.all') }}</option>
            @foreach($categories as $category)
                <option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->localized('name') }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label" for="shop-brand">{{ __('storefront.brands') }}</label>
        <select id="shop-brand" name="brand" class="input">
            <option value="">{{ __('storefront.all') }}</option>
            @foreach($brands as $brand)
                <option value="{{ $brand->slug }}" @selected(($filters['brand'] ?? '') === $brand->slug)>{{ $brand->localized('name') }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label" for="shop-gender">{{ __('storefront.by_gender') }}</label>
        <select id="shop-gender" name="gender" class="input">
            <option value="">{{ __('storefront.all') }}</option>
            <option value="women" @selected(($filters['gender'] ?? '') === 'women')>{{ __('storefront.women') }}</option>
            <option value="men" @selected(($filters['gender'] ?? '') === 'men')>{{ __('storefront.men') }}</option>
            <option value="unisex" @selected(($filters['gender'] ?? '') === 'unisex')>{{ __('storefront.unisex') }}</option>
        </select>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="label" for="shop-min">{{ __('storefront.min') }}</label>
            <input id="shop-min" class="input" type="number" name="min" value="{{ $filters['min'] ?? '' }}" min="0" step="0.01">
        </div>
        <div>
            <label class="label" for="shop-max">{{ __('storefront.max') }}</label>
            <input id="shop-max" class="input" type="number" name="max" value="{{ $filters['max'] ?? '' }}" min="0" step="0.01">
        </div>
    </div>
    <div>
        <label class="label" for="shop-sort">{{ __('storefront.sort') }}</label>
        <select id="shop-sort" name="sort" class="input">
            @foreach(['newest' => __('storefront.sort_newest'), 'price_asc' => __('storefront.sort_price_asc'), 'price_desc' => __('storefront.sort_price_desc'), 'name' => __('storefront.sort_name'), 'featured' => __('storefront.sort_featured')] as $k => $v)
                <option value="{{ $k }}" @selected(($filters['sort'] ?? 'newest') === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-primary w-full min-h-11" type="submit">{{ __('storefront.apply') }}</button>
    <a href="{{ route('shop') }}" class="btn btn-secondary w-full min-h-11">{{ __('storefront.clear_all') }}</a>
</form>

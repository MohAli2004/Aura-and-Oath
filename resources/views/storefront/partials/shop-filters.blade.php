<form method="GET" class="space-y-4">
    <div>
        <label class="label" for="shop-q">Search</label>
        <input id="shop-q" class="input" type="search" name="q" value="{{ $filters['q'] ?? '' }}">
    </div>
    <div>
        <label class="label" for="shop-category">Category</label>
        <select id="shop-category" name="category" class="input">
            <option value="">All</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->slug }}" @selected(($filters['category'] ?? '') === $cat->slug)>{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label" for="shop-brand">Brand</label>
        <select id="shop-brand" name="brand" class="input">
            <option value="">All</option>
            @foreach($brands as $brand)
                <option value="{{ $brand->slug }}" @selected(($filters['brand'] ?? '') === $brand->slug)>{{ $brand->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="label" for="shop-gender">Gender</label>
        <select id="shop-gender" name="gender" class="input">
            <option value="">All</option>
            @foreach(\App\Enums\ProductGender::cases() as $gender)
                <option value="{{ $gender->value }}" @selected(($filters['gender'] ?? '') === $gender->value)>{{ $gender->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="grid grid-cols-2 gap-2">
        <div>
            <label class="label" for="shop-min">Min</label>
            <input id="shop-min" class="input" type="number" name="min_price" value="{{ $filters['min_price'] ?? '' }}">
        </div>
        <div>
            <label class="label" for="shop-max">Max</label>
            <input id="shop-max" class="input" type="number" name="max_price" value="{{ $filters['max_price'] ?? '' }}">
        </div>
    </div>
    <div>
        <label class="label" for="shop-sort">Sort</label>
        <select id="shop-sort" name="sort" class="input">
            @foreach(['newest'=>'Newest','price_asc'=>'Price ↑','price_desc'=>'Price ↓','name'=>'Name','featured'=>'Featured'] as $k=>$v)
                <option value="{{ $k }}" @selected(($filters['sort'] ?? '') === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <button class="btn btn-primary w-full min-h-11" type="submit">Apply</button>
    <a href="{{ route('shop') }}" class="btn btn-secondary w-full min-h-11">Clear all</a>
</form>

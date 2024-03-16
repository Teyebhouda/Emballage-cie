<ul class="child-categories" data-category-id="{{ $category->id }}" style="display: none; padding-top: {{ $padding }}px;">
    @foreach($children as $childCategory)
        @php
            $childProductsCount = \App\Models\ProductCategory::where('category_id', $childCategory->id)->count();
        @endphp
        <li class="category-item" data-category-id="{{ $childCategory->id }}">
            <div class="toggle-wrapper">
                <a href="javascript:void(0);" class="d-flex align-items-center toggle-category" data-category-id="{{ $childCategory->id }}">
                    @if($childCategory->childrenCategories->isNotEmpty())
                        <i class="toggle-icon ms-1">▼</i>
                    @else
                        <i class="toggle-icon" style="visibility: hidden;">▼</i>
                    @endif
                    <span class="category-name ms-2">{{ $childCategory->collectLocalization('name') }}</span>
                    <!-- <span class="fw-bold fs-xs total-count ms-auto">{{ $childProductsCount }}</span> -->
                </a>
                @if($childCategory->childrenCategories->isNotEmpty() && $padding < 150) <!-- Limit recursion depth to 150 -->
                    @include('frontend.default.pages.products.inc.child_categories', ['children' => $childCategory->childrenCategories, 'padding' => $padding + 15])
                @endif
            </div>
        </li>
    @endforeach
</ul>

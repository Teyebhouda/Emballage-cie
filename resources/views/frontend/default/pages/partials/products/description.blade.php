<div class="product-info-tab bg-white rounded-2 overflow-hidden pt-6 mt-4">
    <ul class="nav nav-tabs border-bottom justify-content-center gap-5 pt-info-tab-nav">
        <li><a href="#description" class="active" data-bs-toggle="tab">{{ localize('Description') }}</a></li>
        <li><a href="#info" data-bs-toggle="tab">{{ localize('Informations complémentaires') }}</a></li>
        <li><a href="#ficheTechnique" data-bs-toggle="tab">{{ localize('Fiche technique') }}</a></li>
    </ul>

    
    <div class="tab-content">
        <div class="tab-pane fade show active px-4 py-5" id="description">
            @if ($product->description)
                {!! $product->collectLocalization('description') !!}
            @else
                <div class="text-dark text-center border py-2">{{ localize('Non disponible') }}</div>
            @endif
        </div>

        <div class="tab-pane fade px-4 py-5" id="info">
            <table class="w-100 product-info-table">
                @forelse (generateVariationOptions($product->variation_combinations) as $variation)
                    <tr>
                        <td class="text-dark fw-semibold">{{ $variation['name'] }}</td>
                        <td>
                            @foreach ($variation['values'] as $value)
                                {{ $value['name'] }}@if (!$loop->last)
                                    ,
                                @endif
                            @endforeach
                        </td>
                    </tr>
                    @empty
                        <tr>
                            <td class="text-dark text-center" colspan="2">{{ localize('Non disponible') }}
                            </td>
                        </tr>
                    @endforelse
                </table>
            </div>

        </div>
        
        <div class="tab-pane fade px-4 py-5" id="ficheTechnique">
    <div class="thumbnail position-relative text-center p-4">
        @if ($product->fiche_technique)
            <a href="{{ asset('storage/' . $product->fiche_technique) }}" target="_blank" class="btn btn-primary">
    <i class="fas fa-file-pdf"></i> {{ localize('Consulter') }}</a>
        @else
            <div class="text-dark text-center border py-2">{{ localize('Non disponible') }}</div>
        @endif
    </div>
</div>


        
    </div>
    </div>

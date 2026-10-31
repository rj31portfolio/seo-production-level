@php($brand = $report->snapshot['branding'] ?? ['name' => $report->snapshot['agency'], 'accent_color' => '#ea580c'])
<table class="report-brand"><tbody><tr>
<td class="brand-identity">
@if(!empty($brand['logo_data']))<img class="brand-logo" src="{{ $brand['logo_data'] }}" alt="{{ $brand['name'] }} logo">@endif
<div class="brand-name">{{ $brand['name'] }}</div>
@if(!empty($brand['tagline']))<div class="brand-tagline">{{ $brand['tagline'] }}</div>@endif
</td>
<td class="brand-contact">
@if(!empty($brand['address']))<div class="brand-address">{{ $brand['address'] }}</div>@endif
@foreach(['email', 'phone', 'website'] as $field)@if(!empty($brand[$field]))<div>{{ $brand[$field] }}</div>@endif @endforeach
</td>
</tr></tbody></table>

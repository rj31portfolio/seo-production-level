@if(is_array($value))
    @if($value === [])
        <span class="text-gray-400">None collected</span>
    @elseif(array_is_list($value))
        <ul class="space-y-2">
            @foreach($value as $item)
                <li>@include('seo.data-value', ['value' => $item])</li>
            @endforeach
        </ul>
    @else
        <dl class="space-y-2">
            @foreach($value as $field => $item)
                <div>
                    <dt class="font-medium">{{ ucwords(str_replace('_', ' ', $field)) }}</dt>
                    <dd class="mt-1">@include('seo.data-value', ['value' => $item])</dd>
                </div>
            @endforeach
        </dl>
    @endif
@elseif($value === null)
    <span class="text-gray-400">Not available</span>
@elseif(is_bool($value))
    {{ $value ? 'Yes' : 'No' }}
@elseif($value === '')
    <span class="text-gray-400">Empty</span>
@else
    {{ $value }}
@endif

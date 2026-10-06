@foreach($result->data as $field => $value)
    @continue(in_array($field, ['metrics', 'score', 'error', 'recommendations', 'keywords', 'checks', 'notes'], true))
    <section class="mt-6 min-w-0">
        <h3 class="mb-3 text-sm font-semibold">{{ ucwords(str_replace('_', ' ', $field)) }}</h3>
        @if(is_array($value) && array_is_list($value) && $value !== [] && is_array($value[0]))
            @php
                $columns = [];
                foreach ($value as $row) {
                    foreach (array_keys($row) as $column) {
                        $columns[$column] = true;
                    }
                }
            @endphp
            <div class="max-w-full overflow-x-auto rounded-lg border border-gray-100">
                <table class="w-full">
                    <thead><tr>
                        @foreach(array_keys($columns) as $column)
                            <th class="table-th">{{ ucwords(str_replace('_', ' ', $column)) }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @foreach($value as $row)
                            <tr>
                                @foreach(array_keys($columns) as $column)
                                    <td class="table-td min-w-36 max-w-md break-words align-top [overflow-wrap:anywhere]">@include('seo.data-value', ['value' => $row[$column] ?? null])</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="break-words rounded-lg bg-gray-50 p-4 text-sm text-gray-700 [overflow-wrap:anywhere]">@include('seo.data-value', ['value' => $value])</div>
        @endif
    </section>
@endforeach

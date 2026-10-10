@if($recommendations)
<section class="generated-recommendations">
    <h2 class="mb-4 text-lg font-semibold">{{ $isAi ? 'AI recommendations' : 'Recommendations' }}</h2>
    @if($isAi)<p class="mb-5 text-sm text-gray-500">Review these suggestions before creating tasks or applying changes.</p>@endif
    <div class="space-y-5">
        @foreach($recommendations as $recommendation)
        <article class="rounded-xl border border-gray-200 bg-gray-50 p-5">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-orange-600">Recommendation {{ $loop->iteration }}</p>
            <div class="generated-content">{!! \Illuminate\Support\Str::markdown($recommendation, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
        </article>
        @endforeach
    </div>
</section>
@endif

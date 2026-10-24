@php($elements = $elements ?? $paginator->elements())
@php($pasakFreshSlug = $pasakDashboardSlug ?? null)
@php($pasakFreshPageUrl = function ($url) use ($pasakFreshSlug) {
    // Mode URL berputar: paginator memakai path request (token lama).
    // Tukar ke token aktif agar link halaman langsung tepat sasaran.
    // Mode statis ($pasakFreshSlug kosong): URL dibiarkan apa adanya.
    if (! is_string($url) || $url === '' || ! is_string($pasakFreshSlug) || $pasakFreshSlug === '') {
        return $url;
    }
    $fixed = preg_replace('#/pasak-[A-Za-z0-9]{12,32}(?=\/|$)#', '/'.$pasakFreshSlug, $url, 1);
    return is_string($fixed) ? $fixed : $url;
})
@if ($paginator->hasPages())
    <nav class="sec-pagination" aria-label="Pagination">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span class="sec-page-btn disabled" aria-disabled="true" title="{{ __('Previous page') }}">‹</span>
        @else
            <a href="{{ $pasakFreshPageUrl($paginator->previousPageUrl()) }}" class="sec-page-btn" rel="prev" title="{{ __('Previous page') }}">‹</a>
        @endif

        {{-- Pagination Elements --}}
        @foreach ($elements as $element)
            {{-- "Three Dots" Separator --}}
            @if (is_string($element))
                <span class="sec-page-ellipsis">{{ $element }}</span>
            @endif

            {{-- Array Of Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="sec-page-btn active" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $pasakFreshPageUrl($url) }}" class="sec-page-btn">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $pasakFreshPageUrl($paginator->nextPageUrl()) }}" class="sec-page-btn" rel="next" title="{{ __('Next page') }}">›</a>
        @else
            <span class="sec-page-btn disabled" aria-disabled="true" title="{{ __('Next page') }}">›</span>
        @endif
    </nav>
@endif

@if ($paginator->hasPages())
  <nav class="pager" role="navigation" aria-label="ページネーション">
    <div class="pager__summary">
      {{ number_format($paginator->firstItem()) }}-{{ number_format($paginator->lastItem()) }} / {{ number_format($paginator->total()) }}件
    </div>
    <div class="pager__links">
      @if ($paginator->onFirstPage())
        <span class="pager__button is-disabled" aria-disabled="true" aria-label="前のページ">
          <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
        </span>
      @else
        <a class="pager__button" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="前のページ">
          <span class="material-symbols-outlined" aria-hidden="true">chevron_left</span>
        </a>
      @endif

      @foreach ($elements as $element)
        @if (is_string($element))
          <span class="pager__dots" aria-hidden="true">{{ $element }}</span>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <span class="pager__button is-active" aria-current="page">{{ $page }}</span>
            @else
              <a class="pager__button" href="{{ $url }}">{{ $page }}</a>
            @endif
          @endforeach
        @endif
      @endforeach

      @if ($paginator->hasMorePages())
        <a class="pager__button" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="次のページ">
          <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        </a>
      @else
        <span class="pager__button is-disabled" aria-disabled="true" aria-label="次のページ">
          <span class="material-symbols-outlined" aria-hidden="true">chevron_right</span>
        </span>
      @endif
    </div>
  </nav>
@endif

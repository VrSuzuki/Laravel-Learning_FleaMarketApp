<article class="content-card" @if(!empty($draggable)) draggable="true" data-content-id="{{ $content->id }}" @endif>
  <a class="content-card__image" href="{{ route('contents.show', $content) }}">
    <img src="{{ $content->thumbnail_url }}" alt="{{ $content->title }}">
  </a>
  <div class="content-card__body">
    <div class="pill-row">
      <span class="pill">{{ $content->subGenre->name ?? 'デジタルデータ' }}</span>
      <span class="pill pill--coral">{{ $content->genre->name ?? 'ジャンル' }}</span>
    </div>
    <h3><a href="{{ route('contents.show', $content) }}">{{ $content->title }}</a></h3>
    <div class="meta-row">
      <img class="avatar-sm" src="{{ $content->author->avatar_url }}" alt="">
      <a href="{{ route('profiles.show', $content->author) }}">{{ $content->author->display_name }}</a>
    </div>
    @php
      $favoriteCount = $content->favorites_count ?? $content->favorites()->count();
      $isFavorited = auth()->check()
          ? $content->favorites()->where('user_id', auth()->id())->exists()
          : false;
    @endphp
    <div class="content-card__footer">
      <span class="price">{{ $content->formatted_price }}</span>
      <span class="rating">{{ $content->rating_label }} / {{ number_format($content->ratings_count) }}件</span>
      @auth
        <form method="POST" action="{{ route('favorites.toggle', $content) }}">
          @csrf
          <button class="favorite-pill {{ $isFavorited ? 'is-active' : '' }}" type="submit" aria-label="お気に入り">
            <span class="material-symbols-outlined" aria-hidden="true">{{ $isFavorited ? 'favorite' : 'favorite_border' }}</span>
            {{ number_format($favoriteCount) }}
          </button>
        </form>
      @else
        <a class="favorite-pill" href="{{ route('login') }}" aria-label="お気に入り">
          <span class="material-symbols-outlined" aria-hidden="true">favorite_border</span>
          {{ number_format($favoriteCount) }}
        </a>
      @endauth
    </div>
    @auth
      @if(!empty($editable) && $content->user_id === auth()->id())
        <div class="inline-actions" style="margin-top: 10px;">
          <a class="icon-button" href="{{ route('contents.edit', $content) }}" aria-label="編集">
            <span class="material-symbols-outlined" aria-hidden="true">edit</span>
          </a>
        </div>
      @endif
    @endauth
  </div>
</article>

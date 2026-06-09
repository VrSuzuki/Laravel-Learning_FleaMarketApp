@extends('layouts.base')

@section('title', $user->display_name.' | DigitalAssetPort')

@section('body')
  @include('layouts.header')

  <main class="app-main">
    @include('partials.flash')

    <section class="panel profile-head">
      <img class="profile-head__avatar" src="{{ $user->avatar_url }}" alt="">
      <div>
        <h1 class="section-title">{{ $user->display_name }}</h1>
        <p style="color: var(--muted);">{{ '@'.($user->handle ?: 'user') }}</p>
        <div class="stat-row">
          @if($user->show_following_count || $isOwner)
            <a href="{{ route('profiles.following', $user) }}">フォロー数 {{ number_format($user->following()->count()) }}</a>
          @endif
          @if($user->show_follower_count || $isOwner)
            <a href="{{ route('profiles.followers', $user) }}">フォロワー数 {{ number_format($user->followers()->count()) }}</a>
          @endif
          <span>投稿 {{ number_format($user->contents()->count()) }}</span>
        </div>
        <p style="margin-top: 14px; white-space: pre-wrap;">{{ $user->bio ?: '自己紹介文はまだありません。' }}</p>
      </div>
      <div class="form-actions">
        @if($isOwner)
          <a class="button button--primary" href="{{ route('profiles.edit') }}">プロフィールを編集</a>
        @elseif(auth()->check())
          <form method="POST" action="{{ route('follows.toggle', $user) }}">
            @csrf
            <button class="button {{ $isFollowing ? 'button--ghost' : 'button--primary' }}" type="submit">
              {{ $isFollowing ? 'フォロー中' : 'フォロー' }}
            </button>
          </form>
        @else
          <a class="button button--ghost" href="{{ route('login') }}">ログインしてフォロー</a>
        @endif
      </div>
    </section>

    <section class="section">
      <div class="section-heading">
        <div>
          <p class="section-eyebrow">Portfolio</p>
          <h2 class="section-title">投稿したコンテンツ</h2>
        </div>
        @if($isOwner)
          <a class="button button--primary" href="{{ route('contents.create') }}">アップロード</a>
        @endif
      </div>

      @if($contents->count())
        <div class="content-grid" id="profileContentGrid">
          @foreach($contents as $content)
            @include('partials.content-card', [
              'content' => $content,
              'editable' => $isOwner,
              'draggable' => $isOwner,
            ])
          @endforeach
        </div>
        <div class="pagination">{{ $contents->links() }}</div>
      @else
        <div class="empty-state">投稿されたコンテンツはありません。</div>
      @endif
    </section>
  </main>

  @include('layouts.footer')

  @if($isOwner)
    @push('scripts')
      <script>
        const grid = document.getElementById('profileContentGrid');
        if (grid) {
          let dragged = null;
          grid.addEventListener('dragstart', event => {
            dragged = event.target.closest('[data-content-id]');
          });
          grid.addEventListener('dragover', event => {
            event.preventDefault();
            const target = event.target.closest('[data-content-id]');
            if (!dragged || !target || dragged === target) return;
            const box = target.getBoundingClientRect();
            const after = event.clientY > box.top + box.height / 2;
            grid.insertBefore(dragged, after ? target.nextSibling : target);
          });
          grid.addEventListener('drop', () => {
            const order = [...grid.querySelectorAll('[data-content-id]')].map(node => node.dataset.contentId);
            fetch('{{ route('contents.reorder') }}', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
              },
              body: JSON.stringify({ order })
            });
          });
        }
      </script>
    @endpush
  @endif
@endsection

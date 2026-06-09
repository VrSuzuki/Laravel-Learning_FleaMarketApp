@php
  $homeUrl = Route::has('home') ? route('home') : url('/');
  $activeCart = auth()->check()
      ? \App\Models\Cart::withCount('items')->where('user_id', auth()->id())->where('active', true)->first()
      : null;
  $cartCount = $activeCart ? $activeCart->items_count : 0;
  $unreadNotifications = auth()->check()
      ? auth()->user()->appNotifications()->whereNull('read_at')->count()
      : 0;
  $latestNotifications = auth()->check()
      ? auth()->user()->appNotifications()->with('actor')->latest()->take(8)->get()
      : collect();
@endphp

<header class="site-header">
  <div class="site-header__inner">
    <a class="brand" href="{{ $homeUrl }}" aria-label="DigitalAssetPortトップへ">
      <img class="brand__logo" src="{{ asset('assets/dap-logo.svg') }}" alt="">
      <span class="brand__name">DigitalAssetPort</span>
    </a>

    <div class="site-header__left">
      <form class="site-header__search" action="{{ $homeUrl }}" method="GET">
        <input
          class="site-header__search-input"
          type="search"
          name="keyword"
          value="{{ request('keyword') }}"
          placeholder="キーワードを入力"
          aria-label="コンテンツを検索"
        >
        <button class="icon-button icon-button--primary" type="submit" aria-label="検索">
          <span class="material-symbols-outlined" aria-hidden="true">search</span>
        </button>
      </form>
      <a class="button button--advanced" href="{{ route('search.advanced') }}">詳細検索</a>
    </div>

    <nav class="site-header__nav" aria-label="主要メニュー">
      @guest
        <a class="nav-link" href="{{ route('about') }}">DigitalAssetPortとは</a>
        <a class="button button--ghost" href="{{ route('login') }}">ログイン</a>
      @else
        <a class="button button--primary" href="{{ route('contents.create') }}">
          <span class="material-symbols-outlined" aria-hidden="true">upload</span>
          アップロード
        </a>

        <details class="header-menu">
          <summary class="icon-button" aria-label="通知">
            <span class="material-symbols-outlined" aria-hidden="true">notifications</span>
            @if($unreadNotifications > 0)
              <span class="badge">{{ $unreadNotifications }}</span>
            @endif
          </summary>
          <div class="header-menu__panel header-menu__panel--wide">
            <h2 class="menu-title">通知</h2>
            @forelse($latestNotifications as $notification)
              <a class="notice-mini" href="{{ $notification->url ?: route('notifications.index') }}">
                <img src="{{ optional($notification->actor)->avatar_url ?: auth()->user()->avatar_url }}" alt="">
                <span>{{ $notification->message }}</span>
              </a>
            @empty
              <p class="empty-mini">新しい通知はありません。</p>
            @endforelse
            <a class="menu-link menu-link--strong" href="{{ route('notifications.index') }}">すべての通知→</a>
          </div>
        </details>

        <a class="icon-button" href="{{ route('cart.index') }}" aria-label="カート">
          <span class="material-symbols-outlined" aria-hidden="true">shopping_cart</span>
          @if($cartCount > 0)
            <span class="badge">{{ $cartCount }}</span>
          @endif
        </a>

        <details class="header-menu">
          <summary class="account-button" aria-label="アカウントメニュー">
            <img src="{{ auth()->user()->avatar_url }}" alt="">
          </summary>
          <div class="header-menu__panel">
            <a class="menu-link" href="{{ route('profiles.show', auth()->user()) }}">プロフィール</a>
            <a class="menu-link" href="{{ route('following.index') }}">フォロー</a>
            <a class="menu-link" href="{{ route('favorites.index') }}">お気に入り</a>
            <a class="menu-link" href="{{ route('library.index') }}">ライブラリ</a>
            <a class="menu-link" href="{{ route('purchases.index') }}">購入履歴</a>
            <a class="menu-link" href="{{ route('sales.index') }}">売上管理</a>
            <a class="menu-link" href="{{ route('settings.index') }}">設定</a>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button class="menu-link menu-link--button" type="submit">ログアウト</button>
            </form>
          </div>
        </details>
      @endguest
    </nav>
  </div>
</header>

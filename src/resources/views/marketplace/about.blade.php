@extends('layouts.base')

@section('title', 'DigitalAssetPortとは')
@section('body_class', 'page-about')

@section('body')
  @include('layouts.header')

  <main>
    <section class="hero hero--image" aria-labelledby="about-title">
      <div class="hero__content">
        <p class="eyebrow">DigitalAssetPort</p>
        <h1 id="about-title" class="about-hero-title">
          <span>どんなデータでも置ける</span>
          <span>総合マーケットプレイス</span>
        </h1>
        <p>作業を速くするテンプレート、学びを深める教材、AIをより使いやすくするプロンプト、無料フォントをまとめたリスト、すぐ試せるシステム。PCを利用する様々な人々が、ファイルとして渡せるものを持ち寄るマーケットです。</p>
      </div>
    </section>

    <section class="about-band">
      <div class="wide-inner">
        <div class="section-heading">
          <div>
            <p class="section-eyebrow">For everyone with files</p>
            <h2 class="section-title">作る人にも、使う人にも開かれたポート。</h2>
          </div>
        </div>
        <p style="max-width: 780px; color: var(--muted);">
          DigitalAssetPortは、物品ではなくデジタルコンテンツを中心にしたフリマ型プラットフォームです。
          Zipでまとめられる資料やツール、テンプレート、動画付き演習セットなどを登録し、無料配布または販売できます。
          投稿者プロフィール、フォロー、コメント、通知、ライブラリを通して、継続して使える関係性を残せます。
        </p>
      </div>
    </section>

    <section class="feature-band">
      <div class="wide-inner">
        <div class="section-heading">
          <div>
            <p class="section-eyebrow">Genres</p>
            <h2 class="section-title">どんなジャンルのデジタルコンテンツでも。</h2>
          </div>
        </div>

        <div class="feature-grid">
          @foreach($genres as $genre)
            <article class="feature">
              <span class="material-symbols-outlined" aria-hidden="true">
                @switch($genre->slug)
                  @case('business-office') table_chart @break
                  @case('manufacturing') precision_manufacturing @break
                  @case('daily-life') home_health @break
                  @case('code-system') code_blocks @break
                  @case('education') school @break
                  @case('stock-photo') photo_library @break
                  @case('illustration-comic') draw @break
                  @case('audio-music') graphic_eq @break
                  @case('video-motion') movie @break
                  @case('three-d-vr') deployed_code @break
                  @case('game-assets') sports_esports @break
                  @case('fonts-typography') text_fields @break
                  @case('prompts-ai') smart_toy @break
                  @case('datasets') database @break
                  @case('web-themes') web_asset @break
                  @case('mobile-assets') smartphone @break
                  @case('wordpress-cms') language @break
                  @case('ebooks-zines') menu_book @break
                  @case('presentations') slideshow @break
                  @case('spreadsheets') table_chart @break
                  @case('legal-contracts') gavel @break
                  @case('accounting-tax') receipt_long @break
                  @case('marketing') campaign @break
                  @case('stores-events') store @break
                  @case('cad-blueprints') architecture @break
                  @case('healthcare') health_and_safety @break
                  @case('research-papers') science @break
                  @case('language-translation') translate @break
                  @case('hobby-life') interests @break
                  @default palette
                @endswitch
              </span>
              <h3>{{ $genre->name }}</h3>
              <p>{{ $genre->description }}</p>
            </article>
          @endforeach
        </div>
      </div>
    </section>

    <section class="about-band">
      <div class="wide-inner">
        <div class="section-heading">
          <div>
            <p class="section-eyebrow">Functions</p>
            <h2 class="section-title">DigitalAssetPortでできること。</h2>
          </div>
        </div>

        <div class="feature-grid">
          <article class="feature">
            <span class="material-symbols-outlined" aria-hidden="true">inventory_2</span>
            <h3>投稿と販売</h3>
            <p>ジャンル、タグ、価格、ファイルを登録し、購入者へ届ける導線を作れます。</p>
          </article>
          <article class="feature">
            <span class="material-symbols-outlined" aria-hidden="true">library_books</span>
            <h3>ライブラリ管理</h3>
            <p>購入済み、無料追加済みのコンテンツを一覧化し、いつでもダウンロードできます。</p>
          </article>
          <article class="feature">
            <span class="material-symbols-outlined" aria-hidden="true">notifications</span>
            <h3>反応の通知</h3>
            <p>コメントやフォローの通知で、投稿者と利用者のつながりを残せます。</p>
          </article>
        </div>

        <div class="hero" style="min-height: 240px; margin-top: 36px;">
          <div class="hero__content">
            <h2 class="section-title">あなたのデータを、次の誰かのためへ。</h2>
            <div class="hero__actions">
              @auth
                <a class="button button--primary" href="{{ route('contents.create') }}">コンテンツを投稿する</a>
              @else
                <a class="button button--primary" href="{{ route('register') }}">アカウント登録へ</a>
              @endauth
            </div>
          </div>
        </div>
      </div>
    </section>
  </main>

  @include('layouts.footer')
@endsection

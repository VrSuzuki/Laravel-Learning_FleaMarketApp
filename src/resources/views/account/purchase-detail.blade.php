@extends('layouts.base')

@section('title', '購入履歴詳細 | DigitalAssetPort')

@section('body')
  @include('layouts.header')

  <main class="app-main">
    @include('partials.flash')
    <section class="panel">
      <h1 class="section-title">購入履歴詳細</h1>
      <div class="spec-list">
        <div><span>注文番号</span><strong>{{ $order->order_number }}</strong></div>
        <div><span>購入日時</span><strong>{{ optional($order->purchased_at)->format('Y/m/d H:i') }}</strong></div>
        <div><span>注文金額</span><strong>{{ $order->formatted_total }}</strong></div>
      </div>
    </section>

    <section class="section record-list">
      @foreach($order->items as $item)
        <article class="record">
          <img src="{{ $item->content->thumbnail_url }}" alt="{{ $item->content->title }}">
          <div>
            <h2><a href="{{ route('contents.show', $item->content) }}">{{ $item->content->title }}</a></h2>
            <p style="color: var(--muted);">
              <a href="{{ route('profiles.show', $item->content->author) }}">{{ $item->content->author->display_name }}</a>
            </p>
          </div>
          <a class="button button--primary" href="{{ route('downloads.show', $item->content) }}">コンテンツをダウンロード</a>
        </article>
      @endforeach
    </section>

    <div class="form-actions" style="margin-top: 18px;">
      <a class="button button--ghost" href="{{ route('purchases.index') }}">戻る</a>
    </div>
  </main>

  @include('layouts.footer')
@endsection

@extends('layouts.base')

@section('title', 'メール認証 | DigitalAssetPort')

@section('body')
  @include('layouts.header')

  <main class="auth-main">
    <section class="auth-card">
      <h1>認証メール送付済み</h1>
      @include('partials.flash')
      <p class="auth-lead">認証メールを送りました。メール内のリンクからメール認証を確認してください。</p>
      <form method="POST" action="{{ route('verification.send') }}" style="margin-top: 18px;">
        @csrf
        <button class="button button--primary" type="submit">認証メールを再送する</button>
      </form>
    </section>
  </main>
@endsection

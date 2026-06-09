<article class="panel">
  <div class="meta-row" style="margin-top: 0;">
    <img class="avatar-sm" src="{{ $user->avatar_url }}" alt="">
    <div>
      <h3><a href="{{ route('profiles.show', $user) }}">{{ $user->display_name }}</a></h3>
      <p style="color: var(--muted); margin: 0;">{{ '@'.$user->handle }}</p>
    </div>
  </div>
</article>

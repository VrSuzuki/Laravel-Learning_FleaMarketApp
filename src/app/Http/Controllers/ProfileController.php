<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;

class ProfileController extends Controller
{
    public function show(User $user)
    {
        $contents = $user->contents()
            ->published()
            ->with(['author', 'genre', 'subGenre'])
            ->withCount(['favorites', 'comments'])
            ->paginate(20);

        $isOwner = auth()->check() && auth()->id() === $user->id;
        $isFollowing = auth()->check()
            ? auth()->user()->following()->where('users.id', $user->id)->exists()
            : false;

        return view('profiles.show', compact('user', 'contents', 'isOwner', 'isFollowing'));
    }

    public function following(User $user)
    {
        $this->authorizeFollowVisibility($user, 'show_following_count');

        return view('account.users', [
            'users' => $user->following()->paginate(20),
            'title' => $user->display_name.'さんのフォローリスト',
            'switchLabel' => 'フォロワーリストへ',
            'switchRoute' => route('profiles.followers', $user),
        ]);
    }

    public function followers(User $user)
    {
        $this->authorizeFollowVisibility($user, 'show_follower_count');

        return view('account.users', [
            'users' => $user->followers()->paginate(20),
            'title' => $user->display_name.'さんのフォロワーリスト',
            'switchLabel' => 'フォローリストへ',
            'switchRoute' => route('profiles.following', $user),
        ]);
    }

    public function edit()
    {
        return view('profiles.edit', ['user' => auth()->user()]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $data = $request->only(['nickname', 'bio']);

        if ($request->hasFile('avatar')) {
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $request->user()->update($data);

        return redirect()->route('profiles.show', $request->user())->with('status', 'プロフィールを更新しました。');
    }

    private function authorizeFollowVisibility(User $user, string $flag)
    {
        $isOwner = auth()->check() && auth()->id() === $user->id;

        abort_unless($isOwner || $user->{$flag}, 403);
    }
}

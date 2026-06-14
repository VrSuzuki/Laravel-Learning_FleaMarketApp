<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'handle',
        'name',
        'nickname',
        'email',
        'google_id',
        'google_avatar_url',
        'avatar_path',
        'bio',
        'password',
        'notifications_enabled',
        'show_following_count',
        'show_follower_count',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'notifications_enabled' => 'boolean',
        'show_following_count' => 'boolean',
        'show_follower_count' => 'boolean',
    ];

    public function contents()
    {
        return $this->hasMany(Content::class)->orderBy('profile_order')->latest();
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function favoriteContents()
    {
        return $this->belongsToMany(Content::class, 'favorites')->withTimestamps();
    }

    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->withTimestamps();
    }

    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')->withTimestamps();
    }

    public function libraryContents()
    {
        return $this->belongsToMany(Content::class, 'library_items')->withTimestamps();
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function getDisplayNameAttribute()
    {
        return $this->nickname ?: $this->name;
    }

    public function getAvatarUrlAttribute()
    {
        if ($this->avatar_path) {
            if (Str::startsWith($this->avatar_path, ['http://', 'https://'])) {
                return $this->avatar_path;
            }

            if (Str::startsWith($this->avatar_path, ['assets/', '/assets/'])) {
                return asset(ltrim($this->avatar_path, '/'));
            }

            return asset('storage/'.$this->avatar_path);
        }

        if ($this->google_avatar_url) {
            return $this->google_avatar_url;
        }

        $seed = $this->handle ?: $this->email ?: (string) $this->id;
        $index = (abs(crc32($seed)) % 6) + 1;

        return asset('assets/avatars/avatar-'.$index.'.svg');
    }

    public function getRouteKeyName()
    {
        return 'handle';
    }
}

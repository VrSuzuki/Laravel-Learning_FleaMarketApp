<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountSettingsRequest;
use App\Models\CartItem;
use App\Models\Content;
use App\Models\LibraryItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function settings()
    {
        return view('account.settings', ['user' => auth()->user()]);
    }

    public function updateSettings(AccountSettingsRequest $request)
    {
        $request->user()->update([
            'notifications_enabled' => $request->boolean('notifications_enabled'),
            'show_following_count' => $request->boolean('show_following_count'),
            'show_follower_count' => $request->boolean('show_follower_count'),
        ]);

        return back()->with('status', 'アカウント設定を保存しました。');
    }

    public function favorites()
    {
        $contents = auth()->user()->favoriteContents()
            ->with(['author', 'genre', 'subGenre'])
            ->withCount(['favorites', 'comments'])
            ->latest('favorites.created_at')
            ->paginate(20);

        return view('account.favorites', compact('contents'));
    }

    public function sales()
    {
        $user = auth()->user();
        $tab = request('tab', 'monthly');

        $total = DB::table('order_items')
            ->join('contents', 'contents.id', '=', 'order_items.content_id')
            ->where('contents.user_id', $user->id)
            ->sum('order_items.price');

        $monthly = DB::table('order_items')
            ->join('contents', 'contents.id', '=', 'order_items.content_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('contents.user_id', $user->id)
            ->selectRaw("DATE_FORMAT(orders.purchased_at, '%Y-%m') as label, SUM(order_items.price) as amount")
            ->groupBy('label')
            ->orderByDesc('label')
            ->get();

        $daily = DB::table('order_items')
            ->join('contents', 'contents.id', '=', 'order_items.content_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('contents.user_id', $user->id)
            ->selectRaw('DATE(orders.purchased_at) as label, SUM(order_items.price) as amount')
            ->groupBy('label')
            ->orderByDesc('label')
            ->take(30)
            ->get();

        $products = DB::table('order_items')
            ->join('contents', 'contents.id', '=', 'order_items.content_id')
            ->where('contents.user_id', $user->id)
            ->selectRaw('contents.title as label, COUNT(*) as quantity, SUM(order_items.price) as amount')
            ->groupBy('contents.title')
            ->orderByDesc('amount')
            ->get();

        $orders = Order::whereHas('items.content', function ($query) use ($user) {
                $query->where('contents.user_id', $user->id);
            })
            ->with(['user', 'items.content'])
            ->latest('purchased_at')
            ->paginate(12)
            ->withQueryString();

        return view('account.sales', compact('total', 'monthly', 'daily', 'products', 'orders', 'tab'));
    }

    public function purchases()
    {
        $orders = auth()->user()->orders()
            ->with('items.content.author')
            ->latest('purchased_at')
            ->paginate(20);

        return view('account.purchases', compact('orders'));
    }

    public function purchaseDetail(Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $order->load('items.content.author');

        return view('account.purchase-detail', compact('order'));
    }

    public function library()
    {
        $contents = auth()->user()->libraryContents()
            ->with(['author', 'genre', 'subGenre'])
            ->latest('library_items.created_at')
            ->paginate(20);

        return view('account.library', compact('contents'));
    }

    public function following()
    {
        $users = auth()->user()->following()->paginate(20);

        return view('account.users', [
            'users' => $users,
            'title' => auth()->user()->display_name.'さんのフォローリスト',
            'switchLabel' => 'フォロワーリストへ',
            'switchRoute' => route('followers.index'),
        ]);
    }

    public function followers()
    {
        $users = auth()->user()->followers()->paginate(20);

        return view('account.users', [
            'users' => $users,
            'title' => auth()->user()->display_name.'さんのフォロワーリスト',
            'switchLabel' => 'フォローリストへ',
            'switchRoute' => route('following.index'),
        ]);
    }

    public function notifications()
    {
        $notifications = auth()->user()->appNotifications()
            ->with('actor')
            ->latest()
            ->paginate(20);

        auth()->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return view('account.notifications', compact('notifications'));
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            $contentIds = Content::where('user_id', $user->id)->pluck('id');

            CartItem::whereIn('content_id', $contentIds)->delete();
            LibraryItem::whereIn('content_id', $contentIds)->delete();
            OrderItem::whereIn('content_id', $contentIds)->delete();

            $user->delete();
        });

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'アカウントを削除しました。');
    }
}

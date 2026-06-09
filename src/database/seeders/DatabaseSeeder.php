<?php

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Comment;
use App\Models\Content;
use App\Models\ContentImage;
use App\Models\Favorite;
use App\Models\Follow;
use App\Models\Genre;
use App\Models\LibraryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SubGenre;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->resetDemoData();

        $genres = $this->seedGenres();
        $users = $this->seedUsers();
        $contents = $this->seedContents($genres, $users);

        $this->seedSocialData($users, $contents);
        $this->seedOrdersAndReviews($users, $contents);
    }

    private function resetDemoData()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ([
            'app_notifications',
            'library_items',
            'order_items',
            'orders',
            'cart_items',
            'carts',
            'comments',
            'follows',
            'favorites',
            'content_tag',
            'content_images',
            'contents',
            'sub_genres',
            'genres',
            'users',
        ] as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function seedGenres()
    {
        return collect($this->genres())->mapWithKeys(function ($genreData) {
            $genre = Genre::create([
                'name' => $genreData['name'],
                'slug' => $genreData['slug'],
                'description' => $genreData['description'],
            ]);

            foreach ($genreData['sub_genres'] as $subGenre) {
                SubGenre::create([
                    'genre_id' => $genre->id,
                    'name' => $subGenre,
                    'slug' => $genreData['slug'].'-'.Str::slug($subGenre).'-'.Str::random(4),
                ]);
            }

            return [$genre->slug => $genre->fresh('subGenres')];
        });
    }

    private function seedUsers()
    {
        return collect($this->users())->map(function ($data, $index) {
            $attributes = User::factory()->make(array_merge($data, [
                'name' => $data['handle'],
                'email_verified_at' => now(),
                'avatar_path' => 'assets/avatars/avatar-'.(($index % 6) + 1).'.svg',
            ]))->getAttributes();

            return User::create($attributes);
        });
    }

    private function seedContents($genres, $users)
    {
        return collect($this->contents())->map(function ($data, $index) use ($genres, $users) {
            $genre = $genres[$data['genre']];
            $subGenre = $genre->subGenres->firstWhere('name', $data['sub']);
            $author = $users[$index % $users->count()];
            $thumbnail = 'assets/generated/asset-'.(($index % 12) + 1).'.svg';
            $slugBase = Str::slug($data['title']) ?: 'asset';

            $attributes = Content::factory()->make([
                'user_id' => $author->id,
                'genre_id' => $genre->id,
                'sub_genre_id' => $subGenre->id,
                'title' => $data['title'],
                'slug' => $slugBase.'-'.($index + 1),
                'format' => $data['format'],
                'description' => $data['description'],
                'price' => $data['price'],
                'thumbnail_path' => $thumbnail,
                'environment' => $data['environment'],
                'file_size_mb' => $data['size'],
                'rating_rate' => 0,
                'ratings_count' => 0,
                'profile_order' => $index + 1,
                'published_at' => now()->subDays($index * 2),
                'status' => 'published',
            ])->getAttributes();

            $content = Content::create($attributes);

            collect([0, 1, 2])->map(function ($offset) use ($index) {
                return 'assets/generated/asset-'.((($index + $offset) % 12) + 1).'.svg';
            })->unique()->values()->each(function ($path, $imageIndex) use ($content) {
                ContentImage::create([
                    'content_id' => $content->id,
                    'path' => $path,
                    'sort_order' => $imageIndex + 1,
                ]);
            });

            $tagIds = collect($data['tags'])->map(function ($tagName) {
                return Tag::firstOrCreate(
                    ['name' => $tagName],
                    ['slug' => Str::slug($tagName) ?: Str::random(8)]
                )->id;
            });

            $content->tags()->sync($tagIds);

            return $content;
        });
    }

    private function seedSocialData($users, $contents)
    {
        foreach ($users as $index => $user) {
            $contents->where('user_id', '!=', $user->id)->shuffle()->take(10)->each(function ($content) use ($user) {
                Favorite::create(['user_id' => $user->id, 'content_id' => $content->id]);
            });

            $users->where('id', '!=', $user->id)->shuffle()->take(4)->each(function ($followed) use ($user) {
                Follow::create(['follower_id' => $user->id, 'following_id' => $followed->id]);
            });
        }
    }

    private function seedOrdersAndReviews($users, $contents)
    {
        foreach ($users as $buyerIndex => $buyer) {
            $purchaseTargets = $contents->where('user_id', '!=', $buyer->id)->shuffle()->take(5);
            $order = Order::create([
                'user_id' => $buyer->id,
                'order_number' => 'DAP-DEMO-'.str_pad((string) ($buyerIndex + 1), 4, '0', STR_PAD_LEFT),
                'total_amount' => $purchaseTargets->sum('price'),
                'status' => 'paid',
                'purchased_at' => now()->subDays(12 - $buyerIndex),
            ]);

            foreach ($purchaseTargets as $content) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'content_id' => $content->id,
                    'price' => $content->price,
                ]);

                LibraryItem::create([
                    'user_id' => $buyer->id,
                    'content_id' => $content->id,
                    'order_item_id' => $orderItem->id,
                    'added_type' => $content->price === 0 ? 'free' : 'purchase',
                ]);

                if ($buyerIndex % 2 === 0 || $content->price > 0) {
                    Comment::create([
                        'user_id' => $buyer->id,
                        'content_id' => $content->id,
                        'message' => $this->reviewMessages()[$buyerIndex % count($this->reviewMessages())],
                        'is_recommended' => $buyerIndex % 5 !== 0,
                    ]);

                    AppNotification::create([
                        'user_id' => $content->user_id,
                        'actor_id' => $buyer->id,
                        'type' => 'comment',
                        'message' => $buyer->display_name.'さんが「'.$content->title.'」にコメントしました。',
                        'url' => route('contents.show', $content).'#comments',
                    ]);
                }
            }
        }

        Content::with('comments')->get()->each(function ($content) {
            $ratingsCount = $content->comments->count();
            $recommendedCount = $content->comments->where('is_recommended', true)->count();
            $content->update([
                'ratings_count' => $ratingsCount,
                'rating_rate' => $ratingsCount ? (int) round($recommendedCount / $ratingsCount * 100) : 0,
            ]);
        });

        $cart = Cart::create(['user_id' => $users->first()->id, 'active' => true]);

        $contents->where('user_id', '!=', $users->first()->id)->take(2)->each(function ($content) use ($cart) {
            CartItem::create(['cart_id' => $cart->id, 'content_id' => $content->id]);
        });
    }

    private function genres()
    {
        return [
            [
                'name' => 'ビジネス・オフィス',
                'slug' => 'business-office',
                'description' => '資料、帳票、Notion、Excelなど、日々の業務を軽くするデータ。',
                'sub_genres' => ['業務テンプレート', '企画書・提案書', '経理・分析', '採用・人事', '議事録', '営業管理'],
            ],
            [
                'name' => '製造・工業系',
                'slug' => 'manufacturing',
                'description' => '工程管理、安全教育、品質管理、設備保全に使える現場向けデータ。',
                'sub_genres' => ['工程管理', '安全教育', '品質管理', '設備保全', '5S活動', '検査記録'],
            ],
            [
                'name' => '日常生活',
                'slug' => 'daily-life',
                'description' => '住居、節約、家事、健康管理など日常生活に役立つノウハウ資料。',
                'sub_genres' => ['家計管理', '家事効率化', '住居メンテナンス', '健康管理', '防災', '旅行準備'],
            ],
            [
                'name' => 'コード・システム',
                'slug' => 'code-system',
                'description' => 'アプリ雛形、演習セット、APIサンプル、Docker構成などの開発資産。',
                'sub_genres' => ['Laravel', 'JavaScript', 'API', 'Docker', 'WordPress', 'AIプロンプト'],
            ],
            [
                'name' => '教育・学習',
                'slug' => 'education',
                'description' => 'レポート、演習、動画教材、資格学習など学びを進めるセット。',
                'sub_genres' => ['大学レポート', '語学', 'プログラミング演習', '資格学習', '数学', '研究計画'],
            ],
            [
                'name' => 'クリエイティブ',
                'slug' => 'creative',
                'description' => '画像、音声、動画、3Dモデルなど制作のための素材。',
                'sub_genres' => ['画像素材', '動画素材', '3Dモデル', '音声素材', 'フォントリスト', '配信素材'],
            ],
        ];
    }

    private function users()
    {
        return [
            ['handle' => 'port_admin', 'nickname' => 'ポート管理人', 'email' => 'admin@example.com', 'bio' => 'DigitalAssetPortの動作確認用アカウントです。マーケット全体のサンプルを管理しています。'],
            ['handle' => 'office_craft', 'nickname' => 'Office Craft', 'email' => 'office@example.com', 'bio' => 'Excel、Notion、採用資料など、働く人の時間を少し戻すテンプレートを作っています。'],
            ['handle' => 'code_deck', 'nickname' => 'Code Deck', 'email' => 'code@example.com', 'bio' => 'Laravel、Docker、JavaScriptの学習教材と小さなアプリ雛形を投稿しています。'],
            ['handle' => 'study_harbor', 'nickname' => 'Study Harbor', 'email' => 'study@example.com', 'bio' => '大学レポート、資格学習、研究計画に使えるドキュメントをまとめています。'],
            ['handle' => 'life_patch', 'nickname' => 'Life Patch', 'email' => 'life@example.com', 'bio' => '家計管理、家事、健康、防災など日常生活の手間を減らす資料を配布しています。'],
            ['handle' => 'factory_notes', 'nickname' => 'Factory Notes', 'email' => 'factory@example.com', 'bio' => '製造現場向けの教育スライド、点検表、工程チェックリストを制作しています。'],
            ['handle' => 'creative_bits', 'nickname' => 'Creative Bits', 'email' => 'creative@example.com', 'bio' => '動画・画像・配信素材を中心に、すぐ試せる制作パックを作っています。'],
            ['handle' => 'prompt_garden', 'nickname' => 'Prompt Garden', 'email' => 'prompt@example.com', 'bio' => 'AIを業務や学習に使いやすくするプロンプト集を育てています。'],
            ['handle' => 'font_shelf', 'nickname' => 'Font Shelf', 'email' => 'font@example.com', 'bio' => '無料フォント、ライセンス整理、デザイン下準備に役立つリストを公開しています。'],
            ['handle' => 'shop_manuals', 'nickname' => 'Shop Manuals', 'email' => 'shop@example.com', 'bio' => '小売・飲食向けのマニュアルやPOP、シフト運用資料を作っています。'],
            ['handle' => 'video_kit', 'nickname' => 'Video Kit', 'email' => 'video@example.com', 'bio' => '教材動画やSNS投稿の制作を速くする台本、プリセット、進行表が得意です。'],
            ['handle' => 'research_base', 'nickname' => 'Research Base', 'email' => 'research@example.com', 'bio' => '研究計画、調査票、分析メモのテンプレートを学生向けに整理しています。'],
        ];
    }

    private function contents()
    {
        return [
            ['title' => 'Excel月次売上ダッシュボード改', 'genre' => 'business-office', 'sub' => '経理・分析', 'format' => 'external_tool', 'price' => 1800, 'environment' => 'Excel 2021以降', 'size' => 12.5, 'tags' => ['Excel', '売上', '分析'], 'description' => '売上CSVを貼り付けるだけで月別、日別、商品別に集計できるダッシュボードです。サンプルデータと説明シートを同梱しています。'],
            ['title' => 'Notion採用管理ボード一式', 'genre' => 'business-office', 'sub' => '採用・人事', 'format' => 'external_tool', 'price' => 2200, 'environment' => 'Notion', 'size' => 3.8, 'tags' => ['Notion', '採用', '人事'], 'description' => '応募者管理、面接ログ、選考ステータス、求人票の下書きをまとめたNotionテンプレートです。'],
            ['title' => '提案書ストーリー構成テンプレート', 'genre' => 'business-office', 'sub' => '企画書・提案書', 'format' => 'text', 'price' => 1200, 'environment' => 'PowerPoint / Google Slides', 'size' => 8.1, 'tags' => ['提案書', '営業', '資料'], 'description' => '課題、原因、打ち手、効果の流れを崩さず作れる提案書テンプレートです。'],
            ['title' => '議事録からタスク化する会議ノート', 'genre' => 'business-office', 'sub' => '議事録', 'format' => 'text', 'price' => 0, 'environment' => 'Google Docs / Notion', 'size' => 1.9, 'tags' => ['議事録', 'タスク', '無料'], 'description' => '会議中のメモを決定事項、論点、担当タスクに分けて整理するためのテンプレートです。'],
            ['title' => '製造ライン安全教育スライド2026', 'genre' => 'manufacturing', 'sub' => '安全教育', 'format' => 'text', 'price' => 2400, 'environment' => 'PowerPoint / PDF', 'size' => 34.7, 'tags' => ['安全教育', '製造', '研修'], 'description' => '危険予知、保護具、ヒヤリハット共有を新人向けに説明するスライド資料です。講師メモ付きです。'],
            ['title' => '工程チェックリスト電子化セット', 'genre' => 'manufacturing', 'sub' => '工程管理', 'format' => 'external_tool', 'price' => 3200, 'environment' => 'Excel / PDF', 'size' => 16.9, 'tags' => ['工程管理', 'チェックリスト', '品質'], 'description' => '紙のチェックリストをExcelで運用するための雛形です。承認欄と集計シートを用意しています。'],
            ['title' => '5S活動ポスターと点検表', 'genre' => 'manufacturing', 'sub' => '5S活動', 'format' => 'image', 'price' => 950, 'environment' => 'PDF / PNG', 'size' => 18.2, 'tags' => ['5S', '点検', '現場改善'], 'description' => '整理・整頓・清掃・清潔・しつけを現場で共有するポスターと毎週点検表のセットです。'],
            ['title' => '設備保全ログブック', 'genre' => 'manufacturing', 'sub' => '設備保全', 'format' => 'text', 'price' => 1600, 'environment' => 'Excel / PDF', 'size' => 9.4, 'tags' => ['設備保全', '記録', 'メンテナンス'], 'description' => '設備ごとの点検履歴、異常メモ、部品交換予定を管理できるログブックです。'],
            ['title' => '一人暮らし固定費見直しシート', 'genre' => 'daily-life', 'sub' => '家計管理', 'format' => 'external_tool', 'price' => 500, 'environment' => 'Excel / Google Sheets', 'size' => 2.2, 'tags' => ['家計', '節約', '日常'], 'description' => '通信費、保険、サブスク、光熱費を洗い出して見直し額を計算できるシートです。'],
            ['title' => '週末まとめ家事ルーティン表', 'genre' => 'daily-life', 'sub' => '家事効率化', 'format' => 'text', 'price' => 0, 'environment' => 'PDF', 'size' => 1.1, 'tags' => ['家事', 'ルーティン', '無料'], 'description' => '掃除、洗濯、買い出し、作り置きを週末にまとめるためのチェックリストです。'],
            ['title' => '住居メンテナンス季節別チェック', 'genre' => 'daily-life', 'sub' => '住居メンテナンス', 'format' => 'text', 'price' => 700, 'environment' => 'PDF / Notion', 'size' => 2.8, 'tags' => ['住居', '点検', '日常'], 'description' => 'エアコン、排水口、窓、非常用品など、季節ごとに確認したい項目をまとめています。'],
            ['title' => '健康記録ミニダッシュボード', 'genre' => 'daily-life', 'sub' => '健康管理', 'format' => 'external_tool', 'price' => 900, 'environment' => 'Google Sheets', 'size' => 2.5, 'tags' => ['健康', '記録', '習慣'], 'description' => '睡眠、体重、歩数、気分メモをまとめて可視化するシンプルな記録シートです。'],
            ['title' => 'Laravel Docker演習スターター', 'genre' => 'code-system', 'sub' => 'Laravel', 'format' => 'system', 'price' => 3980, 'environment' => 'Laravel 8 / Docker', 'size' => 92.1, 'tags' => ['Laravel', 'Docker', '教材'], 'description' => 'ログイン、CRUD、テスト、Docker構築までを段階的に学べる演習セットです。'],
            ['title' => 'JavaScript UI演習カード', 'genre' => 'code-system', 'sub' => 'JavaScript', 'format' => 'system', 'price' => 2000, 'environment' => 'ブラウザ / Node.js', 'size' => 28.0, 'tags' => ['JavaScript', 'UI', '演習'], 'description' => 'タブ、モーダル、検索、フォーム検証などのUI演習を小さなカード形式でまとめた教材セットです。'],
            ['title' => 'APIレスポンス設計サンプル集', 'genre' => 'code-system', 'sub' => 'API', 'format' => 'text', 'price' => 1500, 'environment' => 'JSON / Markdown', 'size' => 4.7, 'tags' => ['API', '設計', 'JSON'], 'description' => '一覧、詳細、エラー、ページネーションなどのレスポンス例と設計メモをまとめています。'],
            ['title' => '業務AIプロンプト30選', 'genre' => 'code-system', 'sub' => 'AIプロンプト', 'format' => 'text', 'price' => 1100, 'environment' => 'ChatGPT / Markdown', 'size' => 1.7, 'tags' => ['AI', 'プロンプト', '業務効率'], 'description' => '議事録整理、メール下書き、要件整理、表作成など、業務で使いやすいプロンプト集です。'],
            ['title' => '大学レポート構成テンプレート集', 'genre' => 'education', 'sub' => '大学レポート', 'format' => 'text', 'price' => 0, 'environment' => 'Word / Google Docs', 'size' => 6.8, 'tags' => ['大学', 'レポート', '無料'], 'description' => '序論、本論、結論、参考文献の構成を崩さず書けるテンプレートです。'],
            ['title' => '資格学習 進捗管理シート', 'genre' => 'education', 'sub' => '資格学習', 'format' => 'external_tool', 'price' => 500, 'environment' => 'Excel / Google Sheets', 'size' => 3.5, 'tags' => ['資格', '学習管理', 'Excel'], 'description' => '学習範囲、復習日、模試結果を管理できる進捗シートです。'],
            ['title' => '研究計画書はじめの一枚', 'genre' => 'education', 'sub' => '研究計画', 'format' => 'text', 'price' => 1300, 'environment' => 'Word / PDF', 'size' => 4.4, 'tags' => ['研究', '計画書', '学生'], 'description' => '背景、問い、方法、期待される成果を1枚で整理する研究計画テンプレートです。'],
            ['title' => '英単語復習スケジューラ', 'genre' => 'education', 'sub' => '語学', 'format' => 'external_tool', 'price' => 800, 'environment' => 'Google Sheets', 'size' => 2.6, 'tags' => ['語学', '復習', '英単語'], 'description' => '忘却曲線を意識して復習日を自動表示する語学学習用シートです。'],
            ['title' => '3D小物モデル ミニパック', 'genre' => 'creative', 'sub' => '3Dモデル', 'format' => 'model_3d', 'price' => 980, 'environment' => 'Blender / Unity', 'size' => 140.2, 'tags' => ['3D', 'Blender', '素材'], 'description' => 'アプリやゲームのモックアップに置ける軽量3D小物モデル集です。'],
            ['title' => '動画教材用チャプター台本', 'genre' => 'creative', 'sub' => '動画素材', 'format' => 'video', 'price' => 1600, 'environment' => 'Word / Premiere Pro', 'size' => 8.2, 'tags' => ['動画教材', '台本', '編集'], 'description' => '動画教材を作るための章立て、台本、撮影チェックリストをまとめたパックです。'],
            ['title' => '無料フォント用途別リスト', 'genre' => 'creative', 'sub' => 'フォントリスト', 'format' => 'text', 'price' => 0, 'environment' => 'PDF / Markdown', 'size' => 1.4, 'tags' => ['フォント', 'デザイン', '無料'], 'description' => '見出し、本文、資料、サムネイルなど用途別に無料フォントを整理したリストです。'],
            ['title' => '配信画面ミニオーバーレイ', 'genre' => 'creative', 'sub' => '配信素材', 'format' => 'image', 'price' => 1200, 'environment' => 'PNG / OBS', 'size' => 24.9, 'tags' => ['配信', 'OBS', '素材'], 'description' => '雑談、作業、ゲーム配信に使える軽量オーバーレイ素材です。'],
            ['title' => '営業パイプライン管理Excel', 'genre' => 'business-office', 'sub' => '営業管理', 'format' => 'external_tool', 'price' => 1700, 'environment' => 'Excel', 'size' => 5.6, 'tags' => ['営業', '案件管理', 'Excel'], 'description' => '見込み、提案、契約、失注までを管理する営業パイプライン用Excelです。'],
            ['title' => '新人オンボーディングToDo', 'genre' => 'business-office', 'sub' => '採用・人事', 'format' => 'text', 'price' => 900, 'environment' => 'Notion / PDF', 'size' => 2.1, 'tags' => ['新人教育', '人事', 'チェックリスト'], 'description' => '入社前後の準備、初週面談、権限付与、研修進捗を漏れなく進めるToDo集です。'],
            ['title' => '検査記録テンプレートQR版', 'genre' => 'manufacturing', 'sub' => '検査記録', 'format' => 'external_tool', 'price' => 2100, 'environment' => 'Excel / QR', 'size' => 7.3, 'tags' => ['検査', 'QR', '品質'], 'description' => '検査記録を品番別に整理し、QRで対象シートへ移動できるテンプレートです。'],
            ['title' => '品質異常レポート雛形', 'genre' => 'manufacturing', 'sub' => '品質管理', 'format' => 'text', 'price' => 1400, 'environment' => 'Word / PDF', 'size' => 3.9, 'tags' => ['品質', 'レポート', '改善'], 'description' => '異常内容、原因、暫定対応、恒久対策を整理する品質異常報告書の雛形です。'],
            ['title' => '防災持ち出し品リスト家族版', 'genre' => 'daily-life', 'sub' => '防災', 'format' => 'text', 'price' => 0, 'environment' => 'PDF', 'size' => 1.8, 'tags' => ['防災', '家族', '無料'], 'description' => '家族構成ごとに持ち出し品と備蓄品を整理できるチェックリストです。'],
            ['title' => '旅行準備タスクボード', 'genre' => 'daily-life', 'sub' => '旅行準備', 'format' => 'external_tool', 'price' => 650, 'environment' => 'Notion', 'size' => 2.3, 'tags' => ['旅行', '準備', 'Notion'], 'description' => '予約、持ち物、現地移動、予算を一つにまとめる旅行準備ボードです。'],
            ['title' => 'WordPress小規模サイト雛形', 'genre' => 'code-system', 'sub' => 'WordPress', 'format' => 'system', 'price' => 2600, 'environment' => 'WordPress 6', 'size' => 36.5, 'tags' => ['WordPress', 'サイト制作', 'PHP'], 'description' => '小規模サイトやポートフォリオに使えるシンプルなWordPressテーマ雛形です。'],
            ['title' => 'Docker Compose逆引きメモ', 'genre' => 'code-system', 'sub' => 'Docker', 'format' => 'text', 'price' => 800, 'environment' => 'Docker', 'size' => 1.6, 'tags' => ['Docker', '開発環境', 'メモ'], 'description' => 'DB、MailHog、PHP、Nodeなどローカル開発でよく使うCompose設定を逆引き形式でまとめています。'],
            ['title' => '数学レポート式展開テンプレート', 'genre' => 'education', 'sub' => '数学', 'format' => 'text', 'price' => 700, 'environment' => 'LaTeX / Word', 'size' => 2.7, 'tags' => ['数学', 'レポート', 'LaTeX'], 'description' => '定義、証明、計算過程、考察を読みやすく並べる数学レポート向けテンプレートです。'],
            ['title' => 'プログラミング演習採点表', 'genre' => 'education', 'sub' => 'プログラミング演習', 'format' => 'external_tool', 'price' => 1100, 'environment' => 'Google Sheets', 'size' => 2.9, 'tags' => ['プログラミング', '採点', '教育'], 'description' => '課題提出、動作確認、コード品質、コメントを管理できる演習採点表です。'],
            ['title' => 'サムネイル用背景テクスチャ集', 'genre' => 'creative', 'sub' => '画像素材', 'format' => 'image', 'price' => 1300, 'environment' => 'PNG', 'size' => 42.0, 'tags' => ['画像', '背景', 'サムネイル'], 'description' => '動画サムネイルや資料表紙に使える背景テクスチャをまとめた素材集です。'],
            ['title' => '短尺動画SEスターター', 'genre' => 'creative', 'sub' => '音声素材', 'format' => 'audio', 'price' => 980, 'environment' => 'WAV / MP3', 'size' => 58.4, 'tags' => ['音声', 'SE', '動画編集'], 'description' => '短尺動画の場面転換や強調に使いやすい効果音を収録したスターターセットです。'],
        ];
    }

    private function reviewMessages()
    {
        return [
            '説明が具体的で、すぐ自分用に調整できました。サンプルがあるのも助かります。',
            'デモ用途として見栄えが良く、ポートフォリオの説明にも使いやすい内容でした。',
            '期待していた用途にかなり近かったです。編集しやすい構成なのが良いです。',
            '少し自分向けに直す必要はありましたが、土台として十分便利でした。',
            '必要な項目がまとまっていて、探す時間を減らせました。',
        ];
    }
}

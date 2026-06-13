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

            foreach ($genreData['sub_genres'] as $index => $subGenre) {
                SubGenre::create([
                    'genre_id' => $genre->id,
                    'name' => $subGenre,
                    'slug' => $genreData['slug'].'-'.($index + 1),
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
        $contentData = collect($this->contents());
        $coveredGenres = $contentData->pluck('genre')->unique();

        $genres->keys()->diff($coveredGenres)->each(function ($genreSlug) use ($genres, &$contentData) {
            $contentData->push($this->demoContentForGenre($genreSlug, $genres[$genreSlug]));
        });

        return $contentData->values()->map(function ($data, $index) use ($genres, $users) {
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
                return $this->tagId($tagName);
            });

            $content->tags()->sync($tagIds);

            return $content;
        });
    }

    private function tagId(string $name)
    {
        $tag = Tag::where('name', $name)->first();

        if ($tag) {
            return $tag->id;
        }

        $base = Str::slug($name) ?: 'tag';
        $slug = $base;
        $count = 2;

        while (Tag::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$count++;
        }

        return Tag::create(['name' => $name, 'slug' => $slug])->id;
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

    private function demoContentForGenre(string $genreSlug, Genre $genre)
    {
        $subGenre = $genre->subGenres->first();

        return [
            'title' => $genre->name.'スターターパック',
            'genre' => $genreSlug,
            'sub' => $subGenre->name,
            'format' => $this->formatForGenre($genreSlug),
            'price' => collect([0, 600, 900, 1200, 1800, 2400, 3200])->random(),
            'environment' => 'Windows / macOS / ブラウザ',
            'size' => collect([1.8, 3.4, 6.2, 12.7, 24.5, 48.0])->random(),
            'tags' => [$genre->name, $subGenre->name, 'デモ'],
            'description' => $genre->description.' '.$subGenre->name.'からすぐ試せるサンプルデータをまとめた、ポートフォリオ確認用のスターターセットです。',
        ];
    }

    private function formatForGenre(string $genreSlug)
    {
        $formats = [
            'audio-music' => 'audio',
            'video-motion' => 'video',
            'three-d-vr' => 'model_3d',
            'game-assets' => 'system',
            'fonts-typography' => 'text',
            'prompts-ai' => 'text',
            'datasets' => 'external_tool',
            'cad-blueprints' => 'model_3d',
            'stock-photo' => 'image',
            'ebooks-zines' => 'text',
            'web-themes' => 'system',
            'mobile-assets' => 'system',
        ];

        return $formats[$genreSlug] ?? 'external_tool';
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
            [
                'name' => '写真・ストック素材',
                'slug' => 'stock-photo',
                'description' => 'Web、資料、広告、SNSに使いやすい写真・背景・テクスチャ素材。',
                'sub_genres' => ['人物写真', '風景写真', '商品写真', '背景テクスチャ', '資料表紙', 'SNS素材'],
            ],
            [
                'name' => 'イラスト・漫画素材',
                'slug' => 'illustration-comic',
                'description' => 'アイコン、挿絵、漫画制作、配信用に使える描画素材。',
                'sub_genres' => ['キャラクター', '背景イラスト', '表情差分', '漫画トーン', '吹き出し', 'アイコンセット'],
            ],
            [
                'name' => '音楽・音声',
                'slug' => 'audio-music',
                'description' => 'BGM、効果音、ボイス、ポッドキャスト制作を支える音声データ。',
                'sub_genres' => ['BGM', '効果音', 'ジングル', 'ボイス素材', '環境音', '音声編集プリセット'],
            ],
            [
                'name' => '動画・モーション',
                'slug' => 'video-motion',
                'description' => '動画編集、配信、広告、教材づくりに使える映像素材。',
                'sub_genres' => ['トランジション', 'モーショングラフィックス', '字幕テンプレート', 'LUT', '縦動画テンプレート', '教材動画素材'],
            ],
            [
                'name' => '3D・VR/AR',
                'slug' => 'three-d-vr',
                'description' => '3Dモデル、VRChat、AR表示、ゲーム開発向けの立体データ。',
                'sub_genres' => ['3Dモデル', 'VRMアバター', 'マテリアル', 'モーション', '背景ワールド', 'AR配置データ'],
            ],
            [
                'name' => 'ゲーム制作',
                'slug' => 'game-assets',
                'description' => 'ゲーム開発に使う素材、UI、シナリオ、レベルデザイン資料。',
                'sub_genres' => ['2Dスプライト', 'タイルマップ', 'ゲームUI', 'シナリオ', 'レベルデザイン', 'サウンドパック'],
            ],
            [
                'name' => 'フォント・タイポグラフィ',
                'slug' => 'fonts-typography',
                'description' => 'フォント、文字組み、見出しデザイン、ライセンス整理の資料。',
                'sub_genres' => ['欧文フォント', '日本語フォント', '手書き風', '見出しテンプレート', '文字組み資料', 'ライセンス表'],
            ],
            [
                'name' => 'AI・プロンプト',
                'slug' => 'prompts-ai',
                'description' => '生成AIを業務、制作、学習、分析に活用するためのプロンプトと設定。',
                'sub_genres' => ['文章生成', '画像生成', 'コード支援', '業務自動化', '学習支援', 'AIエージェント'],
            ],
            [
                'name' => 'データセット・分析',
                'slug' => 'datasets',
                'description' => '学習用CSV、分析サンプル、可視化、機械学習向けのデータ。',
                'sub_genres' => ['CSVデータ', 'ダッシュボード', '機械学習', '統計教材', 'BIテンプレート', 'スクレイピング結果'],
            ],
            [
                'name' => 'Webテーマ・UI',
                'slug' => 'web-themes',
                'description' => 'Webサイト、管理画面、LP、UIキットに使えるフロントエンド資産。',
                'sub_genres' => ['HTMLテンプレート', 'CSSコンポーネント', '管理画面UI', 'LPテンプレート', 'Figma連携', 'アクセシビリティ部品'],
            ],
            [
                'name' => 'モバイルアプリ素材',
                'slug' => 'mobile-assets',
                'description' => 'スマホアプリのUI、アイコン、オンボーディング、実装雛形。',
                'sub_genres' => ['iOS UI', 'Android UI', 'アプリアイコン', 'オンボーディング', '通知文面', 'React Native雛形'],
            ],
            [
                'name' => 'WordPress・CMS',
                'slug' => 'wordpress-cms',
                'description' => 'CMSサイト制作、ブログ運営、テーマ、プラグイン設定に関するデータ。',
                'sub_genres' => ['テーマ雛形', 'プラグイン設定', 'ブログ設計', 'SEOチェック', '投稿テンプレート', '保守資料'],
            ],
            [
                'name' => '電子書籍・文章',
                'slug' => 'ebooks-zines',
                'description' => '電子書籍、ZINE、記事、台本、長文制作のためのデジタル文書。',
                'sub_genres' => ['電子書籍', 'ZINE', '記事テンプレート', '台本', '校正チェック', '出版準備'],
            ],
            [
                'name' => 'プレゼン・スライド',
                'slug' => 'presentations',
                'description' => '講義、営業、採用、研究発表などに使えるスライド資産。',
                'sub_genres' => ['営業資料', '講義スライド', '研究発表', 'ピッチ資料', '図解パーツ', '研修資料'],
            ],
            [
                'name' => 'スプレッドシート',
                'slug' => 'spreadsheets',
                'description' => 'Excel、Google Sheets、集計、自動化、管理表のテンプレート。',
                'sub_genres' => ['家計簿', '売上管理', '在庫管理', '進捗管理', '関数サンプル', 'GAS連携'],
            ],
            [
                'name' => '法務・契約',
                'slug' => 'legal-contracts',
                'description' => '契約書、規約、チェックリストなど事業運営を助ける文書テンプレート。',
                'sub_genres' => ['業務委託契約', '利用規約', 'プライバシーポリシー', 'NDA', '請求書文面', 'チェックリスト'],
            ],
            [
                'name' => '会計・税務',
                'slug' => 'accounting-tax',
                'description' => '個人事業、副業、確定申告、経理処理を楽にするデータ。',
                'sub_genres' => ['確定申告', '請求書', '経費管理', '売上台帳', '税務メモ', '予算管理'],
            ],
            [
                'name' => 'マーケティング',
                'slug' => 'marketing',
                'description' => '広告、SNS、SEO、分析、キャンペーン運用に使う実務データ。',
                'sub_genres' => ['SNS投稿', '広告文', 'SEO', 'アクセス解析', 'キャンペーン', 'ペルソナ設計'],
            ],
            [
                'name' => '店舗・イベント',
                'slug' => 'stores-events',
                'description' => '小売、飲食、展示会、同人イベント、ライブ運営向けのデジタル資料。',
                'sub_genres' => ['POP', 'メニュー表', 'シフト表', 'イベント進行', '出展準備', '接客マニュアル'],
            ],
            [
                'name' => '建築・CAD',
                'slug' => 'cad-blueprints',
                'description' => 'CAD、図面、インテリア、施工管理、DIYに使える設計データ。',
                'sub_genres' => ['CAD図面', '3D家具', '間取り', '施工チェック', 'DIY設計', '内装資料'],
            ],
            [
                'name' => '医療・ヘルスケア',
                'slug' => 'healthcare',
                'description' => '健康記録、介護、運動、栄養、医療事務に役立つテンプレート。',
                'sub_genres' => ['健康ログ', '服薬管理', '栄養管理', '運動計画', '介護記録', '医療事務'],
            ],
            [
                'name' => '研究・論文',
                'slug' => 'research-papers',
                'description' => '研究計画、調査、論文執筆、実験記録、参考文献管理の資料。',
                'sub_genres' => ['研究計画', '調査票', '実験ノート', '論文テンプレート', '参考文献', '分析メモ'],
            ],
            [
                'name' => '語学・翻訳',
                'slug' => 'language-translation',
                'description' => '語学学習、翻訳、字幕、発音練習、単語帳に使えるデータ。',
                'sub_genres' => ['単語帳', '文法教材', '翻訳メモリ', '字幕ファイル', '発音練習', '多言語UI'],
            ],
            [
                'name' => '生活・趣味',
                'slug' => 'hobby-life',
                'description' => '料理、旅行、読書、整理整頓、趣味活動を支えるデジタル資料。',
                'sub_genres' => ['レシピ', '旅行計画', '読書記録', '家事チェック', '趣味ログ', 'コレクション管理'],
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

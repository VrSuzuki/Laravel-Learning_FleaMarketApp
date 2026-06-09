# DigitalAssetPort Laravel App

このディレクトリは DigitalAssetPort の Laravel アプリ本体です。セットアップ、URL、機能一覧、ER図はリポジトリルートの `README.md` を参照してください。

よく使うコマンド:

```bash
docker compose exec php composer install
docker compose exec php php artisan storage:link
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php php artisan test
```

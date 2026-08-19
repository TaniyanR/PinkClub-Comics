# PinkClub-Comics

FANZAブックスのコミック・BL・TL・読み放題を扱う、漫画専用のアフィリエイト商品サイトです。

PinkClub-FLのデザイン・管理画面・SEO・アクセス解析・相互リンク/RSS・cron等の共通基盤を活かしつつ、公開画面と取得運用は漫画向けに整理しています。

## 対象

- コミック
- BL
- TL
- 読み放題

取得先は `config/config.php` の `dmm.catalog_targets` で管理し、cron実行ごとに対象を順番に巡回します。取得位置は対象ごとに保持します。

## 公開メニュー

- TOP
- コミック
- BL
- TL
- 読み放題
- 作者一覧
- ジャンル一覧
- シリーズ一覧
- レーベル一覧

メーカーは作品詳細等から参照できるため、上部メニューには置いていません。

## 漫画専用の表示

- 表紙を中心にした作品カード
- 作者・価格・配信日の表示
- サンプル画像が取得できる作品のみサンプル画像を表示
- サンプル動画、VR、女優、出演者、監督、収録時間等の動画向け公開UIは使用しない
- 作品詳細では作者、シリーズ、メーカー、レーベル、ジャンル、配信開始日、価格、対応デバイスを表示
- 購入導線は「FANZAで作品を見る」に統一

## 作品別ランキング

人気作品ランキングはTOPには表示せず、作品の個別ページ下部に表示します。

各作品に応じて次の週間ランキングを表示します。

- コミック / BL / TL / 読み放題の該当区分ランキング
- 主要作者の人気作品ランキング
- 主要ジャンルの人気作品ランキング
- 主要シリーズの人気作品ランキング

ランキングはページビューとFANZAへの遷移を使った既存アクセス集計を利用し、30分キャッシュします。対象データがないランキングは表示しません。

## 主な機能

- FANZA Affiliate APIの商品取得・保存
- 複数取得先の順次取得と取得先別offset管理
- コミック / BL / TL / 読み放題の作品一覧
- 作者一覧・作者別作品
- ジャンル・シリーズ・メーカー・レーベル関連ページ
- 作品検索
- WordPress風の管理画面
- API認証情報の保存、テスト取得、cron自動取得
- 初回セットアップ、DBマイグレーション、ログ表示
- SEO、OGP、JSON-LD、サイトマップ、RSS、アクセス解析
- 相互リンク・相互RSS

## API取得先

現在の標準設定は次の通りです。

| site | service | floor | 用途 |
| --- | --- | --- | --- |
| FANZA | ebook | comic | コミック |
| FANZA | ebook | bl | BL |
| FANZA | ebook | tl | TL |
| FANZA | ebook | unlimited | 読み放題 |

DMM/FANZA側のservice・floor構成が変更された場合は、管理画面の「Floor同期」で取得した値を確認し、`config/config.php` の `dmm.catalog_targets` を合わせてください。

## 自動取得

Comicsではcronの自動同期対象を商品取得に限定しています。作者・ジャンル・シリーズ・メーカー・レーベル等は取得した作品の `iteminfo` から保存します。

```bash
php /path/to/PinkClub-Comics/scripts/auto_import.php
```

複数の取得先はcron実行ごとに順番に切り替わり、offsetは取得先ごとに保存されます。

## 必要環境

- PHP 8.1以上
- MySQL 8.0またはMariaDB 10.5以上
- PDO MySQL、mbstring、JSON、cURLまたはallow_url_fopen
- Apache / nginx
- cron（自動取得を使う場合）

XAMPPでも確認できます。

## セットアップ

1. ファイル一式をサーバーへ配置します。
2. `/public/setup_check.php` を開きます。
3. DB接続情報を保存します。
4. セットアップを実行します。
5. `/public/login0718.php` からログインします。
6. 管理画面の「商品情報API設定」でAPI IDとアフィリエイトIDを保存します。
7. Floor同期で取得対象を確認します。
8. テスト取得でAPI接続とDB保存を確認します。
9. cronを設定します。

初期管理者は `admin` / `password` です。公開前に必ず変更してください。

## 主要URL

- 公開トップ: `/`
- コミック: `/public/catalog.php?type=comic`
- BL: `/public/catalog.php?type=bl`
- TL: `/public/catalog.php?type=tl`
- 読み放題: `/public/catalog.php?type=unlimited`
- 作者一覧: `/public/authors.php`
- ジャンル一覧: `/public/genres.php`
- シリーズ一覧: `/public/series_list.php`
- レーベル一覧: `/public/labels.php`
- 管理ログイン: `/public/login0718.php`
- 管理トップ: `/admin/index.php`
- セットアップ確認: `/public/setup_check.php`
- API設定: `/admin/api_items.php`
- Floor同期: `/admin/sync_floors.php`

旧 `/public/items.php` はコミック一覧へ、旧女優関連の公開URLは作者一覧へ301リダイレクトします。

## 設定とセキュリティ

- DB接続情報やAPI認証情報をGitへコミットしないでください。
- `config.local.php`、ログ、セッション情報は公開しないでください。
- 管理者パスワードを変更し、HTTPSで運用してください。
- 本サイトは成人向けコンテンツを扱います。法令、広告主規約、年齢確認要件を確認してください。

## クレジット

<a href="https://affiliate.dmm.com/api/" target="_blank" rel="nofollow"><img src="https://p.dmm.co.jp/p/affiliate/web_service/r18_135_17.gif" alt="WEB SERVICE BY FANZA" width="135" height="17"></a>

商品情報はDMM/FANZA Affiliate APIを利用します。

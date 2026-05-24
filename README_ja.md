# WP Native Vector Search

> これは技術検証用のプラグインです。データが多いサイトで使用すると著しいパフォーマンス低下を起こす可能性があります。本番サイトで使用したい場合は、このプラグインがPHPで行っているコサイン類似検索を専用のVector DBに置き換えてください。

WP Native Vector Search は、OpenAI の embedding を WordPress のデータベースに保存し、投稿・固定ページ・画像メディアを意味検索できるようにする WordPress プラグインです。

外部 Vector DB は使用しません。

## 主な機能

- OpenAI による text embedding 生成
- 独自テーブルへの vector 保存
- 投稿保存・status 変更時の index キュー登録
- アップロード画像の自然言語説明文生成
- 生成した画像説明文の `wp_postmeta` 保存
- 画像説明文の embedding 化によるメディア検索
- REST API による投稿・メディア検索
- Gutenberg 検索ブロック
- 既存データ向け WP-CLI コマンド
- `設定 > Vector Search` の管理画面
- メディアライブラリでの画像説明文表示

## 必要環境

- WordPress 6.5 以上
- PHP 8.0 以上
- OpenAI API Key
- WordPress HTTP API から `api.openai.com` にアクセスできること

## OpenAI モデル

初期 embedding model:

- `text-embedding-3-small`

初期 vision model:

- `gpt-4.1-mini`

どちらも管理画面から変更できます。

## 設定

`設定 > Vector Search` を開きます。

設定項目:

- OpenAI API Key
- Embedding model
- Vision model
- 対象投稿タイプ
- embedding 入力の最大文字数
- 最小 similarity score
- 保存・status 変更時の自動 index
- メディア検索の有効化
- WordPress 標準検索フォームの vector search 置換

API Key は定数でも指定できます。

```php
define( 'WP_NATIVE_VECTOR_SEARCH_OPENAI_API_KEY', 'sk-...' );
```

この定数が定義されている場合は、保存済み設定より定数が優先されます。

## データベース

有効化時に次のテーブルを作成します。

```text
wp_vector_search_embeddings
```

カラム:

- `id`
- `post_id`
- `post_type`
- `post_status`
- `content_hash`
- `embedding`
- `embedding_model`
- `dimensions`
- `created_at`
- `updated_at`

embedding は JSON 配列として保存します。

メディア検索では、画像 attachment の embedding も同じテーブルに保存します。

- `post_id`: attachment ID
- `post_type`: `attachment`
- `post_status`: attachment の現在の status

投稿 embedding は投稿 status にかかわらず保存します。検索時に WordPress 上の現在の status を確認し、公開検索の対象になる投稿だけを返します。メディア検索が有効な場合、メディア embedding は attachment の status にかかわらず検索対象になります。

## 投稿の index

投稿の自動 index は次のフックでキュー登録します。

- `save_post`
- `transition_post_status`
- `deleted_post`

公開・保存リクエスト中には OpenAI API を直接呼び出しません。投稿ごとに WordPress cron の単発イベントを 1 つだけ予約し、`save_post` と `transition_post_status` の重複による二重 API 呼び出しを避けます。

embedding 対象:

- 投稿タイトル
- 抜粋
- 本文

対象になるのは、設定で選択された投稿タイプです。

設定で選択された投稿タイプは status にかかわらず index します。非公開の投稿も vector テーブルには残り、検索時に現在の status が検索対象外なら除外されます。

メディア embedding は attachment の status や親投稿の status にかかわらず保存します。検索結果にメディア embedding を含めるかどうかは設定画面で切り替えます。

## 画像説明文生成

画像メディアに対して、検索用の自然言語説明文を生成できます。

対応 MIME type:

- `image/jpeg`
- `image/png`
- `image/webp`
- `image/gif`

画像のアップロードまたは編集時に、WordPress cron イベントを予約します。cron 実行時にローカル画像ファイルを base64 data URL として OpenAI Responses API に送り、vision model で日本語の説明文を生成します。説明文が利用可能になると、別の cron イベントでメディア embedding を保存します。

説明文には次の観点を含めます。

- 何が写っているか
- 用途
- 読み取れる文字情報
- 色
- 構図
- 雰囲気
- 関連しそうな検索語

生成結果は `wp_postmeta` に保存します。

postmeta keys:

- `_wp_native_vector_search_image_description`
- `_wp_native_vector_search_image_description_model`
- `_wp_native_vector_search_image_description_hash`
- `_wp_native_vector_search_image_description_generated_at`
- `_wp_native_vector_search_image_description_error`

初期実装では 10MB を超える画像はスキップします。

## メディアの index

メディア index は、生成済みの画像説明文を text embedding 化します。

embedding 対象:

- attachment title
- alt text
- caption
- attachment description
- 生成済み画像説明文

これにより、次のような自然言語検索ができます。

- `CMS の比較表`
- `青い背景のロゴ`
- `管理画面のスクリーンショット`
- `WordPress と Headless CMS の図解`

## 検索 backend

デフォルトは PHP fallback です。JSON embedding を読み込み、PHP で cosine similarity を計算します。

MariaDB 11.7+ では MariaDB Vector を任意で利用できます。

- `php`: 常に JSON/PHP fallback を使います。
- `mariadb_vector`: 選択中の embedding model に対応する MariaDB Vector table が利用可能な場合だけ使います。
- `auto`: MariaDB Vector が利用可能な場合は使い、利用できない場合は PHP に fallback します。

Vector table は dimension ごとに分かれます。

- `wp_vector_search_embeddings_vec_1536`
- `wp_vector_search_embeddings_vec_3072`

互換性のため、JSON embedding table は引き続き source of truth として保持します。

## WP-CLI コマンド

### 投稿 index

```sh
wp vector-search index
```

オプション:

- `--post_type=post|attachment`
- `--limit=100`
- `--force`
- `--dry-run`

例:

```sh
wp vector-search index --post_type=post --limit=100
```

`--post_type=attachment` を指定した場合は、メディア index の処理を実行します。画像説明文が未生成の場合、説明文を生成してから embedding を作成します。

### キュー済み投稿 index の実行

```sh
wp vector-search run-queue
```

オプション:

- `--due-now`

デフォルトでは、予約済みの vector search 投稿 index ジョブをすべて実行します。`--due-now` を付けると、予定時刻を過ぎたジョブのみ実行します。

### メディア説明文生成

```sh
wp vector-search describe-media
```

オプション:

- `--limit=100`
- `--force`
- `--dry-run`

例:

```sh
wp vector-search describe-media --limit=100
```

### メディア index

```sh
wp vector-search index-media
```

オプション:

- `--limit=100`
- `--force`
- `--dry-run`

例:

```sh
wp vector-search index-media --limit=100
```

画像説明文が未生成の場合、`index-media` は説明文を生成してから embedding を作成します。

### MariaDB Vector 操作

```sh
wp vector-search vector-status
wp vector-search create-vector-tables
wp vector-search migrate-vectors --dimension=1536
```

オプション:

- `vector-status --dimension=1536 --refresh`
- `create-vector-tables --dimension=1536`
- `migrate-vectors --dimension=1536 --batch=100`

先に vector table を作成し、既存 JSON embedding を移行します。新規 index では、MariaDB Vector が利用可能な場合に JSON storage と対応する vector table の両方へ書き込みます。

## ユニットテスト

プラグインディレクトリで軽量ユニットテストを実行します。

```sh
php tests/unit/run.php
```

テストは WordPress 関数とデータベースをローカル stub で置き換えるため、Composer、PHPUnit、WordPress core、OpenAI API access、起動中の database は不要です。

## REST API

Endpoint:

```text
/wp-json/vector-search/v1/search
```

Methods:

- `GET`
- `POST`

Request:

```json
{
  "query": "WordPress セキュリティ",
  "limit": 10
}
```

このエンドポイントには、意図しない API 利用量の増加を抑えるための簡易 IP ベース rate limit があります。また、正規化済みの検索クエリ embedding を5分間キャッシュします。

投稿結果:

```json
{
  "type": "post",
  "post_id": 1,
  "title": "WordPress Security",
  "description": "WordPress のログイン、権限、プラグイン更新を安全に運用するための実践ガイド。",
  "url": "https://example.com/wordpress-security",
  "post_type": "post",
  "thumbnail_url": "https://example.com/wp-content/uploads/wordpress-security-150x150.png",
  "score": 0.91
}
```

メディア結果:

```json
{
  "type": "media",
  "attachment_id": 10,
  "post_id": 10,
  "title": "cms-comparison",
  "description": "WordPress と Headless CMS の選択肢を比較する図解。",
  "url": "https://example.com/wp-content/uploads/cms-comparison.png",
  "post_type": "attachment",
  "thumbnail_url": "https://example.com/wp-content/uploads/cms-comparison-150x150.png",
  "media_url": "https://example.com/wp-content/uploads/cms-comparison.png",
  "score": 0.88
}
```

## Gutenberg ブロック

ブロック名:

```text
wp-native-vector-search/search-box
```

ブロックタイトル:

```text
Vector Search Box
```

機能:

- 検索入力
- REST API 呼び出し
- ローディング表示
- メディアサムネイル付き検索結果表示

## 標準検索フォームの置換

設定画面で `Replace WordPress Search` を有効にすると、次の検索 UI をプラグインの vector search フォームに置き換えます。

- `get_search_form()` で出力される検索フォーム
- Core Search ブロック (`core/search`)

置換後のフォームは通常の WordPress 検索結果ページへ遷移せず、プラグインの REST API を呼び出して vector search の結果を表示します。

置換フィルターはフロントエンドのテンプレートリクエストでのみ登録します。wp-admin、AJAX、REST API、WP-CLI では検索マークアップを置換しません。

## 管理画面

### 設定

設定画面:

```text
設定 > Vector Search
```

### メディアライブラリ

メディアライブラリ一覧に `Vector Description` 列を追加します。

添付ファイル詳細画面とメディアモーダルでは、次の情報を表示します。

- 生成済み画像説明文
- vision model
- 生成日時
- 最後の生成エラー

画像説明文は読み取り専用で表示します。

## 検索の仕組み

検索には cosine similarity を使用します。

初期実装の流れ:

1. 検索クエリを OpenAI で embedding 化する
2. WordPress DB から保存済み embedding を取得する
3. PHP 側で cosine similarity を計算する
4. score の高い順に結果を返す
5. 設定された最小 score 未満の結果を除外する

小規模から中規模のローカルデータセットを想定しています。大量のメディアライブラリや高トラフィック環境では、バッチ処理、キュー、キャッシュ、検索対象の絞り込みが必要です。

## 開発メモ

このプラグインは現在、ローカル開発と検証を主目的にしています。

主なトレードオフ:

- 外部 Vector DB は使用しない
- embedding は MySQL に JSON として保存する
- 画像検索は直接画像 embedding ではなく、生成した説明文の embedding を使う
- アップロードまたは編集された画像の説明文生成は WordPress cron で実行する。一方、WP-CLI のメディア index は現在のコマンド内で実行する
- 大量の画像 index は時間と API 利用量を消費する

## アンインストール

アンインストール時に削除するもの:

- `wp_native_vector_search_settings`
- `wp_vector_search_embeddings`

画像説明文 postmeta は、各 attachment が削除されるタイミングで削除されます。

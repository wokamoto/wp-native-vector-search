
# WP Native Vector Search - 仕様書

## 概要

WordPress の投稿データを OpenAI API で embedding 化し、
WordPress DB 内の独自テーブルに保存する。

検索時は検索クエリを embedding 化し、
保存済み vector データとの cosine similarity を計算して
意味的に近い記事を検索する。

外部 Vector DB は使用しない。

---

# 主な機能

- OpenAI API による embedding 生成
- 独自テーブルへの vector 保存
- 投稿更新時の vector 更新キュー登録
- WP-CLI による既存データ indexing
- WP REST API による vector search
- Gutenberg Block による検索 UI 提供
- メディア画像の説明文生成
- メディア画像説明文の vector indexing
- WordPress 標準検索フォームの置換

---

# 使用モデル

初期値:

- text-embedding-3-small

設定可能:

- OpenAI API Key
- embedding model
- vision model
- 最大文字数
- 最小 similarity score
- 自動 vector 化 ON/OFF
- WordPress 標準検索フォームの置換 ON/OFF

画像説明文生成:

- 初期値: gpt-4.1-mini
- OpenAI Responses API の画像入力を使用する
- ローカル画像ファイルを base64 data URL として送信する
- 生成した説明文は text embedding 化の前段データとして利用する

---

# DB設計

## テーブル名

wp_vector_search_embeddings

## カラム

- id
- post_id
- post_type
- post_status
- content_hash
- embedding
- embedding_model
- dimensions
- created_at
- updated_at

embedding は JSON 配列で保存する。

attachment の画像説明文 embedding は同じテーブルに保存する。

- post_id: attachment ID
- post_type: attachment
- post_status: 検索対象の場合は publish

検索時は post_status が publish の row のみを対象にする。
- content_hash: embedding model と画像説明文検索テキストから生成

---

# 投稿保存時の処理

使用フック:

- save_post
- transition_post_status
- deleted_post

動作:

- 投稿公開時に index 用 WordPress cron イベントを予約する
- 本文変更時は index 用 WordPress cron イベントを予約する
- save_post と transition_post_status が同一リクエストで発火しても同一投稿の予約は 1 つにまとめる
- 投稿公開・保存リクエスト内では OpenAI API を直接呼び出さない
- cron 実行時に embedding を生成する
- 削除時は vector 削除

embedding 対象:

- post_title
- post_excerpt
- post_content

---

# メディア画像説明文生成

目的:

- WordPress メディアライブラリの画像を自然言語検索できるようにする
- 画像そのものを直接 embedding するのではなく、画像説明文を生成して text embedding 化する

対象:

- attachment のうち画像 MIME type のもの
- 対応 MIME type:
  - image/jpeg
  - image/png
  - image/webp
  - image/gif

使用 API:

- OpenAI Responses API
- vision model に画像を入力し、日本語の説明文を生成する

説明文に含める観点:

- 何が写っているか
- 用途
- 読み取れる文字情報
- 色
- 構図
- 雰囲気
- 関連しそうな検索語

保存先:

- wp_postmeta

postmeta keys:

- _wp_native_vector_search_image_description
- _wp_native_vector_search_image_description_model
- _wp_native_vector_search_image_description_hash
- _wp_native_vector_search_image_description_generated_at
- _wp_native_vector_search_image_description_error

使用フック:

- add_attachment
- edit_attachment
- delete_attachment

動作:

- 画像アップロード時に説明文を生成して postmeta に保存する
- 画像編集時に説明文を再生成する
- ファイル hash と vision model が変わっていなければ再生成しない
- 削除時は関連 postmeta を削除する
- API エラー時は error meta にメッセージを保存する

制限:

- 初期実装では 10MB を超える画像はスキップする
- animated GIF のフレーム解析は行わない
- 説明文生成後、画像説明文を text embedding 化して検索対象にできる
- メディアは明示的に publish されているか、公開済みの親投稿に添付されているか、公開済み投稿本文から参照されている場合のみ検索対象にする
- 下書き・非公開コンテンツでのみ使われているメディアは media index 時に vector テーブルから削除する

---

# WP-CLI

## コマンド

wp vector-search index

## オプション

- --post_type=post
- --limit=100
- --force
- --dry-run

## メディア説明文生成コマンド

wp vector-search describe-media

## オプション

- --limit=100
- --force
- --dry-run

## メディア index コマンド

wp vector-search index-media

## オプション

- --limit=100
- --force
- --dry-run

動作:

- 画像説明文が未生成の場合は、説明文を生成してから embedding 化する
- 画像説明文が生成済みの場合は、その説明文を embedding 化する
- 画像説明文、attachment title、alt text、caption、description を embedding 対象に含める

## キュー実行コマンド

wp vector-search run-queue

## オプション

- --due-now

動作:

- 予約済みの投稿 index cron イベントを実行する
- --due-now がある場合は予定時刻を過ぎたイベントのみ実行する

---

# REST API

## Endpoint

/wp-json/vector-search/v1/search

## Request

{
  "query": "WordPress セキュリティ",
  "limit": 10
}

## Response

{
  "results": [
    {
      "post_id": 1,
      "type": "post",
      "title": "WordPress Security",
      "score": 0.91
    },
    {
      "type": "media",
      "attachment_id": 10,
      "post_id": 10,
      "title": "cms-comparison",
      "thumbnail_url": "http://localhost:8080/assets/uploads/...",
      "media_url": "http://localhost:8080/assets/uploads/...",
      "score": 0.88
    }
  ]
}

---

# 類似度計算

cosine similarity を使用。

初期実装では PHP 側で計算する。

設定された最小 score 未満の結果は検索結果から除外する。

初期値:

- 0.25

---

# Gutenberg Block

## ブロック名

Vector Search Box

## 機能

- 検索入力
- REST API 呼び出し
- 検索結果表示
- ローディング表示

---

# WordPress 標準検索フォームの置換

設定:

- Replace WordPress Search

対象:

- `get_search_form()` で出力される検索フォーム
- Core Search ブロック (`core/search`)

動作:

- 設定が ON の場合、標準検索フォームを Vector Search Box と同等の検索フォームに置き換える
- 置換フィルターはフロントエンドのテンプレートリクエストでのみ登録する
- wp-admin、AJAX、REST API、WP-CLI では検索マークアップを置換しない
- 置換後のフォームは通常の WordPress 検索結果ページへ遷移しない
- プラグインの REST API `/wp-json/vector-search/v1/search` を呼び出して結果を表示する
- ブロック用の `view.js` と `style.css` を流用する

---

# 管理画面

設定 > Vector Search

設定項目:

- OpenAI API Key
- embedding model
- vision model
- 対象投稿タイプ
- 最大文字数
- 最小 similarity score
- 自動 index
- WordPress 標準検索フォームの置換

メディアライブラリ:

- 一覧画面に Vector Description 列を追加する
- 添付ファイル詳細画面 / メディアモーダルに Vector Description を読み取り専用で表示する
- 表示内容は wp_postmeta の `_wp_native_vector_search_image_description`
- model / generated_at / error meta も補足情報として表示する

---

# ディレクトリ構成

wp-native-vector-search/
├── wp-native-vector-search.php
├── includes/
├── blocks/
└── assets/

---

# 開発順序

1. プラグイン雛形
2. DB テーブル作成
3. OpenAI API クライアント
4. 投稿保存時 indexing
5. WP-CLI
6. REST API
7. Block 実装
8. 管理画面

# WP Native Vector Search テスト手順

この手順は、ローカル Docker Compose 環境で `wp-native-vector-search` プラグインを試すためのものです。

前提:

- WordPress が `docker compose` で起動している
- `wp-native-vector-search` プラグインが有効化済み
- `設定 > Vector Search` で OpenAI API Key が保存済み
- 既存の embedding は必要に応じて再生成してよい

## 1. 起動状態を確認する

```sh
docker compose ps
```

`php`, `wp-cli`, `webserver`, `database` が起動していることを確認します。

```sh
docker compose exec -T wp-cli wp plugin list --fields=name,status,version
```

`wp-native-vector-search` が `active` であることを確認します。

## 2. テスト投稿を追加する

検索結果の違いが分かるように、話題が異なる投稿を複数作成します。

```sh
docker compose exec -T wp-cli wp post create \
  --post_type=post \
  --post_status=publish \
  --post_title='WordPress セキュリティ対策' \
  --post_excerpt='ログイン保護、権限管理、更新運用についての記事です。' \
  --post_content='WordPress のセキュリティでは、強力なパスワード、二要素認証、プラグイン更新、不要な管理者権限の削除が重要です。'
```

```sh
docker compose exec -T wp-cli wp post create \
  --post_type=post \
  --post_status=publish \
  --post_title='ブロックテーマの作り方' \
  --post_excerpt='theme.json とテンプレート編集についての記事です。' \
  --post_content='ブロックテーマでは theme.json で色、タイポグラフィ、レイアウトを定義し、Site Editor でテンプレートを編集します。'
```

```sh
docker compose exec -T wp-cli wp post create \
  --post_type=post \
  --post_status=publish \
  --post_title='MySQL パフォーマンス改善' \
  --post_excerpt='インデックス、クエリ、キャッシュについての記事です。' \
  --post_content='MySQL のパフォーマンス改善では、適切なインデックス設計、遅いクエリの確認、オブジェクトキャッシュの利用が効果的です。'
```

作成された投稿を確認します。

```sh
docker compose exec -T wp-cli wp post list --post_type=post --post_status=publish --fields=ID,post_title,post_status
```

## 3. dry-run で index 対象を確認する

OpenAI API を呼び出す前に、対象件数を確認します。

```sh
docker compose exec -T wp-cli wp vector-search index --post_type=post --limit=20 --dry-run
```

`would_index` が表示されれば、対象投稿が検出されています。

## 4. embedding を生成する

実際に OpenAI API を呼び出し、embedding を保存します。

```sh
docker compose exec -T wp-cli wp vector-search index --post_type=post --limit=20
```

成功例:

```text
Post 1: indexed
Success: Done. indexed=1 skipped=0 deleted=0 would_index=0 failed=0
```

保存件数を確認します。

```sh
docker compose exec -T wp-cli wp db query "SELECT COUNT(*) AS embeddings FROM wp_vector_search_embeddings;"
```

## 5. REST API で検索する

WordPress 内部から REST API を呼び出して確認します。

```sh
docker compose exec -T wp-cli wp eval '$request = new WP_REST_Request("POST", "/vector-search/v1/search"); $request->set_header("content-type", "application/json"); $request->set_body(wp_json_encode(array("query" => "ログインを安全にしたい", "limit" => 5))); $response = rest_do_request($request); echo wp_json_encode($response->get_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;'
```

次のようにクエリを変えて、意味的に近い記事が上位に出るか確認します。

```sh
docker compose exec -T wp-cli wp eval '$request = new WP_REST_Request("POST", "/vector-search/v1/search"); $request->set_header("content-type", "application/json"); $request->set_body(wp_json_encode(array("query" => "サイトエディターでデザインを調整する", "limit" => 5))); $response = rest_do_request($request); echo wp_json_encode($response->get_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;'
```

```sh
docker compose exec -T wp-cli wp eval '$request = new WP_REST_Request("POST", "/vector-search/v1/search"); $request->set_header("content-type", "application/json"); $request->set_body(wp_json_encode(array("query" => "データベースのクエリを速くしたい", "limit" => 5))); $response = rest_do_request($request); echo wp_json_encode($response->get_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;'
```

ホストから接続できる場合は、次の `curl` でも確認できます。

```sh
curl -sS -X POST http://localhost:8080/wp-json/vector-search/v1/search \
  -H 'Content-Type: application/json' \
  -d '{"query":"WordPress のログインを守る","limit":5}'
```

## 6. 更新時の再 index を確認する

投稿本文を更新します。

```sh
docker compose exec -T wp-cli wp post update <POST_ID> \
  --post_content='WordPress のセキュリティでは、ログイン試行制限、二要素認証、WAF、権限の最小化、定期的なバックアップが重要です。'
```

自動 index が有効なら、保存時に embedding が再生成されます。

手動で強制再生成する場合:

```sh
docker compose exec -T wp-cli wp vector-search index --post_type=post --limit=20 --force
```

## 7. 非公開化・削除時の挙動を確認する

投稿を下書きに戻すと、その投稿の embedding は削除されます。

```sh
docker compose exec -T wp-cli wp post update <POST_ID> --post_status=draft
```

削除する場合:

```sh
docker compose exec -T wp-cli wp post delete <POST_ID> --force
```

保存件数を確認します。

```sh
docker compose exec -T wp-cli wp db query "SELECT post_id, post_type, post_status, embedding_model, dimensions, updated_at FROM wp_vector_search_embeddings ORDER BY post_id;"
```

## 8. Gutenberg ブロックを確認する

1. WordPress 管理画面を開く
2. 固定ページまたは投稿を編集する
3. `Vector Search Box` ブロックを追加する
4. 公開またはプレビューする
5. 検索フォームで次のようなクエリを試す

例:

- `ログインを安全にしたい`
- `テーマのデザインを編集したい`
- `データベースを高速化したい`

検索中表示、結果表示、結果なし表示、エラー表示が崩れないことも確認します。

## 9. 標準検索フォームの置換を確認する

1. `設定 > Vector Search` を開く
2. `Replace WordPress Search` を有効にして保存する
3. 検索フォームを表示しているページを開く
4. 標準検索フォームが vector search フォームに置き換わっていることを確認する
5. 検索語を入力し、通常の WordPress 検索結果ページに遷移せず結果が表示されることを確認する

Core Search ブロックを使っている場合も、同じフォームに置き換わります。

## 10. メディア画像説明文を確認する

既存の画像メディアを対象に、説明文生成の dry-run を実行します。

```sh
docker compose exec -T wp-cli wp vector-search describe-media --limit=20 --dry-run
```

実際に OpenAI API を呼び出して説明文を保存します。

```sh
docker compose exec -T wp-cli wp vector-search describe-media --limit=20
```

保存された説明文を確認します。

```sh
docker compose exec -T wp-cli wp post meta list <ATTACHMENT_ID> \
  --keys=_wp_native_vector_search_image_description,_wp_native_vector_search_image_description_model,_wp_native_vector_search_image_description_generated_at \
  --fields=meta_key,meta_value
```

WordPress 管理画面でも確認します。

1. `メディア > ライブラリ` を開く
2. 一覧の `Vector Description` 列に説明文の抜粋が表示されることを確認する
3. 対象画像を開き、添付ファイル詳細の `Vector Description` に説明文、model、生成日時が表示されることを確認する

画像ファイルや vision model が変わっていない場合は、再実行時に `skipped` になります。

強制再生成する場合:

```sh
docker compose exec -T wp-cli wp vector-search describe-media --limit=20 --force
```

画像説明文を embedding 化し、vector search の対象に追加します。

```sh
docker compose exec -T wp-cli wp vector-search index-media --limit=20 --dry-run
```

```sh
docker compose exec -T wp-cli wp vector-search index-media --limit=20
```

メディア検索結果を REST API で確認します。

```sh
docker compose exec -T wp-cli wp eval '$request = new WP_REST_Request("POST", "/vector-search/v1/search"); $request->set_header("content-type", "application/json"); $request->set_body(wp_json_encode(array("query" => "CMSの比較表やイラスト", "limit" => 5))); $response = rest_do_request($request); echo wp_json_encode($response->get_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;'
```

メディア結果には `type: media`, `attachment_id`, `thumbnail_url`, `media_url` が含まれます。

## 11. よく使う確認コマンド

プラグイン状態:

```sh
docker compose exec -T wp-cli wp plugin list --fields=name,status,version
```

設定値の確認:

```sh
docker compose exec -T wp-cli wp option get wp_native_vector_search_settings --format=json
```

embedding テーブルの確認:

```sh
docker compose exec -T wp-cli wp db query "SELECT id, post_id, post_type, post_status, embedding_model, dimensions, updated_at FROM wp_vector_search_embeddings ORDER BY updated_at DESC LIMIT 10;"
```

index dry-run:

```sh
docker compose exec -T wp-cli wp vector-search index --post_type=post --limit=20 --dry-run
```

強制再 index:

```sh
docker compose exec -T wp-cli wp vector-search index --post_type=post --limit=20 --force
```

キュー済み投稿 index の実行:

```sh
docker compose exec -T wp-cli wp vector-search run-queue
```

メディア説明文生成 dry-run:

```sh
docker compose exec -T wp-cli wp vector-search describe-media --limit=20 --dry-run
```

メディア index:

```sh
docker compose exec -T wp-cli wp vector-search index-media --limit=20
```

## 12. 注意点

- `wp vector-search index` は OpenAI API を呼び出すため、実行件数に応じて API 利用量が増えます。
- 投稿公開・更新時は OpenAI API を直接呼び出さず、WordPress cron に単発イベントを予約します。
- キュー済みの投稿 index は `wp vector-search run-queue` で手動実行できます。
- `wp vector-search describe-media` も OpenAI API を呼び出すため、画像件数に応じて API 利用量が増えます。
- `wp vector-search index-media` は、説明文が未生成の画像では説明文生成と embedding 生成の両方で OpenAI API を呼び出します。
- 初期実装では PHP 側で全 embedding を読み込んで cosine similarity を計算します。大量データでは遅くなる可能性があります。
- `text-embedding-3-small` 以外のモデルへ変更した場合、既存 embedding と dimensions が変わる可能性があります。モデル変更後は `--force` で再 index してください。
- 画像説明文は wp_postmeta に保存され、`index-media` により embedding 化されます。
- 検索結果の順位は、テスト投稿の本文量や表現に影響されます。似たテーマの記事を増やすと差分を確認しやすくなります。

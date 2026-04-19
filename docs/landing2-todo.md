# landing2 未対応タスク・提案リスト

次回セッションへの引継ぎ用。当初の A〜G カテゴリ + 追加提案のうち、**現時点で未実装のもの**を優先度付きでまとめる。

## A. アクセシビリティ

- [ ] **A3** キャラ画像 img に `role="presentation"` 追加（装飾の明示）
- [ ] **A4** LIVE/ROSTER 横スクローラーのキーボード操作（←→、Tab）
- [ ] **A5** Champion avatar の alt を「優勝者のアイコン」等に

## B. SEO

- [ ] **B3** `og:type="website"`、`twitter:card` の徹底（header.php 側で大部分は対応済、残りの確認）
- [ ] **B4** `<link rel="canonical">` 追加

## C. パフォーマンス

- [ ] **C2** フォント `@import` → `templates/header.php` の preconnect + link に `M+PLUS+Rounded+1c` を足す（ブロッキング削減）
- [ ] **C3** `logo-small.webp` を `<link rel="preload" fetchpriority="high">`
- [ ] **C4** ローダー画像に `fetchpriority="high"`
- [ ] **C5** `glob()` 結果のキャッシュ化（APCu / ファイルキャッシュ）
- [ ] **C6** divider キャラの `loading="lazy"` を `eager` に見直し（すぐ見える位置のもの）

## D. UX（当初提案）

- [ ] **D2** Stats カウントアップのイージング変更（easeOutQuart 等）
- [ ] **D5** 「すべての大会 / 選手一覧を見る」ボタン SP 時中央寄せ
- [ ] **D7** Spotlight にインタビュー本文冒頭を引用表示（DBクエリ追加要）

## E. ビジュアル

- [ ] **E5** フッター全体の充実（SNS リンク等）— landing2 スコープ外

## F. コード構造（保守性）

- [ ] **F1** landing2.php を `templates/partials/lp3/*.php` に分割（600行 → 10パーツ）
- [ ] **F2** landing-pop.css をセクション別に `@import` 分割（1400行 → 10ファイル）
- [ ] **F3** `enableDragScroll` / `initDots` を共通 JS に切り出し
- [ ] **F4** 区切りキャラの keyframes を CSS 変数化

## G. インタラクション（+α）

- [ ] **G1** ヒーロー背景パララックス
- [ ] **G3** キャラアイコンがカーソルに反応
- [ ] （G2 Champion avatar 登場・G4 Stats 数字リンク化は実装済）

## 殿堂セクション 追加 TOP3 案

以下は提案済み・未実装。軽い順にメリット大きい：

- [ ] **通算獲得ポイント TOP3** — 既存の `HallOfFame::totalPointLeaders()` を流用可
- [ ] **参加大会数 TOP3** — 軽いクエリで「常連」が見える
- [ ] **平均ラウンド得点 TOP3** — 最低5試合のフィルタ付き
- [ ] **最多半荘数 TOP3** — よく打ってる人枠
- [ ] 最多準優勝 TOP3（クエリ新規・中コスト）
- [ ] 最多決勝進出 TOP3（決勝の定義次第）

## ローディング体感改善の続き

- [ ] DB クエリ本数（現在7本）を減らす or 並列化（複数クエリを1本に merge）
- [ ] Redis/APCu で `siteStats` など重い集計を 1分キャッシュ
- [ ] プログレスバーを実測ベースに（現在は演出のインディターミネイト）

## 既知の気になりポイント

- `lp3-divider::before` のスカラップ位置は divider を基準にしているが、viewport サイズによっては微妙にズレて見える可能性あり（SP 特に）。目視確認が必要
- `content-visibility: auto` を削除したため、長大ページのスクロール性能は若干落ちている可能性。測定で確認
- 背景アニメ廃止により `.lp3-band.is-offscreen` クラスも不要になった（JS で付与しているが無害、掃除可能）

## 直近ユーザーから追加要望があった項目の履歴

以下は実装済みだが、今後のデザイン判断が再発する可能性あり：

1. 背景柄は最終的に **全9セクション縦縞で色ユニーク** に着地
2. スカラップは **上のセクション側に所属** させる（divider::before）
3. バンド間の白線解消は `margin-top: -1px` で対応
4. ローディングドットは廃止、代わりに進捗バー
5. ローダーは意図的延長なし。`window.load` と連動

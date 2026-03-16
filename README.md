# 最強位戦 - 麻雀トーナメント

雀魂（じゃんたま）で開催する身内向け麻雀トーナメント「最強位戦」の戦績ページです。

## 概要

- 参加者20名によるトーナメント形式の大会
- 1回戦(20名) → 2回戦(16名) → 3回戦(12名) → 決勝(4名) の4段階
- ルール: 赤4 / 60秒 / トビ無
- 各ラウンドの対戦結果・累計ポイント・決勝進出者を表示

## ファイル構成

```
├── index.html            メインページ（HTML構造）
├── index.php             同内容（Docker/Apache用）
├── css/
│   ├── base.css          変数・リセット・レイアウト・Hero・プログレス
│   ├── components.css    順位表・タブ・卓カード・結果・レコード
│   └── finals.css        決勝卓セクションの演出・アニメーション
├── js/
│   ├── data.js           大会データ（順位・卓・各回戦結果）
│   ├── render.js         DOM描画・タブ切替
│   └── effects.js        パーティクル・スクロールアニメ・決勝エフェクト
├── Dockerfile            Render デプロイ用
└── README.md
```

## 技術スタック

- HTML / CSS / JavaScript（フレームワーク不使用）
- Google Fonts（Noto Sans JP, Inter）
- Docker（PHP 8.2 Apache）でホスティング

## データ更新方法

大会の結果を更新するには `js/data.js` を編集してください。

- `standings` : 総合順位・累計ポイント・各回戦スコア・敗退ラウンド
- `r1Tables` / `r2Tables` / `r3Tables` : 各回戦の卓割り
- `r1Above` / `r1Below` 等 : 各回戦の個別結果（通過 / 敗退）

## ローカルで確認

`index.html` をブラウザで直接開くだけで動作します。

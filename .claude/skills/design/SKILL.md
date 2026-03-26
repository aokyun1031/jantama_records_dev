---
name: design
description: UIデザインのルールとパターン。新しいページやコンポーネントを作成する際に参照する
---

# デザインシステム

## カラーパレット（CSS変数）

```
--purple:#9b8ce8  --pink:#e88cad    --mint:#5cc8b0
--gold:#d4a84c    --blue:#7ca8e8    --coral:#e8907c
--lavender:#b8a0e8
--text:#2d2b55    --text-sub:#8280a8  --text-light:#b0aed0
```

## デザイン原則

- グラスモーフィズム: `background:var(--card); backdrop-filter:blur(12px); border:1px solid var(--glass-border)`
- グラデーション: 135deg が基本角度。2〜4色のグラデーションを使う
- 角丸: `var(--radius)` (16px) または `var(--radius-sm)` (10px)
- 影: `var(--shadow-sm)` 通常時、`var(--shadow)` ホバー時
- ホバー: `transform:translateY(-2px)` + `box-shadow:var(--shadow)` のパターン

## フォント

- 日本語: `'Noto Sans JP'`
- 英数字・数値: `'Inter'`
- 見出し: font-weight 800〜900
- 本文: font-weight 400〜600

## アニメーション

- フェードイン: `fadeUp` (下から上) / `fadeDown` (上から下)
- カードの連続表示: `animation-delay` を `$i * 0.05s` でずらす
- 背景グラデーション: `bgShift` 60秒サイクル
- タイトル: `titleGrad` 6秒サイクルでグラデーション移動

## コンポーネントパターン

### バッジ
```css
display:inline-block;
background:linear-gradient(135deg, var(--lavender), var(--pink));
color:#fff; font-size:0.7rem; font-weight:700;
padding:4px 14px; border-radius:20px; letter-spacing:2px;
```

### カード
```css
background:var(--card); backdrop-filter:blur(12px);
border:1px solid var(--glass-border);
border-radius:var(--radius-sm);
padding:16px 20px; box-shadow:var(--shadow-sm);
transition:transform 0.3s, box-shadow 0.3s;
```

### 戻るボタン
```css
display:inline-flex; align-items:center; gap:8px;
padding:12px 24px;
background:linear-gradient(135deg, var(--purple), var(--pink));
color:#fff; text-decoration:none; border-radius:12px;
font-weight:700; font-size:0.85rem;
```

### ページヒーロー
```css
text-align:center; padding:48px 20px 32px;
```
バッジ → タイトル（グラデーション文字） → サブテキストの順で構成。

## レスポンシブ

- 最大幅: `.main { max-width:680px }`
- ブレークポイント: 768px 以下でモバイル最適化
- 480px 以下でグリッドを1カラムに
- モバイルでは `backdrop-filter` を無効化、アニメーションを簡略化

## テーマ

- ライト: デフォルト（CSS変数で定義）
- ダーク: `css/theme-dark.css` でCSS変数を上書き
- 新しいコンポーネントはライト・ダーク両対応にする

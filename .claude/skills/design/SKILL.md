---
name: design
description: UIデザインのルールとパターン。新しいページやコンポーネントを作成する際に参照する
---

# デザインシステム

## カラーパレット（CSS変数）

ハードコードカラー禁止。必ずCSS変数を使う。

```
色:     --purple  --pink  --mint  --gold  --blue  --coral  --lavender
テキスト: --text  --text-sub  --text-light
セマンティック: --danger  --success
```

## rgba() にはRGB変数を使う

```css
/* OK */
rgba(var(--accent-rgb), 0.25)
rgba(var(--gold-rgb), 0.15)
rgba(var(--mint-rgb), 0.12)
rgba(var(--coral-rgb), 0.1)
rgba(var(--danger-rgb), 0.2)

/* NG - ハードコード */
rgba(155, 140, 232, 0.25)
```

## 主要なCSS変数

| 用途 | 変数名 |
|---|---|
| 背景 | `--card`, `--card-hover` |
| ボーダー | `--glass-border` |
| 影 | `--shadow`, `--shadow-sm` |
| 角丸 | `--radius` (16px), `--radius-sm` (10px) |
| ボタン | `--btn-primary-bg`, `--btn-secondary-bg`, `--btn-text-color`, `--btn-primary-shadow` |
| バッジ | `--badge-bg`, `--badge-color` |
| タイトル | `--title-gradient`, `--title-filter` |
| スコア | `--plus-text`, `--minus-text` |

## デザイン原則

- グラスモーフィズム: `background:var(--card); border:1px solid var(--glass-border)`
- グラデーション: 135deg が基本角度
- ホバー: `transform:translateY(-2px)` + `box-shadow` のパターン
- カード出現: `opacity:0; transform:translateY(16px)` → `animation: fadeIn`

## フォント

- 日本語: `'Noto Sans JP'`（Variable Font, 400-900）
- 英数字: `'Inter'`（Variable Font, 400-900）
- 見出し: font-weight 800〜900
- 本文: font-weight 400〜600

## コンポーネントパターン

### バッジ
```css
background: var(--badge-bg);
color: var(--badge-color);
font-size: 0.7rem; font-weight: 700;
padding: 4px 14px; border-radius: 20px; letter-spacing: 2px;
box-shadow: 0 2px 12px rgba(var(--accent-rgb), 0.3);
```

### カード
```css
background: var(--card);
border: 1px solid rgba(var(--accent-rgb), 0.25);
border-radius: var(--radius-sm);
padding: 16px 20px; box-shadow: var(--shadow-sm);
```

### ボタン（プライマリ）
```css
background: var(--btn-primary-bg);
color: var(--btn-text-color);
border-radius: 12px; font-weight: 700;
box-shadow: var(--btn-primary-shadow);
```

### フォーム入力（input / select / textarea）
```css
border: 1.5px solid var(--input-border);
border-radius: var(--radius-sm);
background: var(--input-bg);
color: var(--text);
transition: border-color 0.2s, box-shadow 0.2s;
/* NG: --glass-border や --card を input に使わない（背景と区別がつかない） */
```
状態:
- `::placeholder` → `var(--input-placeholder)`
- `:hover:not(:disabled):not(:focus)` → `var(--input-border-hover)`
- `:focus` → `border-color: var(--input-border-focus); box-shadow: var(--input-focus-ring)`
- `:disabled` → `background: var(--input-disabled-bg); border-color: var(--input-disabled-border)`

### 成功メッセージ
```css
background: linear-gradient(135deg, rgba(var(--mint-rgb),0.12), rgba(var(--mint-rgb),0.04));
color: var(--success);
border: 1px solid rgba(var(--mint-rgb),0.3);
/* フェードイン → 3秒後フェードアウト */
```

### エラーメッセージ
```css
background: linear-gradient(135deg, rgba(var(--coral-rgb),0.12), rgba(var(--coral-rgb),0.04));
color: var(--danger);
border: 1px solid rgba(var(--coral-rgb),0.3);
```

### 削除セクション
```css
border: 1px solid rgba(var(--danger-rgb), 0.2);
background: rgba(var(--danger-rgb), 0.03);
```

## 画像

```html
<!-- width/height必須、alt必須、一覧ではlazy推奨 -->
<img src="..." alt="説明" width="88" height="88" loading="lazy">
```

キャラクターアイコン: `border-radius: 50%; object-fit: cover;`

## テーマ

- ライト: `base.css` の `:root` で定義（デフォルト）
- ダーク: `theme-dark.css` の `:root` でCSS変数を上書き
- CSS変数を使えば自動で両テーマ対応になる
- `theme-dark.css` にセレクタ別のルールは追加しない（変数のみ）

## レスポンシブ

- 最大幅: `.main { max-width:680px }`
- 768px 以下: モバイル最適化（backdrop-filter無効化、アニメーション簡略化）
- 480px 以下: グリッドを1カラムに
- `prefers-reduced-motion: reduce` でアニメーション一括停止対応済み

## URL

内部リンクに `.php` を付けない。

```html
<!-- OK -->  <a href="player?id=1">
<!-- NG -->  <a href="player.php?id=1">
```

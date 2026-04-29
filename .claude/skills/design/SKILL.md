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

- ライト固定。`base.css` の `:root` で CSS 変数を定義する
- ダークテーマは廃止済み。`theme-dark.css` / `theme-toggle.css` / `theme-toggle.js` は削除済み
- ハードコードカラーは禁止、新しい色は必ず CSS 変数で追加すること

## ページ固有 CSS の置き場所

- **必ず外部ファイル化する**: `public/css/{page-name}.css` として作成し、PHP 側で `$pageCss = ['css/{page-name}.css'];` で読み込む
- **`$pageStyle`（インライン CSS）は原則禁止**。キャッシュが効かない・行数が増えると保守できない
- 例外: Loader など「外部 CSS ロード前に描画が必要」な Critical CSS のみ `$pageStyle` に埋め込む（index.php の lp3-loader が該当）
- 既存コンポーネントが `components.css` / `base.css` / `forms.css` に無いかを先に確認し、使えるものは再利用する
- 複数ページで使い回す要素は `components.css` に昇格（例: `.page-hero` / `.page-hero-badge` / `.page-hero-title`）

## インライン `style=""` 属性の禁止

HTML 要素に直接 `style="background:var(--btn-secondary-bg);..."` 等を書かない。CSP nonce が効かず、CSS 変数を使っていても保守性が悪い。同じ装飾が複数ページで再現する場合は **modifier クラス**（BEM 風）として `components.css` に追加する。

```html
<!-- NG -->
<a href="..." class="btn-cancel" style="background:var(--btn-secondary-bg);color:var(--btn-text-color);">+ 追加</a>

<!-- OK: modifier クラス -->
<a href="..." class="btn-cancel btn-cancel--primary">+ 追加</a>
```

`components.css` 側:
```css
.btn-cancel--primary{
  background:var(--btn-secondary-bg);
  color:var(--btn-text-color);
  border-color:transparent;
}
```

例外: `style="animation-delay: <?= $i * 0.05 ?>s"` のように **PHP ループ index に依存する動的値** はインライン記述可。

## 一覧ページ共通の構造

複数ある一覧ページ（players / tournaments / tables）は同じ骨組みを使う。以下を流用すること:

- 下部のアクション群: `<div class="list-actions">` + `.btn-cancel` / `.btn-cancel--primary`
- 空状態: `<div class="list-empty">...</div>`
- DB エラー: `<div class="list-error"><div class="list-error-label">...</div><div class="list-error-detail">...</div></div>`
- ページネーション: `templates/partials/list-pagination.php` を `require` する（`paginate()` ヘルパで `$page / $totalPages` を取得、親スコープで `$pageUrl` クロージャを定義）

**ページ固有の `.tournaments-error` / `.tables-empty` 等を新規作成しない**。共通の `.list-*` を使う。

```php
/* OK */
$pageCss = ['css/forms.css', 'css/tournament.css'];

/* NG（長い $pageStyle は外出しへ） */
$pageStyle = <<<'CSS'
.td-hero { ... }
.td-badge { ... }
/* ... 100行以上 ... */
CSS;
```

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

---
name: enum
description: enum（`enums/` 配下の値オブジェクト）の追加手順と規約。ハードコード文字列を置き換える際に参照する
---

# Enum（値オブジェクト）

## 目的

ハードコード文字列・マジックナンバーを型安全な enum に置き換える。ラベル表示・CSS クラスなど表示系ロジックを enum 自身に集約する。

## 配置

- ディレクトリ: `enums/`
- composer autoload: `classmap: ["models/", "enums/"]`
- 追加後は必ず `composer dump-autoload` を実行

## 既存 enum 一覧

| enum | 値 | 用途 |
|---|---|---|
| `EventType` | 最強位戦/鳳凰位戦/マスターズ/百段位戦/プチイベント | 大会イベント種別 |
| `TournamentStatus` | preparing/in_progress/completed | 大会ステータス。`label()` + `cssClass()` |
| `PlayerMode` | 3/4 | 対局人数。`label()`（三麻/四麻） + `fullLabel()`（三人麻雀/四人麻雀） |
| `RoundType` | hanchan/tonpu/ikkyoku | 局数 |
| `HanRestriction` | 1/2/4 | 翻縛り |
| `ToggleRule` | 1/0 | ON/OFF 設定。`label('食い断')` → 食い断有/無 |
| `DayOfWeek` | 0-6 | 曜日。`fromDate('2026-04-07')` で日付から曜日ラベル |

## 基本構造

```php
<?php

declare(strict_types=1);

enum TournamentStatus: string
{
    case Preparing = 'preparing';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => '準備中',
            self::InProgress => '開催中',
            self::Completed => '終了',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Preparing => 'preparing',
            self::InProgress => 'active',
            self::Completed => 'completed',
        };
    }
}
```

## 規約

- 先頭に `declare(strict_types=1)`
- 文字列 enum `enum Name: string` を基本とする。数値が本質なら `int` も可
- case 名はパスカルケース、値は DB・API で使う実値（snake_case or そのまま）
- ラベル表示は必ず `label()` メソッドに集約。ビュー側で `match` を書かない
- 引数で表示を変える場合は `label(string $name)` パターン（`ToggleRule` 参照）
- CSS クラスも必要なら `cssClass()` メソッドに
- 日付・動的入力から enum を得るファクトリは `fromXxx()` パターン（`DayOfWeek::fromDate()` 参照）

## 追加手順

1. `enums/{EnumName}.php` を作成
2. `declare(strict_types=1)` ＋ `enum Name: string { ... }`
3. 必要なら `label()` / `cssClass()` / `fromXxx()` メソッド
4. `composer dump-autoload` を実行（Docker の場合 `docker compose exec web composer dump-autoload`）
5. ビュー・モデルのハードコード文字列を enum に置換
6. DB カラムから値を取り出す場合は `EnumName::from($row['column'])` でインスタンス化

## 使用例（ビュー側）

```php
<?php
$status = TournamentStatus::from($tournament['status']);
?>
<span class="status-<?= h($status->cssClass()) ?>">
  <?= h($status->label()) ?>
</span>
```

## 注意

- `from()` は不正値で例外。ユーザー入力由来なら `tryFrom()` を使い null 判定
- DB に存在しない値がある可能性があれば、マイグレーションで正規化してから enum 化
- enum 値の変更は DB データと同期が必要（マイグレーションで UPDATE）

## 関連スキル

- `data-model` — モデル層で enum を使う際のパターン
- `migration` — enum 値変更時の DB 同期

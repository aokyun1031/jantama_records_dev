<?php
/**
 * 一覧ページ共通のページネーション UI。
 *
 * 親スコープから以下を引き継ぐ:
 *   - int      $page         現在のページ番号（1 始まり）
 *   - int      $totalPages   総ページ数
 *   - callable $pageUrl      function(int $p): string  指定ページの URL を返す
 *
 * 入力契約が満たされない場合は何も描画しない（早期 return）。
 */
declare(strict_types=1);

// 親スコープから受け取る変数のガード。include 順を間違えても安全に no-op する。
$page = isset($page) ? (int) $page : 1;
$totalPages = isset($totalPages) ? (int) $totalPages : 1;
if (!isset($pageUrl) || !is_callable($pageUrl)) {
    return;
}
if ($totalPages <= 1) {
    return;
}
?>
<nav class="list-pagination" aria-label="ページ送り">
  <?php if ($page > 1): ?>
    <a href="<?= h($pageUrl($page - 1)) ?>" class="list-page-link" rel="prev">&larr; 前へ</a>
  <?php else: ?>
    <span class="list-page-link is-disabled" aria-disabled="true">&larr; 前へ</span>
  <?php endif; ?>
  <span class="list-page-info"><?= (int) $page ?> / <?= (int) $totalPages ?> ページ</span>
  <?php if ($page < $totalPages): ?>
    <a href="<?= h($pageUrl($page + 1)) ?>" class="list-page-link" rel="next">次へ &rarr;</a>
  <?php else: ?>
    <span class="list-page-link is-disabled" aria-disabled="true">次へ &rarr;</span>
  <?php endif; ?>
</nav>

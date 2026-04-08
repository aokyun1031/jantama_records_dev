<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? '雀魂部屋主催') ?></title>
<?php if (!empty($pageDescription)): ?>
<meta name="description" content="<?= h($pageDescription) ?>">
<?php endif; ?>
<link rel="icon" href="/favicon.ico" sizes="32x32">

<?php if (!empty($pageOgp)): ?>
<meta property="og:title" content="<?= h($pageOgp['title'] ?? $pageTitle) ?>">
<meta property="og:description" content="<?= h($pageOgp['description'] ?? '') ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= h($pageOgp['url'] ?? '') ?>">
<meta property="og:image" content="https://jantama-records.onrender.com/img/logo.png">
<meta property="og:site_name" content="雀魂部屋主催">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($pageOgp['title'] ?? $pageTitle) ?>">
<meta name="twitter:description" content="<?= h($pageOgp['description'] ?? '') ?>">
<meta name="twitter:image" content="https://jantama-records.onrender.com/img/logo.png">
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400..900&family=Inter:wght@400..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/base.css') ?>">
<link rel="stylesheet" href="<?= asset('css/components.css') ?>">
<?php foreach (($pageCss ?? []) as $css): ?>
<link rel="stylesheet" href="<?= asset($css) ?>">
<?php endforeach; ?>
<link rel="stylesheet" href="<?= asset('css/theme-dark.css') ?>" id="theme-dark">
<script>
(function(){var s=localStorage.getItem('saikyo-theme');if(s!=='dark'){document.getElementById('theme-dark').disabled=true}})();
</script>
<link rel="stylesheet" href="<?= asset('css/theme-toggle.css') ?>">

<?php if (!empty($pageStyle)): ?>
<style>
<?= str_ireplace('</style>', '', $pageStyle) ?>
</style>
<?php endif; ?>
</head>
<body>

<!-- Site Logo -->
<?php if (!empty($isTopPage)): ?>
  <div class="site-logo" aria-label="現在のページ: トップ">雀魂部屋主催</div>
<?php else: ?>
  <a href="/" class="site-logo">雀魂部屋主催</a>
<?php endif; ?>

<!-- Top Controls -->
<div class="top-controls">
  <!-- Theme Toggle -->
  <div class="theme-toggle" id="theme-toggle">
    <span class="theme-toggle-icon theme-toggle-sun">&#x2600;</span>
    <div class="theme-toggle-track" id="theme-track">
      <div class="theme-toggle-thumb"></div>
    </div>
    <span class="theme-toggle-icon theme-toggle-moon">&#x1F319;</span>
  </div>
  <!-- Hamburger Menu -->
  <button class="hamburger" id="hamburger" aria-label="メニュー">
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
  </button>
</div>

<!-- Nav Panel -->
<nav class="nav-panel" id="nav-panel">
  <a href="/" class="nav-panel-link">トップページ</a>
  <a href="/tournaments" class="nav-panel-link">大会一覧</a>
  <a href="/players" class="nav-panel-link">選手一覧</a>
</nav>
<div class="nav-overlay" id="nav-overlay"></div>

<div class="main">

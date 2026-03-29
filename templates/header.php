<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? '最強位戦', ENT_QUOTES, 'UTF-8') ?></title>

<?php if (!empty($pageOgp)): ?>
<meta property="og:title" content="<?= htmlspecialchars($pageOgp['title'] ?? $pageTitle, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:description" content="<?= htmlspecialchars($pageOgp['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:type" content="website">
<meta property="og:url" content="<?= htmlspecialchars($pageOgp['url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:image" content="https://jantama-records.onrender.com/img/logo.png">
<meta property="og:site_name" content="最強位戦">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($pageOgp['title'] ?? $pageTitle, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($pageOgp['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:image" content="https://jantama-records.onrender.com/img/logo.png">
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700;900&family=Inter:wght@400;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/components.css">
<?php foreach (($pageCss ?? []) as $css): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>">
<?php endforeach; ?>
<link rel="stylesheet" href="css/theme-dark.css" id="theme-dark">
<link rel="stylesheet" href="css/theme-toggle.css">

<?php if (!empty($pageStyle)): ?>
<style>
<?= str_replace('</style>', '', $pageStyle) ?>
</style>
<?php endif; ?>
</head>
<body>

<!-- Theme Toggle -->
<div class="theme-toggle" id="theme-toggle">
  <span class="theme-toggle-icon theme-toggle-sun">&#x2600;</span>
  <div class="theme-toggle-track" id="theme-track">
    <div class="theme-toggle-thumb"></div>
  </div>
  <span class="theme-toggle-icon theme-toggle-moon">&#x1F319;</span>
</div>

<div class="main">

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
http_response_code(404);
$pageTitle = 'ページが見つかりません - ' . SITE_NAME;
$pageCss = ['css/404.css'];

require __DIR__ . '/../templates/header.php';
?>

<div class="error-page">
  <div class="error-code">404</div>
  <div class="error-message">ページが見つかりません</div>
  <div class="error-detail">お探しのページは存在しないか、移動した可能性があります。</div>
  <a href="/" class="error-back">&#x2190; トップページに戻る</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>

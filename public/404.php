<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
http_response_code(404);
$pageTitle = 'ページが見つかりません - ' . SITE_NAME;
$pageStyle = <<<'CSS'
.error-page {
  text-align: center;
  padding: 80px 20px;
}

.error-code {
  font-family: 'Inter', sans-serif;
  font-size: clamp(4rem, 15vw, 8rem);
  font-weight: 900;
  background: var(--title-gradient);
  background-size: 300% 300%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: titleGrad 6s ease infinite;
  line-height: 1;
  margin-bottom: 16px;
}

.error-message {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 8px;
}

.error-detail {
  font-size: 0.85rem;
  color: var(--text-sub);
  margin-bottom: 32px;
}

.error-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: var(--btn-primary-bg);
  color: var(--btn-text-color);
  text-decoration: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.85rem;
  transition: transform 0.3s, box-shadow 0.3s;
  box-shadow: 0 4px 16px rgba(var(--accent-rgb), 0.3);
}

.error-back:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(var(--accent-rgb), 0.4);
}
CSS;

require __DIR__ . '/../templates/header.php';
?>

<div class="error-page">
  <div class="error-code">404</div>
  <div class="error-message">ページが見つかりません</div>
  <div class="error-detail">お探しのページは存在しないか、移動した可能性があります。</div>
  <a href="/" class="error-back">&#x2190; トップページに戻る</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>

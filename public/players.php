<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();

// --- フラッシュメッセージ ---
$flash = consumeFlash();

// --- データ取得 ---
['data' => $players, 'error' => $error] = fetchData(fn() => Player::all());

// --- テンプレート変数 ---
$pageTitle = '選手一覧 - ' . SITE_NAME;
$pageDescription = '麻雀トーナメントに参加する選手の一覧です。';
$pageCss = ['css/forms.css', 'css/players.css'];

// --- 表示 ---
require __DIR__ . '/../templates/header.php';
?>

<div class="page-hero">
  <div class="page-hero-badge">PLAYERS</div>
  <h1 class="page-hero-title">選手一覧</h1>
  <div class="players-count"><?= count($players ?? []) ?> 名の選手が登録されています</div>
</div>

<?php if ($flash): ?>
  <div class="edit-message success"><?= h($flash) ?></div>
<?php endif; ?>

<?php if ($error): ?>
  <div class="players-error">
    <div class="players-error-label">データベース接続エラー</div>
    <div class="players-error-detail">一時的にデータを取得できません。しばらくしてから再度お試しください。</div>
  </div>
<?php else: ?>
  <div class="players-grid">
  <?php foreach ($players as $i => $player): ?>
    <a href="player?id=<?= (int) $player['id'] ?>" class="player-card" style="animation-delay: <?= $i * 0.05 ?>s">
      <?php if ($player['character_icon']): ?>
        <img src="img/chara_deformed/<?= h($player['character_icon']) ?>" alt="<?= h($player['name']) ?>" class="player-icon" width="44" height="44" loading="lazy">
      <?php else: ?>
        <div class="player-icon-placeholder">NO<br>IMAGE</div>
      <?php endif; ?>
      <div class="player-info">
        <?php if ($player['nickname'] !== null && $player['nickname'] !== ''): ?>
          <div class="player-name"><?= h($player['nickname']) ?></div>
          <div class="player-nickname"><?= h($player['name']) ?></div>
        <?php else: ?>
          <div class="player-name"><?= h($player['name']) ?></div>
        <?php endif; ?>
      </div>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<div style="text-align: center; display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
  <a href="/" class="btn-cancel">&#x2190; トップページに戻る</a>
  <a href="player_new" class="btn-cancel" style="background:var(--btn-secondary-bg);color:var(--btn-text-color);border-color:transparent;box-shadow:0 4px 16px rgba(var(--mint-rgb),0.3);">+ 選手を追加</a>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>

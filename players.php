<?php
// .envファイルを読み込み（ローカル開発用）
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with($line, '#')) continue;
        putenv($line);
    }
}

// DATABASE_URL をパース
$databaseUrl = getenv('DATABASE_URL');
$players = [];
$error = null;

if ($databaseUrl) {
    $params = parse_url($databaseUrl);
    $host = $params['host'] ?? 'localhost';
    $port = $params['port'] ?? 5432;
    $name = ltrim($params['path'] ?? '', '/');
    $user = $params['user'] ?? '';
    $pass = $params['pass'] ?? '';
} else {
    $host = getenv('PGHOST') ?: 'localhost';
    $port = getenv('PGPORT') ?: 5432;
    $name = getenv('PGDATABASE') ?: 'jantama';
    $user = getenv('PGUSER') ?: 'postgres';
    $pass = getenv('PGPASSWORD') ?: '';
}

try {
    $sslmode = getenv('PGSSLMODE') ?: 'require';
    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslmode}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $stmt = $pdo->query('SELECT id, name FROM players ORDER BY id');
    $players = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>選手一覧 - 最強位戦</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;600;700;900&family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/base.css">
<link rel="stylesheet" href="css/components.css">
<link rel="stylesheet" href="css/theme-dark.css" id="theme-dark">
<link rel="stylesheet" href="css/theme-toggle.css">
<style>
.players-hero {
  text-align: center;
  padding: 48px 20px 32px;
}

.players-badge {
  display: inline-block;
  background: linear-gradient(135deg, var(--lavender), var(--pink));
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 14px;
  border-radius: 20px;
  margin-bottom: 16px;
  letter-spacing: 2px;
  box-shadow: 0 2px 12px rgba(184,160,232,0.3);
  animation: fadeDown 0.8s ease both;
}

.players-title {
  font-family: 'Noto Sans JP', sans-serif;
  font-size: clamp(1.8rem, 6vw, 2.5rem);
  font-weight: 900;
  background: linear-gradient(135deg, #9b8ce8, #e88cad, #d4a84c, #5cc8b0);
  background-size: 300% 300%;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: titleGrad 6s ease infinite, fadeUp 1s ease both;
  margin-bottom: 8px;
}

.players-count {
  font-size: 0.85rem;
  color: var(--text-sub);
  animation: fadeUp 1s ease 0.3s both;
}

.players-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
  margin-bottom: 40px;
}

.player-card {
  background: var(--card);
  backdrop-filter: blur(12px);
  border: 1px solid var(--glass-border);
  border-radius: var(--radius-sm);
  padding: 16px 20px;
  box-shadow: var(--shadow-sm);
  display: flex;
  align-items: center;
  gap: 12px;
  transition: transform 0.3s, box-shadow 0.3s;
  opacity: 0;
  transform: translateY(16px);
  animation: playerFadeIn 0.5s ease forwards;
}

.player-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}

.player-id {
  font-family: 'Inter', sans-serif;
  font-weight: 800;
  font-size: 0.85rem;
  color: var(--text-light);
  min-width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, rgba(155,140,232,0.15), rgba(155,140,232,0.05));
  border-radius: 50%;
  flex-shrink: 0;
}

.player-name {
  font-weight: 700;
  font-size: 1rem;
  color: var(--text);
}

.players-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: linear-gradient(135deg, var(--purple), var(--pink));
  color: #fff;
  text-decoration: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.85rem;
  transition: transform 0.3s, box-shadow 0.3s;
  box-shadow: 0 4px 16px rgba(155, 140, 232, 0.3);
  margin-bottom: 40px;
}

.players-back:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 24px rgba(155, 140, 232, 0.4);
}

.players-error {
  text-align: center;
  padding: 24px;
  background: linear-gradient(135deg, rgba(232,112,112,0.1), rgba(232,112,112,0.05));
  border: 1px solid rgba(232,112,112,0.3);
  border-radius: var(--radius);
  color: var(--text);
  font-size: 0.9rem;
  margin-bottom: 24px;
}

.players-error-label {
  font-weight: 700;
  color: var(--coral);
  margin-bottom: 8px;
}

.players-error-detail {
  font-size: 0.75rem;
  color: var(--text-sub);
  word-break: break-all;
}

@keyframes playerFadeIn {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 480px) {
  .players-grid {
    grid-template-columns: 1fr;
  }
}
</style>
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

<!-- Floating Particles -->
<div class="particles" id="particles"></div>

<div class="main">

<div class="players-hero">
  <div class="players-badge">PLAYERS</div>
  <h1 class="players-title">選手一覧</h1>
  <div class="players-count"><?= count($players) ?> 名の選手が登録されています</div>
</div>

<?php if ($error): ?>
  <div class="players-error">
    <div class="players-error-label">データベース接続エラー</div>
    <div class="players-error-detail"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  </div>
<?php else: ?>
  <div class="players-grid">
  <?php foreach ($players as $i => $player): ?>
    <div class="player-card" style="animation-delay: <?= $i * 0.05 ?>s">
      <div class="player-id"><?= (int)$player['id'] ?></div>
      <div class="player-name"><?= htmlspecialchars($player['name'], ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<div style="text-align: center;">
  <a href="index.html" class="players-back">&#x2190; トップページに戻る</a>
</div>

<div class="footer">最強位戦 - 麻雀トーナメント</div>

</div>

<script src="js/theme-toggle.js"></script>
</body>
</html>

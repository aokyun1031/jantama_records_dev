<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

// --- バリデーション ---
$tournamentId = requireTournamentId();
$tournament = requireTournamentWithMeta($tournamentId);

// 優勝者を取得
['data' => $champion] = fetchData(fn() => Standing::champion($tournamentId));

// 既存インタビューを取得
['data' => $interviews] = fetchData(fn() => Interview::byTournament($tournamentId));

$validationError = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validationError = validatePost();
    if ($validationError) {
        // バリデーションエラー
    } else {
        $action = sanitizeInput('action');

        if ($action === 'save') {
            $questions = $_POST['questions'] ?? [];
            $answers = $_POST['answers'] ?? [];
            $items = [];
            $hasError = false;
            for ($i = 0; $i < count($questions); $i++) {
                $q = preg_replace('/[\x00-\x1F\x7F]/u', '', trim($questions[$i] ?? ''));
                $a = preg_replace('/[\x00-\x1F\x7F]/u', '', trim($answers[$i] ?? ''));
                if ($q === '') {
                    continue;
                }
                if (mb_strlen($q) > 500) {
                    $validationError = '質問は500文字以内で入力してください。';
                    $hasError = true;
                    break;
                }
                if (mb_strlen($a) > 2000) {
                    $validationError = '回答は2000文字以内で入力してください。';
                    $hasError = true;
                    break;
                }
                $items[] = ['question' => $q, 'answer' => $a];
            }
            if (!$hasError) {
                try {
                    Interview::save($tournamentId, $items);
                    regenerateCsrfToken();
                    header('Location: interview_edit?id=' . $tournamentId . '&saved=1');
                    exit;
                } catch (PDOException $e) {
                    error_log('[DB] ' . $e->getMessage());
                    $validationError = '保存に失敗しました。';
                }
            }
        } elseif ($action === 'complete') {
            // インタビューが1つ以上あるか確認
            ['data' => $currentItems] = fetchData(fn() => Interview::byTournament($tournamentId));
            if (empty($currentItems)) {
                $validationError = 'インタビューを1つ以上登録してから大会を完了してください。';
            } else {
                try {
                    Tournament::complete($tournamentId);
                    $_SESSION['flash'] = '大会を完了しました！';
                    regenerateCsrfToken();
                    header('Location: tournament?id=' . $tournamentId);
                    exit;
                } catch (PDOException $e) {
                    error_log('[DB] ' . $e->getMessage());
                    $validationError = '大会の完了に失敗しました。';
                }
            }
        }
    }
}

$success = isset($_GET['saved']) && $_GET['saved'] === '1';
// 再取得
if ($success || $validationError) {
    ['data' => $interviews] = fetchData(fn() => Interview::byTournament($tournamentId));
}

$jsInterviews = json_encode(array_map(fn($item) => [
    'question' => $item['question'],
    'answer' => $item['answer'],
], $interviews ?? []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

// --- テンプレート変数 ---
$pageTitle = '優勝インタビュー設定 - ' . h($tournament['name']) . ' - ' . SITE_NAME;
$pageCss = ['css/forms.css', 'css/interview_edit.css'];

$pageTurnstile = true;
require __DIR__ . '/../templates/header.php';
?>

<div class="iv-hero">
  <div class="iv-badge">INTERVIEW</div>
  <div class="iv-title">優勝インタビュー設定</div>
  <div class="iv-subtitle"><?= h($tournament['name']) ?></div>
</div>

<div class="iv-content">
  <?php if ($success): ?>
    <div class="edit-message success">保存しました。</div>
  <?php elseif ($validationError): ?>
    <div class="edit-message error"><?= h($validationError) ?></div>
  <?php endif; ?>

  <?php if ($champion): ?>
    <div class="iv-champion">
      <?php if (!empty($champion['character_icon'])): ?>
        <img src="img/chara_deformed/<?= h($champion['character_icon']) ?>" alt="" class="iv-champion-icon" width="48" height="48" loading="lazy">
      <?php endif; ?>
      <div class="iv-champion-info">
        <div class="iv-champion-label">CHAMPION</div>
        <div class="iv-champion-name"><?= h($champion['nickname'] ?? $champion['name']) ?></div>
      </div>
    </div>
  <?php endif; ?>

  <!-- インタビュー編集フォーム -->
  <form method="post" action="interview_edit?id=<?= $tournamentId ?>" id="interview-form">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="save">

    <div class="iv-section">
      <div class="iv-section-title">質問と回答</div>
      <div class="iv-qa-list" id="qa-list"></div>
      <button type="button" class="iv-btn-add" id="btn-add-qa">+ 質問を追加</button>
    </div>


    <div class="iv-actions">
      <a href="tournament?id=<?= $tournamentId ?>" class="btn-cancel">&#x2190; 大会ページに戻る</a>
      <button type="submit" class="iv-btn-save">インタビューを保存</button>
    </div>
  </form>

  <?php if ($tournament['status'] !== TournamentStatus::Completed->value): ?>
    <!-- 大会完了 -->
    <div class="iv-complete-section">
      <div class="iv-complete-title">大会を完了する</div>
      <div class="iv-complete-desc">インタビューを保存した後、大会を完了できます。<br>完了した後も、インタビューの編集は可能です。</div>
      <form method="post" action="interview_edit?id=<?= $tournamentId ?>" data-confirm="大会を完了しますか？&#10;この操作は取り消せません。">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="complete">
    
        <button type="submit" class="iv-btn-complete">大会を完了する</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php
$pageInlineScript = <<<JS
(function() {
  var items = {$jsInterviews};
  var list = document.getElementById('qa-list');
  var btnAdd = document.getElementById('btn-add-qa');

  function render() {
    list.innerHTML = '';
    for (var i = 0; i < items.length; i++) {
      list.appendChild(createItem(i, items[i]));
    }
  }

  function createItem(idx, data) {
    var div = document.createElement('div');
    div.className = 'iv-qa-item';
    div.innerHTML =
      '<div class="iv-qa-header">' +
        '<span class="iv-qa-number">Q' + (idx + 1) + '</span>' +
        '<button type="button" class="iv-qa-remove" data-idx="' + idx + '" title="削除">&times;</button>' +
      '</div>' +
      '<div class="iv-qa-label">質問</div>' +
      '<input type="text" name="questions[]" class="iv-qa-input" value="' + esc(data.question) + '" placeholder="質問を入力...">' +
      '<div class="iv-qa-label" style="margin-top:10px">回答</div>' +
      '<textarea name="answers[]" class="iv-qa-input answer" placeholder="回答を入力...">' + esc(data.answer) + '</textarea>';

    div.querySelector('.iv-qa-remove').addEventListener('click', function() {
      syncFromDom();
      items.splice(idx, 1);
      render();
    });
    return div;
  }

  function syncFromDom() {
    var qInputs = list.querySelectorAll('input[name="questions[]"]');
    var aInputs = list.querySelectorAll('textarea[name="answers[]"]');
    for (var i = 0; i < qInputs.length; i++) {
      items[i] = { question: qInputs[i].value, answer: aInputs[i].value };
    }
  }

  btnAdd.addEventListener('click', function() {
    syncFromDom();
    items.push({ question: '', answer: '' });
    render();
    // 最後の質問入力にフォーカス
    var inputs = list.querySelectorAll('.iv-qa-input');
    if (inputs.length) inputs[inputs.length - 2].focus();
  });

  // フォーム送信前に現在の値をitemsに反映
  document.getElementById('interview-form').addEventListener('submit', function() {
    syncFromDom();
  });

  // 初回描画。空なら1つ追加
  if (items.length === 0) items.push({ question: '', answer: '' });
  render();

  function esc(s) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(s || ''));
    return d.innerHTML;
  }
})();
JS;

require __DIR__ . '/../templates/footer.php';
?>

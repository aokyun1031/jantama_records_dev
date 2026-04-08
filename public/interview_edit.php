<?php
declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

startSecureSession();
ensureCsrfToken();

// --- バリデーション ---
$tournamentId = requireTournamentId();
$tournament = requireTournamentWithMeta($tournamentId);

// 優勝者を取得
$champion = Standing::champion($tournamentId);

// 既存インタビューを取得
['data' => $interviews] = fetchData(fn() => Interview::byTournament($tournamentId));

$validationError = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        http_response_code(403);
        $validationError = '不正なリクエストです。ページを再読み込みしてください。';
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
            $currentItems = Interview::byTournament($tournamentId);
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
$pageCss = ['css/forms.css'];
$pageStyle = <<<'CSS'
.iv-hero { text-align: center; padding: 48px 20px 24px; }
.iv-badge { display: inline-block; background: var(--badge-bg); color: var(--badge-color); font-size: 0.7rem; font-weight: 700; padding: 4px 14px; border-radius: 20px; margin-bottom: 16px; letter-spacing: 2px; box-shadow: 0 2px 12px rgba(var(--accent-rgb),0.3); }
.iv-title { font-family: 'Noto Sans JP', sans-serif; font-size: clamp(1.4rem, 5vw, 2rem); font-weight: 900; color: var(--text); margin-bottom: 4px; }
.iv-subtitle { font-size: 0.85rem; color: var(--text-sub); }

.iv-content { max-width: 700px; margin: 0 auto 40px; padding: 0 16px; }

.iv-champion { display: flex; align-items: center; gap: 12px; padding: 16px 20px; margin-bottom: 20px; background: linear-gradient(135deg, rgba(var(--gold-rgb), 0.08), rgba(var(--accent-rgb), 0.03)); border: 1px solid rgba(var(--gold-rgb), 0.25); border-radius: var(--radius); }
.iv-champion-icon { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(var(--gold-rgb), 0.3); }
.iv-champion-info { flex: 1; }
.iv-champion-label { font-size: 0.7rem; font-weight: 700; color: var(--gold); letter-spacing: 1px; }
.iv-champion-name { font-size: 1.1rem; font-weight: 800; color: var(--text); }

.iv-section { background: var(--card); border: 1px solid rgba(var(--accent-rgb), 0.25); border-radius: var(--radius); padding: 24px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.iv-section-title { font-weight: 800; font-size: 0.95rem; color: var(--text); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid rgba(var(--accent-rgb), 0.15); display: flex; align-items: center; gap: 12px; }

.iv-qa-list { display: flex; flex-direction: column; gap: 16px; }
.iv-qa-item { background: rgba(var(--accent-rgb), 0.02); border: 1px solid var(--glass-border); border-radius: var(--radius-sm); padding: 16px; position: relative; }
.iv-qa-header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.iv-qa-number { font-size: 0.75rem; font-weight: 800; color: var(--purple); background: rgba(var(--accent-rgb), 0.1); padding: 2px 10px; border-radius: 10px; }
.iv-qa-remove { margin-left: auto; width: 28px; height: 28px; border: none; border-radius: 50%; background: rgba(var(--danger-rgb), 0.08); color: var(--danger); font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
.iv-qa-remove:hover { background: rgba(var(--danger-rgb), 0.2); }
.iv-qa-label { font-size: 0.8rem; font-weight: 700; color: var(--text-sub); margin-bottom: 6px; }
.iv-qa-input { width: 100%; padding: 10px 14px; border: 1.5px solid var(--input-border); border-radius: var(--radius-sm); font-size: 0.9rem; font-family: 'Noto Sans JP', sans-serif; background: var(--input-bg); color: var(--text); box-sizing: border-box; resize: vertical; transition: border-color 0.2s, box-shadow 0.2s; }
.iv-qa-input::placeholder { color: var(--input-placeholder); }
.iv-qa-input:hover:not(:disabled):not(:focus) { border-color: var(--input-border-hover); }
.iv-qa-input:focus { outline: none; border-color: var(--input-border-focus); box-shadow: var(--input-focus-ring); }
.iv-qa-input.answer { min-height: 80px; }

.iv-btn-add { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; background: var(--card); border: 2px dashed var(--glass-border); border-radius: var(--radius-sm); color: var(--text-sub); font-size: 0.85rem; font-weight: 700; font-family: 'Noto Sans JP', sans-serif; cursor: pointer; transition: border-color 0.2s, color 0.2s; width: 100%; justify-content: center; }
.iv-btn-add:hover { border-color: rgba(var(--accent-rgb), 0.4); color: var(--text); }

.iv-actions { display: flex; gap: 12px; justify-content: center; margin-top: 24px; flex-wrap: wrap; }
.iv-btn-save { padding: 12px 32px; background: var(--btn-primary-bg); color: var(--btn-text-color); border: none; border-radius: 12px; font-weight: 700; font-size: 0.9rem; font-family: 'Noto Sans JP', sans-serif; cursor: pointer; transition: transform 0.3s, box-shadow 0.3s; box-shadow: 0 4px 16px rgba(var(--accent-rgb), 0.3); }
.iv-btn-save:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(var(--accent-rgb), 0.4); }
.iv-btn-cancel { display: inline-flex; align-items: center; padding: 12px 24px; background: var(--card); color: var(--text-sub); border: 1px solid var(--glass-border); border-radius: 12px; font-weight: 700; font-size: 0.9rem; font-family: 'Noto Sans JP', sans-serif; text-decoration: none; transition: transform 0.3s; }
.iv-btn-cancel:hover { transform: translateY(-2px); }

.iv-complete-section { margin-top: 24px; padding: 24px; background: linear-gradient(135deg, rgba(var(--mint-rgb), 0.06), rgba(var(--accent-rgb), 0.03)); border: 2px solid rgba(var(--mint-rgb), 0.3); border-radius: var(--radius); text-align: center; }
.iv-complete-title { font-weight: 800; font-size: 0.95rem; color: var(--text); margin-bottom: 8px; }
.iv-complete-desc { font-size: 0.8rem; color: var(--text-sub); margin-bottom: 16px; }
.iv-btn-complete { padding: 12px 32px; background: var(--btn-secondary-bg); color: var(--btn-text-color); border: none; border-radius: 12px; font-weight: 700; font-size: 0.9rem; font-family: 'Noto Sans JP', sans-serif; cursor: pointer; transition: transform 0.3s, box-shadow 0.3s; box-shadow: 0 4px 16px rgba(var(--mint-rgb), 0.3); }
.iv-btn-complete:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(var(--mint-rgb), 0.4); }

CSS;

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
      <form method="post" action="interview_edit?id=<?= $tournamentId ?>" onsubmit="return confirm('大会を完了しますか？\nこの操作は取り消せません。')">
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

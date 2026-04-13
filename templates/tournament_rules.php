    <!-- イベント種別 -->
    <div class="edit-section">
      <div class="edit-label">イベント種別</div>
      <div class="edit-radio-group">
        <?php foreach (EventType::cases() as $et): ?>
          <label class="edit-radio-option">
            <input type="radio" name="event_type" value="<?= h($et->value) ?>" <?= $postEventType === $et->value ? 'checked' : '' ?>>
            <span class="edit-radio-label"><?= h($et->label()) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 大会名 -->
    <div class="edit-section">
      <label class="edit-label" for="input-name">大会名</label>
      <input type="text" id="input-name" name="name" class="edit-input" value="<?= h($postName) ?>" maxlength="100" required>
    </div>

    <!-- ルール設定 -->
    <div class="edit-section">
      <div class="edit-section-title">ルール設定</div>
      <div class="edit-hint" style="margin-bottom: 16px;">卓作成時に変更可能です。</div>

      <div class="edit-field">
        <div class="edit-label">対局人数</div>
        <div class="edit-radio-group">
          <?php foreach (PlayerMode::cases() as $pm): ?>
            <label class="edit-radio-option">
              <input type="radio" name="player_mode" value="<?= h($pm->value) ?>" <?= $postPlayerMode === $pm->value ? 'checked' : '' ?>>
              <span class="edit-radio-label"><?= h($pm->fullLabel()) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="edit-field">
        <div class="edit-label">局数</div>
        <div class="edit-radio-group">
          <?php foreach (RoundType::cases() as $rt): ?>
            <label class="edit-radio-option">
              <input type="radio" name="round_type" value="<?= h($rt->value) ?>" <?= $postRoundType === $rt->value ? 'checked' : '' ?>>
              <span class="edit-radio-label"><?= h($rt->label()) ?>戦</span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="edit-field">
        <label class="edit-label" for="select-thinking-time">長考時間</label>
        <select id="select-thinking-time" name="thinking_time" class="edit-select">
          <option value="3+5" <?= $postThinkingTime === '3+5' ? 'selected' : '' ?>>3+5秒</option>
          <option value="5+10" <?= $postThinkingTime === '5+10' ? 'selected' : '' ?>>5+10秒</option>
          <option value="5+20" <?= $postThinkingTime === '5+20' ? 'selected' : '' ?>>5+20秒（標準）</option>
          <option value="60+0" <?= $postThinkingTime === '60+0' ? 'selected' : '' ?>>60+0秒</option>
          <option value="300+0" <?= $postThinkingTime === '300+0' ? 'selected' : '' ?>>300+0秒</option>
        </select>
      </div>

      <div class="edit-field">
        <div class="edit-label">配給原点 / 返し点</div>
        <div class="points-row">
          <div class="points-field">
            <input type="number" name="starting_points" class="edit-input" value="<?= h($postStartingPoints) ?>" min="100" max="200000" step="100" required>
            <div class="edit-hint">配給原点</div>
          </div>
          <div class="points-field">
            <input type="number" name="return_points" class="edit-input" value="<?= h($postReturnPoints) ?>" min="100" max="200000" step="100" required>
            <div class="edit-hint">返し点</div>
          </div>
        </div>
      </div>

      <div class="edit-field">
        <div class="edit-label">赤ドラ</div>
        <div class="edit-radio-group">
          <label class="edit-radio-option">
            <input type="radio" name="red_dora" value="0" <?= $postRedDora === '0' ? 'checked' : '' ?>>
            <span class="edit-radio-label">赤無し</span>
          </label>
          <label class="edit-radio-option">
            <input type="radio" name="red_dora" value="3" <?= $postRedDora === '3' ? 'checked' : '' ?>>
            <span class="edit-radio-label">赤ドラ3</span>
          </label>
          <label class="edit-radio-option">
            <input type="radio" name="red_dora" value="4" <?= $postRedDora === '4' ? 'checked' : '' ?>>
            <span class="edit-radio-label">赤ドラ4</span>
          </label>
        </div>
      </div>

      <div class="edit-field">
        <div class="edit-label">喰いタン</div>
        <div class="edit-radio-group">
          <label class="edit-radio-option">
            <input type="radio" name="open_tanyao" value="1" <?= $postOpenTanyao === '1' ? 'checked' : '' ?>>
            <span class="edit-radio-label">有効</span>
          </label>
          <label class="edit-radio-option">
            <input type="radio" name="open_tanyao" value="0" <?= $postOpenTanyao === '0' ? 'checked' : '' ?>>
            <span class="edit-radio-label">無効</span>
          </label>
        </div>
      </div>

      <div class="edit-field">
        <div class="edit-label">翻縛り</div>
        <div class="edit-radio-group">
          <?php foreach (HanRestriction::cases() as $hr): ?>
            <label class="edit-radio-option">
              <input type="radio" name="han_restriction" value="<?= h($hr->value) ?>" <?= $postHanRestriction === $hr->value ? 'checked' : '' ?>>
              <span class="edit-radio-label"><?= h($hr->label()) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="edit-field">
        <div class="edit-label">トビ</div>
        <div class="edit-radio-group">
          <label class="edit-radio-option">
            <input type="radio" name="bust" value="1" <?= $postBust === '1' ? 'checked' : '' ?>>
            <span class="edit-radio-label">あり</span>
          </label>
          <label class="edit-radio-option">
            <input type="radio" name="bust" value="0" <?= $postBust === '0' ? 'checked' : '' ?>>
            <span class="edit-radio-label">なし</span>
          </label>
        </div>
      </div>
    </div>

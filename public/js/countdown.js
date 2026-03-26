// countdown.js
document.addEventListener('DOMContentLoaded', () => {
  const countdownContainer = document.getElementById('countdown-container');
  const countdownText = document.getElementById('countdown-text');

  if (!countdownContainer || !countdownText) return;

  const now = new Date();

  // 今日の日付 (時間を0時0分0秒にリセットして日付のみで比較)
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  // 決勝日: 今年の3月22日 (月は0始まりのため2=3月)
  const finalDay = new Date(now.getFullYear(), 2, 22);

  // 差分を計算して日数に変換
  const diffTime = finalDay - today;
  const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays > 0) {
    // 開催前
    countdownText.innerHTML = `開催まであと<span class="days">${diffDays}</span>日！`;
  } else if (diffDays === 0) {
    // 当日
    countdownText.innerHTML = `大会は終了しました`;
    countdownContainer.classList.add('finished-container');
    countdownText.classList.add('finished');
  } else {
    // 終了後（23日以降）
    countdownText.innerHTML = `大会は終了しました`;
    countdownContainer.classList.add('finished-container');
    countdownText.classList.add('finished');
  }
});
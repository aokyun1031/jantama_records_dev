import type { FullConfig } from '@playwright/test';

/**
 * 全テスト実行前に DB をウォームアップする。
 *
 * テストの既定接続先はローカル docker-compose の db コンテナ（.env の DATABASE_URL）。
 * Neon dev ブランチに向けた場合はスリープ状態からの復帰に数秒〜十数秒かかるため、
 * 軽量ページに事前アクセスして接続を確立しておく。
 */
async function globalSetup(config: FullConfig): Promise<void> {
  const baseURL = config.projects[0]?.use?.baseURL || 'http://localhost:8080';
  const maxRetries = 3;

  console.log(`DB warmup: ${baseURL}/players にアクセス中...`);
  for (let i = 0; i < maxRetries; i++) {
    try {
      const response = await fetch(`${baseURL}/players`, { signal: AbortSignal.timeout(30000) });
      if (response.ok) {
        console.log('DB warmup: 完了');
        return;
      }
      console.log(`DB warmup: レスポンス ${response.status}、リトライ ${i + 1}/${maxRetries}`);
    } catch {
      console.log(`DB warmup: 接続失敗、リトライ ${i + 1}/${maxRetries}`);
    }
    if (i < maxRetries - 1) {
      await new Promise((r) => setTimeout(r, 2000));
    }
  }
  console.warn('DB warmup: 全リトライ失敗。最初のテストが遅くなる可能性あり');
}

export default globalSetup;

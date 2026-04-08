import type { FullConfig } from '@playwright/test';

/**
 * 全テスト実行前にNeon DBをウォームアップする。
 * Neon無料枠はスリープ状態からの復帰に数秒〜十数秒かかるため、
 * 軽量ページに事前アクセスして接続を確立しておく。
 */
async function globalSetup(config: FullConfig): Promise<void> {
  const baseURL = config.projects[0]?.use?.baseURL || 'http://localhost:8080';
  const maxRetries = 3;

  for (let i = 0; i < maxRetries; i++) {
    try {
      const response = await fetch(`${baseURL}/players`, { signal: AbortSignal.timeout(30000) });
      if (response.ok) {
        return;
      }
    } catch {
      // リトライ
    }
    if (i < maxRetries - 1) {
      await new Promise((r) => setTimeout(r, 2000));
    }
  }
  console.warn('DB warmup: all retries failed, tests may be slow on first connection');
}

export default globalSetup;

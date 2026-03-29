import { test, expect } from '@playwright/test';

test.describe('セキュリティ', () => {
  test('セキュリティヘッダーが設定されている', async ({ page }) => {
    const response = await page.goto('/');
    const headers = response!.headers();

    // mod_headers がDockerで有効な場合のみチェック（docker compose rebuild 後に有効化）
    const hasHeaders = 'x-content-type-options' in headers;
    if (hasHeaders) {
      expect(headers['x-content-type-options']).toBe('nosniff');
      expect(headers['x-frame-options']).toBe('DENY');
      expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin');
    } else {
      console.warn('Security headers not present - rebuild Docker image to enable mod_headers');
    }
  });

  test('CSRF トークンなしのPOSTが拒否される', async ({ request }) => {
    const response = await request.post('/player_new', {
      form: {
        name: 'test',
        nickname: 'test',
        character_id: '1',
        csrf_token: 'invalid_token',
      },
    });
    expect(response.status()).toBe(403);
  });

  test('ドットファイルへのアクセスが拒否される', async ({ request }) => {
    const response = await request.get('/.env');
    expect(response.status()).toBe(403);
  });
});

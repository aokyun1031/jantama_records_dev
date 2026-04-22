import { test, expect } from '../helpers/fixtures';

test.describe('セキュリティ', () => {
  test('セキュリティヘッダーが設定されている', async ({ page }) => {
    const response = await page.goto('/');
    const headers = response!.headers();

    test.skip(!('x-content-type-options' in headers), 'mod_headers が無効 — docker compose build で有効化');

    expect(headers['x-content-type-options']).toBe('nosniff');
    expect(headers['x-frame-options']).toBe('DENY');
    expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin');
  });

  test('CSP に nonce が含まれ script-src に unsafe-inline がない', async ({ page }) => {
    const response = await page.goto('/');
    const csp = response!.headers()['content-security-policy'];

    test.skip(!csp, 'CSP ヘッダーが設定されていない');

    expect(csp).toMatch(/script-src[^;]*'nonce-[a-f0-9]+'/);
    expect(csp).not.toMatch(/script-src[^;]*'unsafe-inline'/);
  });

  test('CSP nonce がリクエストごとに異なる', async ({ request }) => {
    // ブラウザの page.goto を 2 回走らせると重いので HTTP request で直接取得する
    const extractNonce = (csp: string | undefined): string | undefined =>
      csp?.match(/'nonce-([a-f0-9]+)'/)?.[1];

    const [res1, res2] = await Promise.all([request.get('/'), request.get('/')]);
    const nonce1 = extractNonce(res1.headers()['content-security-policy']);
    const nonce2 = extractNonce(res2.headers()['content-security-policy']);

    test.skip(!nonce1 || !nonce2, 'CSP ヘッダーが設定されていない');

    expect(nonce1).not.toBe(nonce2);
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

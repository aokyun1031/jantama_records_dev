import { test, expect } from '../helpers/fixtures';

test.describe('ヘルスチェック', () => {
  test('/health は 200 で "ok" を返す', async ({ request }) => {
    const res = await request.get('/health');
    expect(res.status()).toBe(200);
    expect(await res.text()).toBe('ok');
  });

  test('/health は text/plain で返す', async ({ request }) => {
    const res = await request.get('/health');
    expect(res.headers()['content-type']).toContain('text/plain');
  });
});

import path from 'node:path';

/**
 * E2E 専用スタック（リポジトリ直下の docker-compose.test.yml）の接続情報。
 *
 * テストの接続先はこのスタックに固定している。任意の URL へ向けられるようにすると、
 * 隔離を確認していない環境（開発用スタックや外部の DB につながった環境）に対して
 * 書き込みテストを流してしまうため、接続先を切り替える環境変数は用意していない。
 * 変えられるのはホスト側のポート番号だけ。
 */
export const E2E_PORT = process.env.E2E_PORT || '8081';
export const BASE_URL = `http://localhost:${E2E_PORT}`;
export const COMPOSE_FILE = path.resolve(__dirname, '../../../docker-compose.test.yml');

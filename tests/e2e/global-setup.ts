import { execFileSync } from 'node:child_process';
import { BASE_URL, COMPOSE_FILE } from './helpers/stack';

/**
 * 隔離できているとみなす診断結果。1 つでも違えばテストを 1 件も実行せずに中断する。
 * 各項目の意味は tests/e2e/support/e2e-canary.php を参照。
 */
const EXPECTED_CANARY: Record<string, string | boolean> = {
  discord_enabled: false,
  discord_oauth_configured: false,
  discord_host: '127.0.0.1',
  db_host: 'db',
  is_production: false,
  env_file_present: false,
};

function compose(args: string[]): void {
  execFileSync('docker', ['compose', '-f', COMPOSE_FILE, ...args], { stdio: 'inherit' });
}

async function assertIsolated(): Promise<void> {
  const response = await fetch(`${BASE_URL}/__e2e_canary`, { signal: AbortSignal.timeout(30000) });
  if (!response.ok) {
    throw new Error(
      `隔離の確認に失敗: ${BASE_URL}/__e2e_canary が ${response.status} を返しました。` +
        '接続先が E2E 専用スタックではない可能性があります。'
    );
  }
  const actual = (await response.json()) as Record<string, unknown>;
  const mismatches = Object.entries(EXPECTED_CANARY)
    .filter(([key, expected]) => actual[key] !== expected)
    .map(([key, expected]) => `${key}: 期待 ${JSON.stringify(expected)} / 実際 ${JSON.stringify(actual[key])}`);
  if (mismatches.length > 0) {
    throw new Error(`E2E スタックが隔離されていないため中断します。\n  ${mismatches.join('\n  ')}`);
  }
}

/**
 * 全テストの前に、E2E 専用スタックを現在のコードで起動し直し、データを初期状態に戻し、
 * 隔離されていることを確認する。どれかに失敗したらテストは実行されない。
 *
 * - 起動: コードはイメージに焼くので、毎回 --build する（変更が無ければキャッシュで数秒）
 * - データ: seed:run が全テーブルを TRUNCATE して db/seed_data.sql を入れ直す。
 *   前回の実行で作られたテストデータは残らないので、後始末（teardown）は持たない
 */
async function globalSetup(): Promise<void> {
  console.log('E2E スタックを起動中（docker-compose.test.yml）...');
  compose(['up', '-d', '--build', '--wait']);

  console.log('シードデータを投入中...');
  compose(['exec', '-T', 'web', 'php', 'vendor/bin/phinx', 'seed:run']);

  await assertIsolated();
  console.log(`E2E スタックの準備完了: ${BASE_URL}`);
}

export default globalSetup;

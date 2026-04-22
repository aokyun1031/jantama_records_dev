import pg from 'pg';
import dotenv from 'dotenv';
import path from 'path';
import { TEST_PREFIX } from './helpers/constants';

dotenv.config({ path: path.resolve(__dirname, '../../.env') });

/**
 * 全テスト完了後にテストデータを一括削除する。
 * CASCADE DELETE により関連テーブル（standings, round_results,
 * tables_info, table_players, tournament_meta, interviews 等）も自動削除される。
 */
async function globalTeardown(): Promise<void> {
  const databaseUrl = process.env.DATABASE_URL;
  if (!databaseUrl) {
    console.warn('globalTeardown: DATABASE_URL が未設定。クリーンアップをスキップ');
    return;
  }

  // .env の DATABASE_URL はローカル docker-compose 用に `db:5432` を指す。
  // globalTeardown は WSL ホスト側から動くため、compose ネットワーク名 `db` は
  // 解決できない → `localhost` に置換する。Neon 等の外部ホストでは no-op。
  const connectionString = databaseUrl.replace('@db:', '@localhost:');

  const client = new pg.Client({ connectionString });
  try {
    await client.connect();

    // 大会削除（CASCADE で standings, round_results, tables_info, table_players,
    // tournament_meta, interviews, table_paifu_urls が自動削除）
    const tournaments = await client.query(
      `DELETE FROM tournaments WHERE name LIKE $1 RETURNING id`,
      [`${TEST_PREFIX}%`]
    );

    // 選手削除（CASCADE で残存 round_results, standings, table_players が自動削除）
    const players = await client.query(
      `DELETE FROM players WHERE name LIKE $1 RETURNING id`,
      [`${TEST_PREFIX}%`]
    );

    const tCount = tournaments.rowCount ?? 0;
    const pCount = players.rowCount ?? 0;
    if (tCount > 0 || pCount > 0) {
      console.log(`globalTeardown: 大会 ${tCount}件、選手 ${pCount}件を削除`);
    }
  } catch (e) {
    console.warn('globalTeardown: クリーンアップ失敗', e);
  } finally {
    await client.end();
  }
}

export default globalTeardown;

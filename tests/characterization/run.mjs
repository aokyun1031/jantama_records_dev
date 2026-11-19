#!/usr/bin/env node
// 現行モデルの戻り値の記録（golden）を作る・照合する。
//
//   node tests/characterization/run.mjs capture   記録を作り直して golden/legacy/seed.jsonl に保存する
//   node tests/characterization/run.mjs check     今のコードの出力を、保存済みの記録と照合する
//   node tests/characterization/run.mjs check <file>   別の環境で書き出した結果のファイルを照合する
//
// どちらも E2E 専用スタック（docker-compose.test.yml）を現在のコードで起動し、シードを入れ直してから
// コンテナ内で legacy-export.php を実行する。依存パッケージは無い（Node と Docker だけで動く）。
//
// 記録の 1 行は 1 呼び出し。現行コードには、同じデータでも結果が変わる呼び出しがある
// （同点のときの並び順を SQL が決めていない、など）。capture は実行計画と行の物理的な並びを
// 変えながら複数回書き出し、結果が変わった呼び出しに印を付けて保存する。
//
//   {"call","args","result"}                          毎回同じ結果。照合は完全一致
//   {"call","args","unstable":"order","result"}       並び順だけが変わる。result は並び順を正規化した値
//   {"call","args","unstable":"content","variants"}   内容が変わる。variants は観測した結果（正規化済み）の一覧

import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const composeFile = path.resolve(here, '../../docker-compose.test.yml');
const goldenFile = path.join(here, 'golden/legacy/seed.jsonl');
const MAX_REPORTED = 20;

/**
 * capture で試す条件。SQL が並び順や選ぶ行を決めていない箇所は、実行計画か行の物理的な並びが
 * 変わると結果が変わる。両方を意図的に変えて、そういう呼び出しを洗い出す。
 *   pgOptions: PGOPTIONS で渡すプランナ設定（空なら既定）
 *   shuffle:   書き出しの前に、各テーブルの約半数の行を値を変えずに UPDATE して物理的な位置を動かす
 */
const CONDITIONS = [
  { name: '既定', pgOptions: '', shuffle: false },
  { name: '行の並びを変更 A', pgOptions: '', shuffle: true },
  { name: 'ハッシュ集約・ハッシュ結合なし', pgOptions: '-c enable_hashagg=off -c enable_hashjoin=off', shuffle: false },
  { name: '行の並びを変更 B + シーケンシャルスキャンなし', pgOptions: '-c enable_seqscan=off', shuffle: true },
  {
    name: 'インデックススキャンなし',
    pgOptions: '-c enable_indexscan=off -c enable_indexonlyscan=off -c enable_bitmapscan=off',
    shuffle: false,
  },
  { name: '行の並びを変更 C + マージ結合・ネストループなし', pgOptions: '-c enable_mergejoin=off -c enable_nestloop=off', shuffle: true },
  { name: 'ソートなし', pgOptions: '-c enable_sort=off', shuffle: false },
  { name: '行の並びを変更 D', pgOptions: '', shuffle: true },
];

function compose(args, options = {}) {
  return execFileSync('docker', ['compose', '-f', composeFile, ...args], {
    encoding: 'utf8',
    maxBuffer: 256 * 1024 * 1024,
    stdio: ['ignore', 'pipe', 'inherit'],
    ...options,
  });
}

function reseed() {
  compose(['exec', '-T', 'web', 'php', 'vendor/bin/phinx', 'seed:run'], { stdio: 'ignore' });
}

/** 各テーブルの約半数の行を、値を変えずに UPDATE する（行の物理的な位置だけが変わる）。 */
function shuffleRows(salt) {
  const sql = `
    DO $$
    DECLARE r record;
    BEGIN
      FOR r IN
        SELECT c.table_name, min(c.column_name) AS column_name
        FROM information_schema.columns c
        JOIN information_schema.tables t USING (table_schema, table_name)
        WHERE c.table_schema = 'public' AND t.table_type = 'BASE TABLE'
          AND c.table_name <> 'phinxlog' AND c.is_identity = 'NO'
        GROUP BY c.table_name
      LOOP
        EXECUTE format(
          'UPDATE %I s SET %I = %I WHERE (hashtext(s::text || %L) & 1) = 0',
          r.table_name, r.column_name, r.column_name, '${salt}'
        );
      END LOOP;
    END $$;`;
  compose(['exec', '-T', 'db', 'psql', '-U', 'postgres', '-d', 'jantama', '-v', 'ON_ERROR_STOP=1', '-q'], {
    input: sql,
    stdio: ['pipe', 'ignore', 'inherit'],
  });
}

/** 書き出しを 1 回実行し、「呼び出し + 引数」をキーにした Map（値は解析済みの行）を返す。 */
function exportOnce(pgOptions = '') {
  const env = pgOptions === '' ? [] : ['-e', `PGOPTIONS=${pgOptions}`];
  return parseExport(compose(['exec', '-T', ...env, 'web', 'php', 'tests/characterization/legacy-export.php']));
}

/** legacy-export.php の出力（JSON Lines）を、「呼び出し + 引数」をキーにした Map にする。 */
function parseExport(jsonl) {
  const map = new Map();
  for (const line of jsonl.split('\n')) {
    if (line === '') continue;
    const entry = JSON.parse(line);
    const key = `${entry.call}(${JSON.stringify(entry.args).slice(1, -1)})`;
    if (map.has(key)) throw new Error(`同じ呼び出しが 2 回書き出されています: ${key}`);
    map.set(key, entry);
  }
  return map;
}

/** 並び順に依存しない形にする: 配列は要素を正規化してから並べ替え、オブジェクトはキー順にする。 */
function normalize(value) {
  if (Array.isArray(value)) {
    return value
      .map((item) => {
        const normalized = normalize(item);
        return [JSON.stringify(normalized), normalized];
      })
      .sort((a, b) => (a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : 0))
      .map(([, normalized]) => normalized);
  }
  if (value !== null && typeof value === 'object') {
    return Object.fromEntries(Object.keys(value).sort().map((key) => [key, normalize(value[key])]));
  }
  return value;
}

/** 記録に残す値。例外とメソッドなしも結果として扱う。 */
function outcomeOf(entry) {
  if (entry.missing) return { missing: true };
  if (entry.error) return { error: entry.error };
  return { result: entry.result };
}

function capture() {
  reseed();
  const runs = [];
  CONDITIONS.forEach((condition, i) => {
    if (condition.shuffle) shuffleRows(`shuffle-${i}`);
    const startedAt = Date.now();
    runs.push(exportOnce(condition.pgOptions));
    const seconds = Math.round((Date.now() - startedAt) / 1000);
    console.log(`  書き出し ${i + 1}/${CONDITIONS.length}: ${condition.name}（${seconds} 秒）`);
  });
  reseed();

  // 1 回目の走査: 呼び出しごとに、条件によって結果が変わったかを調べる
  const observed = [];
  const unstableByMethod = new Map();
  for (const [key, first] of runs[0]) {
    const outcomes = runs.map((run) => {
      if (!run.has(key)) throw new Error(`条件によって書き出される呼び出しが違います: ${key}`);
      return outcomeOf(run.get(key));
    });
    const raw = new Set(outcomes.map((o) => JSON.stringify(o)));
    const normalized = [...new Set(outcomes.map((o) => JSON.stringify(normalize(o))))].sort();
    const kind = raw.size === 1 ? 'stable' : normalized.length === 1 ? 'order' : 'content';
    observed.push({ first, outcome: outcomes[0], normalized, kind });

    if (kind !== 'stable') {
      const counts = unstableByMethod.get(first.call) ?? { order: 0, content: 0, inherited: 0 };
      counts[kind]++;
      unstableByMethod.set(first.call, counts);
    }
  }

  // 2 回目の走査: 記録の行を作る。結果が変わる原因は SQL（メソッド）にあるので、1 件でも変わった
  // メソッドは、今回たまたま変わらなかった呼び出しも並び順に依存しない形で記録する。
  const lines = observed.map(({ first, outcome, normalized, kind }) => {
    const line = { call: first.call, args: first.args };
    if (first.sorted_by !== undefined) line.sorted_by = first.sorted_by;

    if (kind === 'content') {
      line.unstable = 'content';
      line.variants = normalized.map((v) => JSON.parse(v));
    } else if (kind === 'order' || unstableByMethod.has(first.call)) {
      if (kind === 'stable') unstableByMethod.get(first.call).inherited++;
      line.unstable = 'order';
      Object.assign(line, JSON.parse(normalized[0]));
    } else {
      Object.assign(line, outcome);
    }
    return JSON.stringify(line);
  });

  mkdirSync(path.dirname(goldenFile), { recursive: true });
  writeFileSync(goldenFile, lines.join('\n') + '\n');
  console.log(`保存しました: ${path.relative(process.cwd(), goldenFile)}（${lines.length} 呼び出し）`);

  if (unstableByMethod.size === 0) {
    console.log('条件を変えても結果が変わる呼び出しはありませんでした。');
    return;
  }
  console.log('条件によって結果が変わったメソッド（呼び出し数）:');
  for (const [method, counts] of [...unstableByMethod].sort()) {
    console.log(
      `  ${method}: 並び順だけ ${counts.order} / 内容も ${counts.content} / 今回は変わらなかった ${counts.inherited}`
    );
  }
}

/**
 * @param {string | undefined} exportFile 指定すると、書き出しを実行せずにそのファイルを照合する
 *   （別の PHP バージョンのイメージなど、このスタックの外で legacy-export.php を実行した結果の照合用）
 */
function check(exportFile) {
  let current;
  if (exportFile === undefined) {
    reseed();
    current = exportOnce();
  } else {
    current = parseExport(readFileSync(exportFile, 'utf8'));
  }
  const differences = [];
  let total = 0;

  for (const text of readFileSync(goldenFile, 'utf8').split('\n')) {
    if (text === '') continue;
    total++;
    const golden = JSON.parse(text);
    const key = `${golden.call}(${JSON.stringify(golden.args).slice(1, -1)})`;
    if (!current.has(key)) {
      differences.push({ key, kind: '記録にあるが今回は無い' });
      continue;
    }
    const outcome = outcomeOf(current.get(key));
    current.delete(key);
    const actual = JSON.stringify(normalize(outcome));

    if (golden.unstable === 'content') {
      const allowed = golden.variants.map((v) => JSON.stringify(v));
      if (!allowed.includes(actual)) {
        differences.push({ key, kind: '記録済みのどの結果とも違う', expected: allowed.join(' | '), actual });
      }
    } else if (golden.unstable === 'order') {
      const expected = JSON.stringify(outcomeOf(golden));
      if (expected !== actual) differences.push({ key, kind: '内容が違う', expected, actual });
    } else {
      const expected = JSON.stringify(outcomeOf(golden));
      const exact = JSON.stringify(outcome);
      if (expected !== exact) {
        const orderOnly = JSON.stringify(normalize(outcomeOf(golden))) === actual;
        differences.push({
          key,
          kind: orderOnly ? '並び順だけが違う（記録では安定していた呼び出し）' : '内容が違う',
          expected,
          actual: exact,
        });
      }
    }
  }
  for (const key of current.keys()) {
    differences.push({ key, kind: '今回あるが記録に無い' });
  }

  if (differences.length === 0) {
    console.log(`記録と一致しました（${total} 呼び出し）。`);
    return;
  }
  console.error(`記録と一致しません（${differences.length} / ${total} 呼び出し）。`);
  for (const d of differences.slice(0, MAX_REPORTED)) {
    console.error(`  [${d.kind}] ${d.key}`);
    if (d.expected !== undefined) {
      console.error(`      記録: ${d.expected.slice(0, 300)}`);
      console.error(`      今回: ${d.actual.slice(0, 300)}`);
    }
  }
  if (differences.length > MAX_REPORTED) {
    console.error(`  ... ほか ${differences.length - MAX_REPORTED} 件`);
  }
  process.exit(1);
}

const [mode, exportFile] = process.argv.slice(2);
if ((mode !== 'capture' && mode !== 'check') || (mode === 'capture' && exportFile !== undefined)) {
  console.error('使い方: node tests/characterization/run.mjs capture');
  console.error('        node tests/characterization/run.mjs check [書き出し済みの JSON Lines ファイル]');
  process.exit(64);
}

if (exportFile === undefined) {
  console.log('E2E 専用スタックを起動中（docker-compose.test.yml）...');
  compose(['up', '-d', '--build', '--wait'], { stdio: ['ignore', 'ignore', 'inherit'] });
}

if (mode === 'capture') capture();
else check(exportFile);

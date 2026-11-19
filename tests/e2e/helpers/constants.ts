/**
 * テストデータ識別用プレフィックス。シード由来のデータと見分けるために付ける。
 * テストデータの後始末はしない（global-setup が毎回シードを入れ直すので次の実行には残らない）。
 */
export const TEST_PREFIX = '__E2E_TEST_';

/**
 * 404 検証で使う、実在しないはずの ID。
 */
export const NONEXISTENT_ID = 999999;

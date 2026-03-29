<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenamePlayersToOfficialNames extends AbstractMigration
{
    public function up(): void
    {
        // アプリ内の正式名称に変更
        // シーダー名と現在のDB名が異なる可能性があるため、両方をカバー
        $this->execute("
            UPDATE players SET name = CASE
                WHEN name = 'あーす' THEN 'あーすじゃ'
                WHEN name IN ('あき', 'アキ') THEN '天高馬肥'
                WHEN name = 'あはん' THEN 'ahaaaaaaaan'
                WHEN name = 'イラチ' THEN 'jmas'
                WHEN name IN ('がう', 'がぞう') THEN 'がうさーん'
                WHEN name IN ('がちゃ', 'がちゃむん') THEN 'がちゃむん'
                WHEN name = 'ぎり' THEN 'ぎり。'
                WHEN name = 'けちゃこ' THEN '青いけちゃっぷ'
                WHEN name = 'こいぬ' THEN 'millin_waltz'
                WHEN name = 'シーマ' THEN 'DJc1ma'
                WHEN name = 'するが' THEN 'xするがx'
                WHEN name = 'そぼろ' THEN 'そぼろ丼'
                WHEN name = 'なぎ' THEN 'nagiya0211'
                WHEN name = 'ぶる' THEN 'ぶりゅー3'
                WHEN name = 'ホロホロ' THEN 'ホロ・ホロ'
                WHEN name = 'みーた' THEN 'みーた姫'
                WHEN name IN ('みか', 'ミカ') THEN 'janwich'
                WHEN name = 'りあ' THEN 'たべ田りあん'
                WHEN name IN ('梅', '梅ちゃん') THEN '梅おかか413'
                ELSE name
            END
        ");

        // tournament_metaのrecord_playerも更新
        $this->execute("
            UPDATE tournament_meta SET value = 'xするがx'
            WHERE key = 'record_player' AND value IN ('するが', 'xするがx')
        ");
    }

    public function down(): void
    {
        // シーダーの元の名前に戻す
        $this->execute("
            UPDATE players SET name = CASE
                WHEN name = 'あーすじゃ' THEN 'あーす'
                WHEN name = '天高馬肥' THEN 'あき'
                WHEN name = 'ahaaaaaaaan' THEN 'あはん'
                WHEN name = 'jmas' THEN 'イラチ'
                WHEN name = 'がうさーん' THEN 'がう'
                WHEN name = 'がちゃむん' THEN 'がちゃ'
                WHEN name = 'ぎり。' THEN 'ぎり'
                WHEN name = '青いけちゃっぷ' THEN 'けちゃこ'
                WHEN name = 'millin_waltz' THEN 'こいぬ'
                WHEN name = 'DJc1ma' THEN 'シーマ'
                WHEN name = 'xするがx' THEN 'するが'
                WHEN name = 'そぼろ丼' THEN 'そぼろ'
                WHEN name = 'nagiya0211' THEN 'なぎ'
                WHEN name = 'ぶりゅー3' THEN 'ぶる'
                WHEN name = 'ホロ・ホロ' THEN 'ホロホロ'
                WHEN name = 'みーた姫' THEN 'みーた'
                WHEN name = 'janwich' THEN 'みか'
                WHEN name = 'たべ田りあん' THEN 'りあ'
                WHEN name = '梅おかか413' THEN '梅'
                ELSE name
            END
        ");

        $this->execute("
            UPDATE tournament_meta SET value = 'するが'
            WHERE key = 'record_player' AND value = 'xするがx'
        ");
    }
}

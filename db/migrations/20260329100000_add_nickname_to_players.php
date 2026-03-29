<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddNicknameToPlayers extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE players ADD COLUMN nickname VARCHAR(50) DEFAULT NULL");

        $this->execute("
            UPDATE players SET nickname = CASE name
                WHEN 'あーすじゃ' THEN 'あーす'
                WHEN '天高馬肥' THEN 'アキ'
                WHEN 'ahaaaaaaaan' THEN 'あはん'
                WHEN 'jmas' THEN 'イラチ'
                WHEN 'がうさーん' THEN 'がぞう'
                WHEN 'がちゃむん' THEN 'がちゃむん'
                WHEN 'ぎり。' THEN 'ぎり'
                WHEN '青いけちゃっぷ' THEN 'けちゃこ'
                WHEN 'millin_waltz' THEN 'こいぬ'
                WHEN 'DJc1ma' THEN 'シーマ'
                WHEN 'xするがx' THEN 'するが'
                WHEN 'そぼろ丼' THEN 'そぼろ'
                WHEN 'nagiya0211' THEN 'なぎ'
                WHEN 'ぱーらめんこ' THEN 'ぱーらめんこ'
                WHEN 'ぶりゅー3' THEN 'ぶる'
                WHEN 'ホロ・ホロ' THEN 'ホロホロ'
                WHEN 'みーた姫' THEN 'みーた'
                WHEN 'janwich' THEN 'ミカ'
                WHEN 'たべ田りあん' THEN 'りあ'
                WHEN '梅おかか413' THEN '梅ちゃん'
                ELSE NULL
            END
        ");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE players DROP COLUMN nickname");
    }
}

<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedCharactersAndAssignPlayers extends AbstractMigration
{
    public function up(): void
    {
        // キャラクターマスタ登録
        $this->execute("
            INSERT INTO characters (name, icon_filename) VALUES
                ('ワン次郎', 'ワン次郎.png'),
                ('未来', '未来.png'),
                ('篠原ミア', '篠原ミア.png'),
                ('福姫', '福姫.png'),
                ('陸八間アル', NULL),
                ('三上千織', '三上千織.png'),
                ('一姫', '一姫.png'),
                ('イヴ・クリス', 'イブクリス.png'),
                ('かぐや姫', 'かぐや姫.png'),
                ('東城玄音', '東城玄音.png'),
                ('ミラ', NULL),
                ('玖辻', '玖辻.png'),
                ('寺崎千穂理', '寺崎千穂理.png'),
                ('藤本キララ', '藤本キララ.png'),
                ('二之宮花', '二之宮花.png'),
                ('琳琅', '琳琅.png'),
                ('北原リリィ', '北原リリイ.png'),
                ('セイバー', NULL),
                ('花語青', '花語青.png')
        ");

        // 選手とキャラクターの紐付け
        $this->execute("
            UPDATE players SET character_id = c.id
            FROM characters c
            WHERE (players.name = 'あーすじゃ' AND c.name = 'ワン次郎')
               OR (players.name = '天高馬肥' AND c.name = '未来')
               OR (players.name = 'ahaaaaaaaan' AND c.name = '篠原ミア')
               OR (players.name = 'jmas' AND c.name = '福姫')
               OR (players.name = 'がうさーん' AND c.name = '陸八間アル')
               OR (players.name = 'がちゃむん' AND c.name = '三上千織')
               OR (players.name = 'ぎり。' AND c.name = '福姫')
               OR (players.name = '青いけちゃっぷ' AND c.name = '一姫')
               OR (players.name = 'millin_waltz' AND c.name = 'イヴ・クリス')
               OR (players.name = 'DJc1ma' AND c.name = 'かぐや姫')
               OR (players.name = 'xするがx' AND c.name = '東城玄音')
               OR (players.name = 'そぼろ丼' AND c.name = 'ミラ')
               OR (players.name = 'nagiya0211' AND c.name = '玖辻')
               OR (players.name = 'ぱーらめんこ' AND c.name = '寺崎千穂理')
               OR (players.name = 'ぶりゅー3' AND c.name = '藤本キララ')
               OR (players.name = 'ホロ・ホロ' AND c.name = '二之宮花')
               OR (players.name = 'みーた姫' AND c.name = '琳琅')
               OR (players.name = 'janwich' AND c.name = '北原リリィ')
               OR (players.name = 'たべ田りあん' AND c.name = 'セイバー')
               OR (players.name = '梅おかか413' AND c.name = '花語青')
        ");
    }

    public function down(): void
    {
        $this->execute("UPDATE players SET character_id = NULL");
        $this->execute("DELETE FROM characters");
    }
}

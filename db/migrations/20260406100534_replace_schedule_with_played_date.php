<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ReplaceScheduleWithPlayedDate extends AbstractMigration
{
    public function up(): void
    {
        // 新カラム追加
        $this->execute("ALTER TABLE tables_info ADD COLUMN played_date DATE");
        $this->execute("ALTER TABLE tables_info ADD COLUMN day_of_week VARCHAR(10) NOT NULL DEFAULT ''");

        // tournament_id=1 の既存データに3月の日付を仮セット
        // 1回戦: 5卓 → 金曜3/21, 土曜3/22, 日曜3/23
        $this->execute("UPDATE tables_info SET played_date = '2026-03-21', day_of_week = '金曜' WHERE id = 1");
        $this->execute("UPDATE tables_info SET played_date = '2026-03-21', day_of_week = '金曜' WHERE id = 2");
        $this->execute("UPDATE tables_info SET played_date = '2026-03-22', day_of_week = '土曜' WHERE id = 3");
        $this->execute("UPDATE tables_info SET played_date = '2026-03-23', day_of_week = '日曜' WHERE id = 4");
        $this->execute("UPDATE tables_info SET played_date = '2026-03-23', day_of_week = '日曜' WHERE id = 5");

        // 2回戦: 4卓 → 翌週 金曜3/27, 土曜3/28
        $this->execute("UPDATE tables_info SET played_date = '2026-03-27', day_of_week = '金曜' WHERE id = 6");
        $this->execute("UPDATE tables_info SET played_date = '2026-03-27', day_of_week = '金曜' WHERE id = 7");
        $this->execute("UPDATE tables_info SET played_date = '2026-03-28', day_of_week = '土曜' WHERE id = 8");
        $this->execute("UPDATE tables_info SET played_date = '2026-03-28', day_of_week = '土曜' WHERE id = 9");

        // 3回戦: 3卓 → 翌週末 土曜3/29, 日曜3/30
        $this->execute("UPDATE tables_info SET played_date = '2026-03-29', day_of_week = '日曜' WHERE id = 10");
        $this->execute("UPDATE tables_info SET played_date = '2026-03-29', day_of_week = '日曜' WHERE id = 11");
        $this->execute("UPDATE tables_info SET played_date = '2026-03-29', day_of_week = '日曜' WHERE id = 12");

        // 決勝: 1卓 → 3/30 日曜
        $this->execute("UPDATE tables_info SET played_date = '2026-03-30', day_of_week = '月曜' WHERE id = 15");

        // schedule カラム削除
        $this->execute("ALTER TABLE tables_info DROP COLUMN schedule");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE tables_info ADD COLUMN schedule VARCHAR(50) NOT NULL DEFAULT ''");

        // 元データを復元
        $this->execute("UPDATE tables_info SET schedule = '' WHERE id = 1");
        $this->execute("UPDATE tables_info SET schedule = '金曜 21:00' WHERE id = 2");
        $this->execute("UPDATE tables_info SET schedule = '土曜 21:00' WHERE id = 3");
        $this->execute("UPDATE tables_info SET schedule = '日曜 13:00' WHERE id = 4");
        $this->execute("UPDATE tables_info SET schedule = '日曜 22:00' WHERE id = 5");
        $this->execute("UPDATE tables_info SET schedule = '' WHERE id IN (6,7,8,9)");
        $this->execute("UPDATE tables_info SET schedule = '' WHERE id = 10");
        $this->execute("UPDATE tables_info SET schedule = '日曜夜' WHERE id = 11");
        $this->execute("UPDATE tables_info SET schedule = '' WHERE id IN (12,15)");

        $this->execute("ALTER TABLE tables_info DROP COLUMN played_date");
        $this->execute("ALTER TABLE tables_info DROP COLUMN day_of_week");
    }
}

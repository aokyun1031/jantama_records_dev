<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class TournamentSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return [];
    }

    public function run(): void
    {
        // 大会登録
        $this->table('tournaments')->insert([
            ['name' => '第1回 最強位戦', 'status' => 'completed'],
        ])->save();

        $tournamentId = (int) $this->fetchRow('SELECT id FROM tournaments LIMIT 1')['id'];

        // 選手登録
        $players = $this->table('players');
        $playerNames = [
            'ahaaaaaaaan','janwich','がちゃむん','ホロ・ホロ','ぎり。',
            'DJc1ma','みーた姫','millin_waltz','天高馬肥','xするがx',
            'ぶりゅー3','そぼろ丼','ぱーらめんこ','たべ田りあん','青いけちゃっぷ',
            'あーすじゃ','がうさーん','nagiya0211','梅おかか413','jmas',
        ];
        $playerData = [];
        foreach ($playerNames as $name) {
            $playerData[] = ['name' => $name];
        }
        $players->insert($playerData)->save();

        // player_id マップ作成
        $rows = $this->fetchAll('SELECT id, name FROM players');
        $playerMap = [];
        foreach ($rows as $row) {
            $playerMap[$row['name']] = $row['id'];
        }

        // 卓情報
        $tablesData = [
            // 1回戦
            ['tournament_id' => $tournamentId, 'round_number' => 1, 'table_name' => '1卓', 'played_date' => '2026-03-21', 'day_of_week' => '金曜', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 1, 'table_name' => '2卓', 'played_date' => '2026-03-21', 'day_of_week' => '金曜', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 1, 'table_name' => '3卓', 'played_date' => '2026-03-22', 'day_of_week' => '土曜', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 1, 'table_name' => '4卓', 'played_date' => '2026-03-23', 'day_of_week' => '日曜', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 1, 'table_name' => '5卓', 'played_date' => '2026-03-23', 'day_of_week' => '日曜', 'done' => true],
            // 2回戦
            ['tournament_id' => $tournamentId, 'round_number' => 2, 'table_name' => '1卓', 'played_date' => '2026-03-27', 'day_of_week' => '金曜', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 2, 'table_name' => '2卓', 'played_date' => '2026-03-27', 'day_of_week' => '金曜', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 2, 'table_name' => '3卓', 'played_date' => '2026-03-28', 'day_of_week' => '土曜', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 2, 'table_name' => '4卓', 'played_date' => '2026-03-28', 'day_of_week' => '土曜', 'done' => true],
            // 3回戦
            ['tournament_id' => $tournamentId, 'round_number' => 3, 'table_name' => '1卓', 'played_date' => '2026-03-29', 'day_of_week' => '日曜', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 3, 'table_name' => '2卓', 'played_date' => '2026-03-29', 'day_of_week' => '日曜', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 3, 'table_name' => '3卓', 'played_date' => '2026-03-29', 'day_of_week' => '日曜', 'done' => true],
            // 決勝
            ['tournament_id' => $tournamentId, 'round_number' => 4, 'table_name' => '決勝卓', 'played_date' => '2026-03-30', 'day_of_week' => '月曜', 'done' => true],
        ];
        $this->table('tables_info')->insert($tablesData)->save();

        // table_id マップ作成
        $rows = $this->fetchAll('SELECT id, round_number, table_name FROM tables_info');
        $tableMap = [];
        foreach ($rows as $row) {
            $key = $row['round_number'] . '_' . $row['table_name'];
            $tableMap[$key] = $row['id'];
        }

        // 卓メンバー割り当て
        $assignments = [
            // 1回戦
            ['1_1卓', ['janwich','millin_waltz','ぱーらめんこ','青いけちゃっぷ']],
            ['1_2卓', ['ぶりゅー3','がちゃむん','nagiya0211','そぼろ丼']],
            ['1_3卓', ['ぎり。','ホロ・ホロ','あーすじゃ','DJc1ma']],
            ['1_4卓', ['みーた姫','たべ田りあん','がうさーん','jmas']],
            ['1_5卓', ['天高馬肥','ahaaaaaaaan','梅おかか413','xするがx']],
            // 2回戦
            ['2_1卓', ['janwich','ホロ・ホロ','梅おかか413','そぼろ丼']],
            ['2_2卓', ['ぶりゅー3','DJc1ma','天高馬肥','jmas']],
            ['2_3卓', ['ぎり。','がうさーん','xするがx','青いけちゃっぷ']],
            ['2_4卓', ['みーた姫','ahaaaaaaaan','がちゃむん','たべ田りあん']],
            // 3回戦
            ['3_1卓', ['天高馬肥','ホロ・ホロ','DJc1ma','たべ田りあん']],
            ['3_2卓', ['janwich','みーた姫','がうさーん','xするがx']],
            ['3_3卓', ['ahaaaaaaaan','jmas','がちゃむん','ぎり。']],
            // 決勝
            ['4_決勝卓', ['ホロ・ホロ','janwich','がちゃむん','xするがx']],
        ];
        $tpData = [];
        foreach ($assignments as [$key, $names]) {
            foreach ($names as $seat => $name) {
                $tpData[] = [
                    'table_id'   => $tableMap[$key],
                    'player_id'  => $playerMap[$name],
                    'seat_order' => $seat + 1,
                ];
            }
        }
        $this->table('table_players')->insert($tpData)->save();

        // ラウンド成績
        $results = [
            // 1回戦
            [1, 'ahaaaaaaaan',82.5,true],  [1, 'がちゃむん',51.9,true],  [1, 'DJc1ma',51.7,true],
            [1, 'millin_waltz',40.3,true],  [1, 'がうさーん',28.7,true],    [1, 'みーた姫',26.9,true],
            [1, 'janwich',22.3,true],    [1, 'そぼろ丼',12.2,true],  [1, 'ぶりゅー3',11.9,true],
            [1, 'ホロ・ホロ',10.9,true],[1, 'ぎり。',10.2,true],    [1, '青いけちゃっぷ',1.1,true],
            [1, 'たべ田りあん',-6.5,true],    [1, '天高馬肥',-16.1,true],   [1, '梅おかか413',-22.0,true],
            [1, 'xするがx',-44.4,true], [1, 'jmas',-49.1,false],[1, 'ぱーらめんこ',-63.7,false],
            [1, 'あーすじゃ',-72.8,false],[1, 'nagiya0211',-76.0,false],
            // 2回戦
            [2, 'ぎり。',58.5,true],    [2, 'janwich',54.6,true],    [2, 'ホロ・ホロ',43.4,true],
            [2, '天高馬肥',37.3,true],    [2, 'がちゃむん',24.9,true],  [2, 'ahaaaaaaaan',18.8,true],
            [2, 'xするがx',10.0,true],  [2, 'がうさーん',3.8,true],     [2, 'DJc1ma',2.3,true],
            [2, 'jmas',-4.3,true],  [2, 'みーた姫',-10.3,true], [2, 'たべ田りあん',-33.3,true],
            [2, 'ぶりゅー3',-34.6,false],  [2, 'そぼろ丼',-39.6,false],[2, '梅おかか413',-58.4,false],
            [2, '青いけちゃっぷ',-72.3,false],
            // 3回戦
            [3, 'ホロ・ホロ',48.6,true],[3, 'janwich',41.1,true],    [3, 'がちゃむん',37.5,true],
            [3, 'xするがx',33.2,true],  [3, 'みーた姫',31.2,false], [3, 'ahaaaaaaaan',29.1,false],
            [3, 'DJc1ma',1.5,false],  [3, 'ぎり。',-12.2,false],  [3, '天高馬肥',-21.9,false],
            [3, 'たべ田りあん',-28.2,false],  [3, 'jmas',-54.4,false],[3, 'がうさーん',-105.5,false],
            // 決勝
            [4, 'ホロ・ホロ',68.3,true],[4, 'xするがx',64.5,true],
            [4, 'がちゃむん',-58.5,false],[4, 'janwich',-74.3,false],
        ];
        $rrData = [];
        foreach ($results as [$round, $name, $score, $above]) {
            $rrData[] = [
                'tournament_id'  => $tournamentId,
                'player_id'      => $playerMap[$name],
                'round_number'   => $round,
                'score'          => $score,
                'is_above_cutoff'=> $above,
            ];
        }
        $this->table('round_results')->insert($rrData)->save();

        // 総合順位
        $standingsData = [
            ['ホロ・ホロ',1,171.2,false,0], ['ahaaaaaaaan',2,130.4,false,3],
            ['xするがx',3,63.3,false,0],   ['ぎり。',4,56.5,false,3],
            ['がちゃむん',5,55.8,false,0],   ['DJc1ma',6,55.5,false,3],
            ['みーた姫',7,47.8,false,3],   ['janwich',8,43.7,false,0],
            ['millin_waltz',9,40.3,false,1],   ['天高馬肥',10,-0.7,false,3],
            ['ぶりゅー3',11,-22.7,false,2],   ['そぼろ丼',12,-27.4,false,2],
            ['ぱーらめんこ',13,-63.7,false,1],['たべ田りあん',14,-68.0,false,3],
            ['青いけちゃっぷ',15,-71.2,false,2],['あーすじゃ',16,-72.8,false,1],
            ['がうさーん',17,-73.0,false,3],   ['nagiya0211',18,-76.0,false,1],
            ['梅おかか413',19,-80.4,false,2],     ['jmas',20,-107.8,false,3],
        ];
        $sData = [];
        foreach ($standingsData as [$name, $rank, $total, $pending, $elim]) {
            $sData[] = [
                'tournament_id'    => $tournamentId,
                'player_id'        => $playerMap[$name],
                'rank'             => $rank,
                'total'            => $total,
                'pending'          => $pending,
                'eliminated_round' => $elim,
            ];
        }
        $this->table('standings')->insert($sData)->save();

        // 大会メタ情報
        $this->table('tournament_meta')->insert([
            ['tournament_id' => $tournamentId, 'key' => 'total_players',     'value' => '20'],
            ['tournament_id' => $tournamentId, 'key' => 'current_round',     'value' => '4'],
            ['tournament_id' => $tournamentId, 'key' => 'remaining_players', 'value' => '1'],
        ])->save();
    }
}

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
            'あはん','みか','がちゃ','ホロホロ','ぎり',
            'シーマ','みーた','こいぬ','あき','するが',
            'ぶる','そぼろ','ぱーらめんこ','りあ','けちゃこ',
            'あーす','がう','なぎ','梅','イラチ',
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
            ['tournament_id' => $tournamentId, 'round_number' => 1, 'table_name' => '1卓', 'schedule' => '',           'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 1, 'table_name' => '2卓', 'schedule' => '金曜 21:00', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 1, 'table_name' => '3卓', 'schedule' => '土曜 21:00', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 1, 'table_name' => '4卓', 'schedule' => '日曜 13:00', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 1, 'table_name' => '5卓', 'schedule' => '日曜 22:00', 'done' => true],
            // 2回戦
            ['tournament_id' => $tournamentId, 'round_number' => 2, 'table_name' => '1卓', 'schedule' => '', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 2, 'table_name' => '2卓', 'schedule' => '', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 2, 'table_name' => '3卓', 'schedule' => '', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 2, 'table_name' => '4卓', 'schedule' => '', 'done' => true],
            // 3回戦
            ['tournament_id' => $tournamentId, 'round_number' => 3, 'table_name' => '1卓', 'schedule' => '',       'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 3, 'table_name' => '2卓', 'schedule' => '日曜夜', 'done' => true],
            ['tournament_id' => $tournamentId, 'round_number' => 3, 'table_name' => '3卓', 'schedule' => '',       'done' => true],
            // 決勝
            ['tournament_id' => $tournamentId, 'round_number' => 4, 'table_name' => '決勝卓', 'schedule' => '', 'done' => true],
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
            ['1_1卓', ['みか','こいぬ','ぱーらめんこ','けちゃこ']],
            ['1_2卓', ['ぶる','がちゃ','なぎ','そぼろ']],
            ['1_3卓', ['ぎり','ホロホロ','あーす','シーマ']],
            ['1_4卓', ['みーた','りあ','がう','イラチ']],
            ['1_5卓', ['あき','あはん','梅','するが']],
            // 2回戦
            ['2_1卓', ['みか','ホロホロ','梅','そぼろ']],
            ['2_2卓', ['ぶる','シーマ','あき','イラチ']],
            ['2_3卓', ['ぎり','がう','するが','けちゃこ']],
            ['2_4卓', ['みーた','あはん','がちゃ','りあ']],
            // 3回戦
            ['3_1卓', ['あき','ホロホロ','シーマ','りあ']],
            ['3_2卓', ['みか','みーた','がう','するが']],
            ['3_3卓', ['あはん','イラチ','がちゃ','ぎり']],
            // 決勝
            ['4_決勝卓', ['ホロホロ','みか','がちゃ','するが']],
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
            [1, 'あはん',82.5,true],  [1, 'がちゃ',51.9,true],  [1, 'シーマ',51.7,true],
            [1, 'こいぬ',40.3,true],  [1, 'がう',28.7,true],    [1, 'みーた',26.9,true],
            [1, 'みか',22.3,true],    [1, 'そぼろ',12.2,true],  [1, 'ぶる',11.9,true],
            [1, 'ホロホロ',10.9,true],[1, 'ぎり',10.2,true],    [1, 'けちゃこ',1.1,true],
            [1, 'りあ',-6.5,true],    [1, 'あき',-16.1,true],   [1, '梅',-22.0,true],
            [1, 'するが',-44.4,true], [1, 'イラチ',-49.1,false],[1, 'ぱーらめんこ',-63.7,false],
            [1, 'あーす',-72.8,false],[1, 'なぎ',-76.0,false],
            // 2回戦
            [2, 'ぎり',58.5,true],    [2, 'みか',54.6,true],    [2, 'ホロホロ',43.4,true],
            [2, 'あき',37.3,true],    [2, 'がちゃ',24.9,true],  [2, 'あはん',18.8,true],
            [2, 'するが',10.0,true],  [2, 'がう',3.8,true],     [2, 'シーマ',2.3,true],
            [2, 'イラチ',-4.3,true],  [2, 'みーた',-10.3,true], [2, 'りあ',-33.3,true],
            [2, 'ぶる',-34.6,false],  [2, 'そぼろ',-39.6,false],[2, '梅',-58.4,false],
            [2, 'けちゃこ',-72.3,false],
            // 3回戦
            [3, 'ホロホロ',48.6,true],[3, 'みか',41.1,true],    [3, 'がちゃ',37.5,true],
            [3, 'するが',33.2,true],  [3, 'みーた',31.2,false], [3, 'あはん',29.1,false],
            [3, 'シーマ',1.5,false],  [3, 'ぎり',-12.2,false],  [3, 'あき',-21.9,false],
            [3, 'りあ',-28.2,false],  [3, 'イラチ',-54.4,false],[3, 'がう',-105.5,false],
            // 決勝
            [4, 'ホロホロ',68.3,true],[4, 'するが',64.5,true],
            [4, 'がちゃ',-58.5,false],[4, 'みか',-74.3,false],
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
            ['ホロホロ',1,171.2,false,0], ['あはん',2,130.4,false,3],
            ['するが',3,63.3,false,0],   ['ぎり',4,56.5,false,3],
            ['がちゃ',5,55.8,false,0],   ['シーマ',6,55.5,false,3],
            ['みーた',7,47.8,false,3],   ['みか',8,43.7,false,0],
            ['こいぬ',9,40.3,false,1],   ['あき',10,-0.7,false,3],
            ['ぶる',11,-22.7,false,2],   ['そぼろ',12,-27.4,false,2],
            ['ぱーらめんこ',13,-63.7,false,1],['りあ',14,-68.0,false,3],
            ['けちゃこ',15,-71.2,false,2],['あーす',16,-72.8,false,1],
            ['がう',17,-73.0,false,3],   ['なぎ',18,-76.0,false,1],
            ['梅',19,-80.4,false,2],     ['イラチ',20,-107.8,false,3],
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
            ['tournament_id' => $tournamentId, 'key' => 'record_score',      'value' => '65400'],
            ['tournament_id' => $tournamentId, 'key' => 'record_player',     'value' => 'するが'],
            ['tournament_id' => $tournamentId, 'key' => 'total_players',     'value' => '20'],
            ['tournament_id' => $tournamentId, 'key' => 'current_round',     'value' => '4'],
            ['tournament_id' => $tournamentId, 'key' => 'remaining_players', 'value' => '1'],
        ])->save();
    }
}

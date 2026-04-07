<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedInterviewsForTournament1 extends AbstractMigration
{
    public function up(): void
    {
        // 既存データがあれば削除して再投入
        $this->execute('DELETE FROM interviews WHERE tournament_id = 1');

        $items = [
            [1, 1, '大会全体を通して、ご自身の調子やツモの感触はどうでしたか？', '四人打ちにしてはいいところがよく入ったと思います。'],
            [1, 2, '大会前に密かに立てていた「テーマ」や「作戦」はありましたか？', '楽しく打てたらいいなぁって感じで特に作戦はありません。'],
            [1, 3, '今大会を通じて、ご自身の中で「MVP」と言える最高のアガリ（または最高のファインプレー）はどの局でしたか？', '決勝戦の親の時に清一色上がった時ですかね、、、配牌チートだったし思ってた所全部入って来ました、'],
            [1, 4, '逆に「あれは危なかった」、「実はあの時、手が震えていた」というヒヤッとした場面や、裏目に出た局はありましたか？', '特に無し'],
            [1, 5, '一番警戒していた、あるいは「こいつにだけは負けたくない」と思っていたプレイヤーは誰でしたか？', 'ミカさんですね！'],
            [1, 6, '今回の優勝賞品は、どのように使いたいですか？', "毎週水曜日会社にヤクルトさんくるのでそこで使います！\n子供がよく飲むので。。。"],
            [1, 7, '他の参加者たちへ、チャンピオンから愛のあるメッセージ・アドバイスをお願いします！', '勝ち負けにこだわらず楽しく麻雀しましょう。'],
            [1, 8, '最後に、次回大会への意気込みをお願いします！', '討伐賞は誰にも渡しません！'],
        ];

        foreach ($items as [$tid, $order, $q, $a]) {
            $this->execute(sprintf(
                "INSERT INTO interviews (tournament_id, sort_order, question, answer) VALUES (%d, %d, %s, %s)",
                $tid,
                $order,
                $this->getAdapter()->getConnection()->quote($q),
                $this->getAdapter()->getConnection()->quote($a)
            ));
        }
    }

    public function down(): void
    {
        $this->execute('DELETE FROM interviews WHERE tournament_id = 1');
    }
}

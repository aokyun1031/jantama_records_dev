<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jantama Records - 大会成績</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700&family=Oswald:wght@500&display=swap" rel="stylesheet">
    <style>
        :root {
            --plus-color: #f1c40f; /* 雀魂の1位のような金色 */
            --minus-color: #3498db; /* 冷静な青 */
            --bg-color: #1a1a2e; /* 深い紺色 */
            --card-bg: rgba(255, 255, 255, 0.05);
        }

        body {
            background-color: var(--bg-color);
            color: #fff;
            font-family: 'Noto Sans JP', sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            width: 90%;
            max-width: 500px;
            padding: 20px;
        }

        h1 {
            text-align: center;
            font-family: 'Oswald', sans-serif;
            letter-spacing: 2px;
            color: rgba(255, 255, 255, 0.8);
            border-bottom: 2px solid var(--plus-color);
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .result-list {
            list-style: none;
            padding: 0;
        }

        .result-item {
            background: var(--card-bg);
            margin-bottom: 12px;
            padding: 15px 25px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            backdrop-filter: blur(5px);
            border-left: 4px solid transparent;
            opacity: 0; /* アニメーション用初期値 */
            transform: translateX(-20px);
            animation: fadeInRight 0.5s ease forwards;
        }

        /* プラス・マイナスの色分け */
        .is-plus { border-left-color: var(--plus-color); }
        .is-minus { border-left-color: var(--minus-color); }

        .player-name {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .score {
            font-family: 'Oswald', sans-serif;
            font-size: 1.4rem;
        }

        .is-plus .score { color: var(--plus-color); }
        .is-minus .score { color: var(--minus-color); }

        /* 区切り線 */
        .divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.2);
            margin: 20px 0;
            position: relative;
        }

        /* アニメーションの遅延設定 */
        .result-item:nth-child(1) { animation-delay: 0.1s; }
        .result-item:nth-child(2) { animation-delay: 0.2s; }
        .result-item:nth-child(3) { animation-delay: 0.3s; }
        .result-item:nth-child(4) { animation-delay: 0.4s; }
        .result-item:nth-child(6) { animation-delay: 0.5s; }
        .result-item:nth-child(7) { animation-delay: 0.6s; }
        .result-item:nth-child(8) { animation-delay: 0.7s; }
        .result-item:nth-child(9) { animation-delay: 0.8s; }

        @keyframes fadeInRight {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* ホバー時の演出 */
        .result-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.02);
            transition: 0.3s;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>MAHJONG RESULT</h1>
    
    <div class="result-list">
        <div class="result-item is-plus">
            <span class="player-name">ホロホロ</span>
            <span class="score">+48.6</span>
        </div>
        <div class="result-item is-plus">
            <span class="player-name">みか</span>
            <span class="score">+41.1</span>
        </div>
        <div class="result-item is-plus">
            <span class="player-name">するが</span>
            <span class="score">+33.2</span>
        </div>
        <div class="result-item is-plus">
            <span class="player-name">みーた</span>
            <span class="score">+31.2</span>
        </div>

        <div class="divider"></div>

        <div class="result-item is-plus">
            <span class="player-name">シーマ</span>
            <span class="score">+1.5</span>
        </div>
        <div class="result-item is-minus">
            <span class="player-name">アキ</span>
            <span class="score">-21.9</span>
        </div>
        <div class="result-item is-minus">
            <span class="player-name">りあ</span>
            <span class="score">-28.2</span>
        </div>
        <div class="result-item is-minus">
            <span class="player-name">がぞう</span>
            <span class="score">-105.5</span>
        </div>
    </div>
</div>

</body>
</html>
<?php
// Túrázós platformjáték — önálló oldal, nem igényel bejelentkezést
$gameVersion = '1.0.0';
require __DIR__ . '/game-data.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Túra a csúcsra — Hegymászó játék</title>
<link rel="stylesheet" href="style.css?v=<?php echo htmlspecialchars($gameVersion); ?>">
</head>
<body>
<div class="game-page">
    <div class="game-box" id="gameBox">
        <canvas id="gameCanvas" width="960" height="540"></canvas>
    </div>
    <div class="game-help">
        <h2>Irányítás</h2>
        <ul>
            <li><b>→</b> előre &nbsp; <b>←</b> hátra &nbsp; <b>↑</b> ugrás (kétszer: dupla ugrás) &nbsp; <b>↓</b> guggolás</li>
            <li><b>Alt</b> (tartva) lassú lopakodás — így nem riasztod el a rókát 🦊</li>
            <li><b>Shift</b> ütés túrabottal &nbsp; <b>Ctrl</b> nadrág le/fel (vízátkeléshez)</li>
            <li><b>S</b> naptej &nbsp; <b>L</b> fejlámpa &nbsp; <b>P</b> fotó / szelfi a csúcskeresztnél &nbsp; <b>Esc</b> szünet</li>
        </ul>
    </div>
</div>
<script>
window.GAME_LEVELS = <?php echo gameLevelsJson(); ?>;
</script>
<script src="js/levels.js?v=<?php echo htmlspecialchars($gameVersion); ?>"></script>
<script src="js/entities.js?v=<?php echo htmlspecialchars($gameVersion); ?>"></script>
<script src="js/render.js?v=<?php echo htmlspecialchars($gameVersion); ?>"></script>
<script src="js/main.js?v=<?php echo htmlspecialchars($gameVersion); ?>"></script>
</body>
</html>

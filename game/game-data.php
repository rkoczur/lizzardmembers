<?php
/**
 * A hegymászó játék pályaadatai — közös forrás a nyilvános (game/index.php)
 * és a tagoldali (user/game.php) beágyazáshoz. A JS a window.GAME_LEVELS-ből
 * olvassa a neveket és magasságokat.
 */

$GAME_LEVEL_NAMES = [
    'Hármas-határ-hegy', 'Kékestető', 'Schneeberg', 'Dumbier', 'Grosses Buchstein',
    'Rysy', 'Sauleck', 'Grossvenediger', 'Grossglockner', 'Monte Rosa',
    'Matterhorn', 'Kazbek', 'Denali', 'Lenin-csúcs', 'Cho Oyu',
    'Nanga Parbat', 'Annapurna', 'K2', 'Mount Everest',
];
$GAME_LEVEL_HEIGHTS = [
    497, 1014, 2076, 2043, 2224,
    2503, 3079, 3657, 3798, 4634,
    4478, 5054, 6190, 7134, 8188,
    8126, 8091, 8611, 8849,
];

function gameLevelsJson(): string
{
    global $GAME_LEVEL_NAMES, $GAME_LEVEL_HEIGHTS;
    return json_encode(array_map(
        fn($i) => ['name' => $GAME_LEVEL_NAMES[$i], 'height' => $GAME_LEVEL_HEIGHTS[$i]],
        array_keys($GAME_LEVEL_NAMES)
    ), JSON_UNESCAPED_UNICODE);
}

<?php
/**
 * Geriye uyumluluk: /game-play?gameid=… → /play?game_id=…&mode=real&wallet=main
 */
$gid = isset($_GET['gameid']) ? trim((string) $_GET['gameid']) : '';
if ($gid === '') {
    header('Location: /slot');
    exit;
}
$q = [
    'game_id' => $gid,
    'mode'    => 'real',
    'wallet'  => 'main',
];
header('Location: /play?' . http_build_query($q), true, 302);
exit;

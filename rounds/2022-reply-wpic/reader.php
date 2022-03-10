<?php

global $fileName;

/** @var Demon[] */
global $demons;

global $demonsCount;

global $maxTurns;

use Utils\FileManager;

require_once '../../bootstrap.php';

class Player {
    public $stamina;
    public $maxStamina;
}

class Demon {
    public $staminaNeeded;
    public $turnsAfter;
    public $staminaRecoveredAfter;
    public $fragmentTurnsCount;
    public $futureFragments;
}

/* Reaading the input */
$fileManager = new FileManager($fileName);
$content = explode("\n", $fileManager->get());

$fileRow = 0;

$player = new Player();
$infoRow = explode(' ', $content[$fileRow++]);
$player->stamina = $infoRow[0];
$player->maxStamina = $infoRow[1];
$maxTurns = $infoRow[2];
$demonsCount = $infoRow[3];

for($c = 0; $c < $demonsCount; $c++){
    $demon = new Demon();
    $demonRow = explode(' ', $content[$fileRow++]);
    $demon->staminaNeeded = (int)$demonRow[0];
    $demon->turnsAfter = (int)$demonRow[1];
    $demon->staminaRecoveredAfter = (int)$demonRow[2];
    $demon->fragmentTurnsCount = (int)$demonRow[3];
    $futureFragments = [];
    for($i = 0; $i < count($demonRow) - 4; $i++){
        $futureFragments[] = (int)$demonRow[$i + 4];
    }
    $demon->futureFragments = $futureFragments;
    $demons[] = $demon;
}

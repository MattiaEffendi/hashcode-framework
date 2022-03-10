<?php

global $fileName;

/** @var Demon[] */
global $demons;

/** @var int */
global $demonsCount;

/** @var int */
global $maxTurns;

use Utils\File;
use Utils\FileManager;

require_once '../../bootstrap.php';

class Player {
    /** @var int */
    public $stamina;
    /** @var int */
    public $maxStamina;
}

class Demon {
    /** @var int */
    public $id;
    /** @var float */
    public $score;
    /** @var int */
    public $staminaNeeded;
    /** @var int */
    public $turnsAfter;
    /** @var int */
    public $staminaRecoveredAfter;
    /** @var int */
    public $fragmentTurnsCount;
    /** @var int[] */
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
    $demon->id = $c;
    $demon->score = 0;
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

class Output {
    public $out = [];

    public function add($value){
        $this->out[] = $value;
    }

    public function save($fileName){
        $outTxt = '';
        foreach($this->out as $outputRow){
            $outTxt .= $outputRow . "\n";
        }
        File::write($fileName, $outTxt);
    }
}
<?php

use Utils\Analysis\Analyzer;

global $fileName;

$fileName = '05';

include_once 'reader.php';

$analyzer = new Analyzer($fileName, [
    'demons_count' => $demonsCount,
]);

$analyzer->addDataset('demons', $demons, ['staminaNeeded', 'turnsAfter', 'staminaRecoveredAfter', 'fragmentTurnsCount', 'futureFragments']);

$analyzer->analyze();
<?php

use Utils\ArrayUtils;
use Utils\Collection;
use Utils\File;
use Utils\Log;

$fileName = '04';

/* Reader */
include_once 'reader.php';

$currentTurn = 0;
$staminaRecoverForecast = [];

$output = [];

function calculateScoresV0(&$demons, &$player){
    foreach($demons as &$demon){
        $demon->score = min($demon->staminaRecoveredAfter, $player->maxStamina - $player->stamina);
    }
}

function calculateScoresV01(&$demons, &$player, &$currentTurn, &$maxTurns){
    foreach($demons as &$demon){
        $fragmentsAvailable = $maxTurns - $currentTurn;
        $fragmentsSum = 0;
        for($i = 0; $i < $fragmentsAvailable && $i < $demon->fragmentTurnsCount; $i++){
            $fragmentsSum += $demon->futureFragments[$i];
        }
        $demon->score = $$fragmentsSum / $fragmentsAvailable;
}
}

function calculateScoresV1(&$demons, &$player){
    foreach($demons as &$demon){
        $demon->score = min($demon->staminaRecoveredAfter, $player->maxStamina - $player->stamina) / $demon->turnsAfter;
    }
}

function calculateScoresV2(&$demons, &$player, $currentTurn, $maxTurns){
    foreach($demons as &$demon){
        $partialScore = min($demon->staminaRecoveredAfter, $player->maxStamina - $player->stamina) / $demon->turnsAfter;
        if($currentTurn / $maxTurns < 0.5){
            $demon->score = $partialScore;
        }

        else {
            $fragmentsAvailable = $maxTurns - $currentTurn;
            $fragmentsSum = 0;
            for($i = 0; $i < $fragmentsAvailable && $i < $demon->fragmentTurnsCount; $i++){
                $fragmentsSum += $demon->futureFragments[$i];
            }
            $demon->score = $partialScore + ($fragmentsSum / $fragmentsAvailable);
        }
    }
}

function sortDemonsByScore(&$demons){
    usort($demons, function($a, $b){
        return $b->score - $a->score;
    });
}

function recoverStamina(&$staminaRecoverForecast, &$player, $currentTurn){
    $player->stamina += $staminaRecoverForecast[$currentTurn];
    unset($staminaRecoverForecast[$currentTurn]);
    $player->stamina > $player->maxStamina ? $player->stamina = $player->maxStamina : $player->stamina;
}

function fightBestDemon(&$demons, &$player, &$staminaRecoverForecast, &$currentTurn, $maxTurns){
    global $output;
    $key = 0;
    foreach($demons as &$demon){
        if($demon->staminaNeeded <= $player->stamina){
            // We fight him

            $staminaNeeded = $demon->staminaNeeded;
            
            if(isset($staminaRecoverForecast[$currentTurn + $demon->turnsAfter])){
                $staminaRecoverForecast[$currentTurn + $demon->turnsAfter] = $staminaRecoverForecast[$currentTurn + $demon->turnsAfter] + $demon->staminaRecoveredAfter;
            } else {
                $staminaRecoverForecast[$currentTurn + $demon->turnsAfter] = $demon->staminaRecoveredAfter;
            }
        
            $output[] = $demon->id;

            unset($demons[$key]);

            return $staminaNeeded;
        }
        $key++;
    }
    if(count($staminaRecoverForecast) == 0){
        Log::out('Deadlock! We have ' . $player->stamina . ' stamina and no stamina to get.');
        $currentTurn = $maxTurns + 1;
    }
}

function saveOutput($fileName, $output){
    $outTxt = implode(PHP_EOL, $output);
    File::write('outputs/'. $fileName . '.txt', $outTxt);
}

while($currentTurn <= $maxTurns){

    Log::out('[Output '. $fileName . '] Running turn ' . $currentTurn . '/' . $maxTurns);

    calculateScoresV2($demons, $player, $currentTurn, $maxTurns);

    sortDemonsByScore($demons);

    // Recover stamina from the previous fight
    recoverStamina($staminaRecoverForecast, $player, $currentTurn);

    // Fight the best demon 
    $consumedStamina = fightBestDemon($demons, $player, $staminaRecoverForecast, $currentTurn, $maxTurns);
   
    // Lower the stamina by the one needed to fight
    $player->stamina -= $consumedStamina;
    
    $currentTurn++;
}

saveOutput($fileName, $output);





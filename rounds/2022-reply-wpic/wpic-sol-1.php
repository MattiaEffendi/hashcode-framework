<?php

use Utils\ArrayUtils;
use Utils\Collection;
use Utils\File;
use Utils\Log;

$fileName = '03';

/* Reader */
include_once 'reader.php';

$currentTurn = 0;
$staminaRecoverForecast = [];

$output = [];

function calculateScores(&$demons, &$player){
    foreach($demons as $demon){
        $demon->score = min($demon->staminaRecoveredAfter, $player->maxStamina - $player->stamina);
    }
}

function calculateScoresV2(&$demons, &$player){
    foreach($demons as $demon){
        $demon->score = min($demon->staminaRecoveredAfter, $player->maxStamina - $player->stamina);
    }
}

function sortDemonsByScore(&$demons){
    usort($demons, function($a, $b){
        return $b->score - $a->score;
    });
}

function recoverStamina($staminaRecoverForecast, &$player, $currentTurn){
    $player->stamina += $staminaRecoverForecast[$currentTurn];
    $player->stamina > $player->maxStamina ? $player->stamina = $player->maxStamina : $player->stamina;
}

function fightBestDemon(&$demons, &$player, &$staminaRecoverForecast, $currentTurn){
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
}

function saveOutput($fileName, $output){
    $outTxt = implode(PHP_EOL, $output);
    File::write('outputs/'. $fileName . '.txt', $outTxt);
}

while($currentTurn <= $maxTurns){

    Log::out('Running turn ' . $currentTurn . '/' . $maxTurns);

    calculateScores($demons, $player);

    sortDemonsByScore($demons);

    // Recover stamina from the previous fight
    recoverStamina($staminaRecoverForecast, $player, $currentTurn);

    // Fight the best demon 
    $consumedStamina = fightBestDemon($demons, $player, $staminaRecoverForecast, $currentTurn);
   
    // Lower the stamina by the one needed to fight
    $player->stamina -= $consumedStamina;

    
    $currentTurn++;
}

saveOutput($fileName, $output);





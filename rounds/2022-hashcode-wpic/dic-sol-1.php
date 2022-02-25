<?php

use Utils\ArrayUtils;
use Utils\File;
use Utils\Log;

$fileName = 'b';

/* Reader */
include_once 'reader.php';

$availableContributors = [];
$skills = [];

$currentDay = 0;

$currentProjects = [];
$output = [];

$sortedProjects = [];

function calculateScores(){
    global $currentDay;
    global $sortedProjects;
    global $projects;
    foreach($projects as $project){
        $delay = $project->expire - $currentDay - $project->duration;
        $scarto = $delay < 0 ? 0 : $delay;
        
        if($delay > 0){
            $delay = 0;
        }
        
        $project->score = (($project->award + $delay) / $project->duration) / ($scarto + 1);

        
        //ArrayUtils::array_keysort($project->roles, 'level');
    }
    
    $sortedProjects = $projects;

    // Sort projects by descending score
    usort($sortedProjects, function($a, $b){
        return $b->score <=> $a->score;
    });

}

function assignProject($project, $chosenContributors){
    global $currentProjects;
    global $projects;
    global $output;
    $currentProjects[$project->name] = [
        'project' => $project,
        'days_left' => $project->duration,
        'contribuents' => $chosenContributors
    ];
    $output[] = [
        'project_name' => $project->name,
        'contribuents' => implode(' ', $chosenContributors),
    ];
    unset($projects[$project->name]);
    occupyPeople($chosenContributors);
}

function occupyPeople($people){
    foreach($people as $contribuent){
        $availableContributors[$contribuent] = false;
    }
}

function freePeople($people){
    foreach($people as $contribuent){
        $availableContributors[$contribuent] = true;
    }
}

function endProject($project){
    global $currentProjects;
    freePeople($project['contributents']);
    unset($currentProjects[$project['project']->name]);
}

function checkEndingProjects(){
    global $currentProjects;
    global $currentDay;
    $fProject = null;
    foreach($currentProjects as $project){
        $fProject = $project;
        break;
    }
    $passingDays = $fProject['days_left'];
    foreach($currentProjects as $project){
        if($project['days_left'] == $passingDays){
            endProject($project);
        }else{
            $project['days_left'] -= $passingDays;
        }
    }
    $currentDay += $passingDays;
    return $passingDays;
}

function output($output){
    $outTxt = "";
    $outTxt .= count($output) . "\n";
    foreach($output as $outputRow){
        $outTxt .= $outputRow['project_name'] . "\n";
        $outTxt .= $outputRow['contribuents'] . "\n";
    }
    File::write('out.txt', $outTxt);
}


// Create an array with key = contributor, value = array of skills
foreach($contributors as $contributor){
    $availableContributors[$contributor->name] = true;
    foreach($contributor->skills as $skillName => $level){
        $skills[$skillName][] = [
            'contributor' => $contributor->name,
            'level' => $level,
        ];
    }
}

calculateScores();


foreach($skills as $skill => $content){
    ArrayUtils::array_keysort($content, 'level', SORT_ASC);
}

while(count($projects) > 0){

    if(count($currentProjects) > 0){
        checkEndingProjects();
        calculateScores();
    }

    foreach($sortedProjects as $project){
        $chosenContributors = [];
        foreach($project->roles as $requestedSkill){
            $skill = $skills[$requestedSkill['skill']];
            $simplyTheBest = $skills[$requestedSkill['skill']][count($skills[$requestedSkill['skill']]) - 1];

            if($simplyTheBest['level'] < $requestedSkill['level']){
                continue;
            }

            foreach($skill as $contributorForSkill){
                if($contributorForSkill['level'] >= $requestedSkill['level'] && $availableContributors[$contributorForSkill['contributor']] && !in_array($contributorForSkill['contributor'], $chosenContributors)){
                    $chosenContributors[] = $contributorForSkill['contributor'];
                    //Log::out('Assigned ' . $contributorForSkill['contributor'] . ' to ' . $project->name . ' for ' . $requestedSkill['skill'] . ' level ' . $requestedSkill['level']);
                    break;
                }
            }
            
        }
        if(count($chosenContributors) == count($project->roles)){
            assignProject($project, $chosenContributors);
        }
    }

    ArrayUtils::array_keysort($currentProjects, 'days_left', SORT_ASC);

    if(count($currentProjects) == 0){   
        output($output);
        exit;
    }
}

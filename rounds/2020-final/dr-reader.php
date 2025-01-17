<?php

ini_set('display_errors', E_ERROR);
ini_set('display_startup_errors', 1);
error_reporting(E_ERROR);

require_once '../../bootstrap.php';

use Utils\FileManager;
use Utils\Log;

$fileName = $fileName ?: 'a';

$W = 0;
$H = 0;
$N_MOUNT_POINTS = 0;
$N_ASSEMBLY_POINTS = 0;
$N_TASKS = 0;
$N_ARMS = 0;
$N_STEPS = 0;

//$MAP = []; // [x][y]=m (mounting point),a (assembly point)
$XY_ASSEMBLY_POINTS = [];
$ASSEMBLY_POINTS = collect();
$MOUNT_POINTS = collect();
$TASKS = collect();

class AssemblyPoint
{
    public $x, $y;
    /** @var int $starts */
    public $starts = 0;
    /** @var int $finishes */
    public $finishes = 0;
    /** @var int $middles */
    public $middles = 0;
    /** @var int $singles */
    public $singles = 0;
    public $startingTasks = [];
    public $endingTasks = [];
    public $singleTasks = [];

    public function __construct($x, $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public static function get($x, $y, $status, $task)
    {
        global $ASSEMBLY_POINTS, $XY_ASSEMBLY_POINTS;
        if ($ap = $XY_ASSEMBLY_POINTS[$x][$y]) {
            /** @var AssemblyPoint $ap */
        } else {
            $ap = new AssemblyPoint($x, $y);
            $XY_ASSEMBLY_POINTS[$x][$y] = $ap;
            $ASSEMBLY_POINTS->add($ap);
        }
        switch ($status) {
            case 'start':
                $ap->starts++;
                $ap->startingTasks[] = $task;
                break;
            case 'middle';
                $ap->middles++;
                break;
            case 'finish';
                $ap->finishes++;
                $ap->endingTasks[] = $task;
                break;
            case 'single';
                $ap->singles++;
                $ap->singleTasks[] = $task;
                break;
        }

        return $ap;
    }
}

class MountPoint
{
    public $x, $y;

    public function __construct($row)
    {
        list($x, $y) = explode(" ", $row);
        $this->x = (int)$x;
        $this->y = (int)$y;
    }
}

class Task
{
    public $id;
    /** @var int $score */
    public $score;
    /** @var int $nAssemblyPoints */
    public $nAssemblyPoints;
    /** @var int $nSteps */
    public $nSteps = 0;
    /** @var int $scorePerStep */
    public $scorePerStep;
    /** @var int $offsettedScorePerStep */
    public $offsettedScorePerStep;
    /** @var array[AssemblyPoint] $assemblyPoints */
    public $assemblyPoints = []; // AssemblyPoints
    public $startX, $startY, $endX, $endY;

    public static $lastId = 0;

    public function __construct($row1, $row2)
    {
        $this->id = self::$lastId++;

        list($score, $assemblyPoints) = explode(" ", $row1);
        $score = (int)$score;
        $nAssemblyPoints = (int)$assemblyPoints;
        $this->nAssemblyPoints = $nAssemblyPoints;
        $this->score = $score;
        $this->assemblyPoints = [];
        $c = 0;
        $status = 'single';
        $row2 = explode(" ", $row2);
        $prevX = null;
        $prevY = null;
        for ($i = 0; $i < $nAssemblyPoints; $i++) {
            $x = (int)$row2[$c++];
            $y = (int)$row2[$c++];
            if ($i == 0) {
                $status = 'start';
                if ($nAssemblyPoints == 1) {
                    $status = 'single';
                    $this->endX = $x;
                    $this->endY = $y;
                }
                $this->startX = $x;
                $this->startY = $y;
            } elseif ($i == $nAssemblyPoints - 1) {
                $status = 'finish';
                $this->endX = $x;
                $this->endY = $y;
            } else {
                $status = 'middle';
            }

            if ($status != 'start' && $status != 'single') {
                $this->nSteps += abs($x - $prevX) + abs($y - $prevY);
            }
            $prevX = $x;
            $prevY = $y;

            $this->assemblyPoints[] = AssemblyPoint::get($x, $y, $status, $this);
        }
        $this->scorePerStep = $this->score / ($this->nSteps + 1); //+1 to fix the single cases
        $this->offsettedScorePerStep = $this->score / ($this->nSteps + 10); //+10 to assume there are X steps to do, to reach the point
    }
}


// Reading the inputs
Log::out("Reading file");
$fileManager = new FileManager($fileName);
$content = explode("\n", $fileManager->get());
list($W, $H, $N_ARMS, $N_MOUNT_POINTS, $N_TASKS, $N_STEPS) = explode(" ", $content[0]);
$W = (int)$W;
$H = (int)$H;
$N_ARMS = (int)$N_ARMS;
$N_MOUNT_POINTS = (int)$N_MOUNT_POINTS;
$N_TASKS = (int)$N_TASKS;
$N_STEPS = (int)$N_STEPS;

$r = 1;

Log::out("Adding mount points");
for ($i = 0; $i < $N_MOUNT_POINTS; $i++)
    $MOUNT_POINTS->add(new MountPoint($content[$r++]));

Log::out("Adding tasks");
for ($i = 0; $i < $N_TASKS; $i++)
    $TASKS->add(new Task($content[$r++], $content[$r++]));
$TASKS->keyBy('id');

$N_MOUNT_POINTS = $MOUNT_POINTS->count();
$N_TASKS = $TASKS->count();
$N_ASSEMBLY_POINTS = $ASSEMBLY_POINTS->count();

Log::out("Read finished");


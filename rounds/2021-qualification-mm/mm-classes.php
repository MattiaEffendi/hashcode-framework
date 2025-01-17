<?php

require_once '../../bootstrap.php';

use Utils\Log;

class Street
{
    // Base attrs
    /** @var string $name */
    public $name;
    /** @var int $duration */
    public $duration;
    /** @var Intersection $start */
    public $start;
    /** @var Intersection $end */
    public $end;

    // Global attrs
    /** @var Semaphore $semaphore */
    public $semaphore;
    /** @var int $usage */
    public $usage;

    public function __construct($name, $duration, $start, $end)
    {
        $this->name = $name;
        $this->duration = (int)$duration;
        $this->start = $start;
        $this->end = $end;
        $this->semaphore = new Semaphore($this);
        $this->usage = 0;

        $this->end->streetsIn[$this->name] = $this;
        $this->start->streetsOut[$this->name] = $this;
    }

    public function init()
    {
        $this->semaphore->init();
    }
}

class Semaphore
{
    // Base attrs
    /** @var Street $street */
    public $street;
    /** @var Intersection $intersection */
    public $intersection;

    // Instant attrs
    /** @var Car[] $queue */
    public $queue;
    /** @var int $timeDuration */
    public $timeDuration;

    // History attrs
    /** int $maxQueue */
    public $maxQueue;
    /** int $queueTime */
    public $queueTime;
    /** int $totalQueue */
    public $totalQueue;

    /** @var array $history */
    public $history;

    public function __construct(Street $street)
    {
        $this->street = $street;
        $this->intersection = $street->end;
    }

    public function init()
    {
        $this->queue = [];
        $this->timeDuration = 1;
        $this->queueTime = 0;
        $this->maxQueue = 0;
        $this->totalQueue = 0;
    }

    public function saveHistory()
    {
        $this->history[] = [
            'timeDuration' => $this->timeDuration,
            'queueTime' => $this->queueTime,
            'maxQueue' => $this->maxQueue,
            'totalQueue' => $this->totalQueue,
        ];
    }

    public function getLoad()
    {
        return array_reduce($this->queue, function ($carry, Car $car) {
            return $carry + $car->priority;
        }, 0);
    }

    public function update()
    {
        $currentQueue = count($this->queue);
        if ($currentQueue > 0)
            $this->queueTime = 0;
        if ($currentQueue > $this->maxQueue)
            $this->maxQueue = $currentQueue;
        $this->totalQueue += $currentQueue;
    }

    public function enqueueCar(Car $car)
    {
        array_unshift($this->queue, $car);
        //$this->maxQueue = max(count($this->queue), $this->maxQueue);
    }

}

class Intersection
{
    // Base attrs
    public $id;
    /** @var Street[] $streetsIn */
    public $streetsIn = [];
    /** @var Street[] $streetsOut */
    public $streetsOut = [];

    // Global attrs
    /** @var Car[] $passingCars */
    public $passingCars;

    // History attrs
    /** @var Street[] $greenScheduling */
    public $greenScheduling = [];
    /** @var Car[] $remainingCars */
    public $remainingCars = [];

    public function __construct($id)
    {
        $this->id = $id;
        $this->passingCars = [];
    }

    public function init()
    {
        $this->greenScheduling = [];
        $this->remainingCars = $this->passingCars;
    }

    public function calculateTimeDuration()
    {
        global $DURATION;
        $scores = [];
        $worstScore = 10000000000;
        foreach ($this->streetsIn as $streetName => $street) {
            $sem = $street->semaphore;
            $score = $sem->totalQueue / $DURATION + pow($sem->maxQueue, 0.5) + $sem->queueTime / $DURATION;
            $scores[$streetName] = $score;
            if($score < $worstScore) {
                $worstScore = $score;
            }
        }
        foreach ($this->streetsIn as $streetName => $street) {
            $sem = $street->semaphore;
            $sem->timeDuration = round($scores[$streetName] / $worstScore);
        }
    }

    public function calculateScheduling()
    {
        // Se ho solo una strada entrante
        if (count($this->streetsIn) === 1) {
            $this->greenScheduling = [
                array_values($this->streetsIn)[0],
            ];
            return;
        }
        $this->greenScheduling = [];
        foreach ($this->streetsIn as $streetName => $street) {
            for ($i = 0; $i < $street->semaphore->timeDuration; $i++) {
                $this->greenScheduling[] = $this->streetsIn[$streetName];
            }
        }
    }

    public function nextStep(int $t)
    {
        if (!$this->greenScheduling) {
            Log::error('Non c\'è uno scheduling');
        }
        $currentStreet = $this->greenScheduling[$t % count($this->greenScheduling)];
        if ($currentStreet) {
            if (count($currentStreet->semaphore->queue) > 0) {
                $car = array_pop($currentStreet->semaphore->queue);
                $car->nextStreet();
                unset($this->remainingCars[$car->id]);
                return true;
            }
        } else {
            Log::error('Non c\'è una strada verde');
        }
        return false;
    }
}

class Car
{
    const STATE_ALREADY_ENQUEUED = 0;
    const STATE_JUST_ENQUEUED = 1;
    const STATE_TRAVELING = 2;

    private static $lastId = 0;

    // Base attrs
    /** @var int $id */
    public $id;
    /** @var Street[] $streets */
    public $streets;
    /** @var Street $startingStreet */
    public $startingStreet;

    // General attrs
    /** @var int $pathDuration */
    public $pathDuration;
    /** @var int $priority */
    public $priority;

    // Instant attrs
    /** @var Street $currentStreet */
    public $currentStreet;
    /** @var int $currentStreetIdx */
    public $currentStreetIdx = 0;
    /** @var int currentStreetDuration */
    public $currentStreetDuration = 0;
    /** @var int currentStreetEnqueued */
    public $isEnqueued = false;

    public function __construct($streets)
    {
        $this->id = self::$lastId++;
        $this->streets = $streets;

        $this->pathDuration = 0;
        foreach ($streets as $street) {
            /** @var Street $street */
            if ($street !== $this->streets[0]) {
                $this->pathDuration += $street->duration;
                $street->start->passingCars[$this->id] = $this;
            }
            $street->usage++;
        }
    }

    public function init()
    {
        $this->currentStreetIdx = 0;
        $this->startingStreet = $this->streets[$this->currentStreetIdx];
        $this->currentStreet = $this->startingStreet;
    }

    public function nextStep()
    {
        if ($this->currentStreetDuration > 1) {
            $this->currentStreetDuration--;
            return self::STATE_TRAVELING;
        } else {
            if (!$this->isEnqueued) {
                $this->enqueue();
                return self::STATE_JUST_ENQUEUED;
            }
        }
        return self::STATE_ALREADY_ENQUEUED;
    }

    public function enqueue()
    {
        $this->currentStreet->semaphore->enqueueCar($this);
        $this->isEnqueued = true;
    }

    public function nextStreet()
    {
        $this->currentStreetIdx++;
        $this->currentStreet = $this->streets[$this->currentStreetIdx];
        $this->currentStreetDuration = $this->currentStreet->duration;
        $this->isEnqueued = false;
    }
}

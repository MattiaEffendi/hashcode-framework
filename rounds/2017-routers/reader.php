<?php

use Utils\FileManager;

require_once '../../bootstrap.php';

class Cell
{
    /** @var int $r */
    public $r;
    /** @var int $c */
    public $c;
    /** @var bool $isTarget */
    public $isTarget;
    /** @var bool $isWall */
    public $isWall;
    /** @var bool $isVoid */
    public $isVoid;

    public function __construct($r, $c, $type)
    {
        $this->r = $r;
        $this->c = $c;
        $this->isTarget = $type === '.';
        $this->isWall = $type === '#';
        $this->isVoid = $type === '-';
    }
}

// Reading the inputs
$fileManager = new FileManager($fileName);
$fileContent = $fileManager->get();

$data = explode("\n", $fileContent);
unset($fileContent);
[$rowsCount, $columnsCount, $routerRadius] = explode(' ', $data[0]);
[$backbonePrice, $routerPrice, $maxBudget] = explode(' ', $data[1]);
[$backboneStartRow, $backboneStartColumn] = explode(' ', $data[2]);
array_splice($data, 0, 3);

$rowsCount = (int)$rowsCount;
$columnsCount = (int)$columnsCount;
$routerRadius = (int)$routerRadius;

$backbonePrice = (int)$backbonePrice;
$routerPrice = (int)$routerPrice;
$maxBudget = (int)$maxBudget;

$backboneStartRow = (int)$backboneStartRow;
$backboneStartColumn = (int)$backboneStartColumn;

/** @var Cell[][] $map */
$map = [];

for ($i = 0; $i < $rowsCount; $i++) {
    $map[$i] = [];
    for ($k = 0; $k < $columnsCount; $k++) {
        $map[$i][$k] = new Cell($i, $k, $data[$i][$k]);
    }
}

unset($data);

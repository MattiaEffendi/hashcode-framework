<?php

use Utils\Collection;

$fileName = 'e';

include 'reader.php';

/**
 * @var integer $countBooks
 * @var integer $countLibraries
 * @var integer $countDays
 * @var Collection $books
 * @var Collection $libraries
 */

echo $countDays . "\n";
$avg = $libraries->avg('signUpDuration');
echo $avg . "\n";
echo ($countDays / $avg) . "\n";

$output = '';

//$fileManager->output(implode("\n", $output));

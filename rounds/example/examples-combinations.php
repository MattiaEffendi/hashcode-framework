<?php

use Utils\ArrayUtils;

require_once '../../bootstrap.php';

print_r(ArrayUtils::getAllCombinationsFlat(['a', 'b', 'c']));
print_r(ArrayUtils::getAllCombinationsFlatLimited(['a', 'b', 'c'], 2));
print_r(ArrayUtils::getSumForAllCombinationsValuesFlat([1, 2, 3]));

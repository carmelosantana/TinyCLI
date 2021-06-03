<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use carmelosantana\TinyCLI\TinyCLI;

// output memory
if (TinyCLI::isCLI())
    TinyCLI::memoryUsage();

TinyCLI::madeWithLove('NY');


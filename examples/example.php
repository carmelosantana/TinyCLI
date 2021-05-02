<?php
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use carmelosantana\TinyCLI\TinyCLI;

if (TinyCLI::isCLI())
    TinyCLI::cli_echo_footer();

TinyCLI::cli_echo_made_with_love('NY');

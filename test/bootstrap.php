<?php
// set up AT and require the autoloader
error_reporting(E_ALL);
$workingDir = realpath(dirname(__DIR__));
augmented_types_whitelist([$workingDir . "/src/"]);
require "$workingDir/vendor/autoload.php";

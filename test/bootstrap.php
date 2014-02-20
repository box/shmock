<?php
// set up AT and require the autoloader
$workingDir = realpath(dirname(__DIR__));
augmented_types_whitelist([$workingDir . "/src/"]);
require "$workingDir/vendor/autoload.php";

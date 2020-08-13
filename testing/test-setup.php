<?php

if (!isset($outputfile)) {
    throw new Exception('Forgot to define $outputfile?');
}

$basedir = __DIR__ . '/';

include_once($basedir . '../phlute.php');
print_r("Ignore preceeding warning.  Proceeding with test.\n");

$outputfileFull = $basedir . 'test-output/' . $outputfile;

if (file_exists($outputfileFull)) {
    unlink($outputfileFull);
}

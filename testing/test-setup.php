<?php

if (!isset($outputfile)) {
    throw new Exception('Forgot to define $outputfile?');
}

$basedir = __DIR__ . '/';

$argv = [null, null, '--donotrun'];

include_once($basedir . '../phlute.php');

$outputfileFull = $basedir . 'test-output/' . $outputfile;

if (file_exists($outputfileFull)) {
    unlink($outputfileFull);
}

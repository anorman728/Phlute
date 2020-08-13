<?php

/**
 * File to manually test FileWriter.
 *
 */

// Don't define a php file.  This is just for making sure writing to file
// actually works.
$outputfile = 'test-filewriter-output';

include_once('./test-setup.php');

$fileWriter = new FileWriter($outputfileFull);

$fileWriter->appendToFile('testing');
$fileWriter->appendToFile('123', 1);
$fileWriter->appendToFile('456', 2);

<?php

/**
 * File to manually test Docblock.
 *
 */

$outputfile = 'test-docblockbuilder-output.php';

include_once('./test-setup.php');

$writer = new FileWriter($outputfileFull);

$writer->appendToFile('<?php');

// Indent level zero.
$docblockbuilder = new DocblockBuilder($writer, 1);

$docblockbuilder->setDescription("Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.");
$docblockbuilder->addAttribute('param', ['string', 'abc123', 'This is a parameter description.', 'This should not appear anywhere.']);

$docblockbuilder->write();

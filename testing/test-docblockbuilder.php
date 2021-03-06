<?php

/**
 * File to manually test Docblock.
 *
 */

$outputfile = 'test-docblockbuilder-output.php';

include_once('./test-setup.php');

$writer = new FileWriter($outputfileFull);

$writer->appendToFile('<?php');

// First test.

$docblockbuilder = new DocblockBuilder($writer, 1);

$desc = <<<_PLAINTEXT
    Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.

    Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
_PLAINTEXT;

$docblockbuilder->setDescription($desc);
$docblockbuilder->addAttribute('param', ['string', 'abc123', 'This is a parameter description.', 'This should not appear anywhere.']);

$docblockbuilder->write();


$writer->appendToFile('');


// Second test.

$docblockbuilder = new DocblockBuilder($writer, 1);
$docblockbuilder->setDescription('This is a string docblock.');
$docblockbuilder->addAttribute('var', ['string']);
//$docblockbuilder->setForceVertical(true);

$docblockbuilder->write();


$writer->appendToFile('');


// Third test.

$docblockbuilder = new DocblockBuilder($writer, 1);
$docblockbuilder->setDescription('This should be a line comment.');
$docblockbuilder->useLineCommentDecorations();

$docblockbuilder->write();


$writer->appendToFile('');


// Fourth test.

$docblockbuilder = new DocblockBuilder($writer, 1);
$docblockbuilder->setDescription('Out of the tree of life I just picked me a plumb.  You came along and everything started to hum.  Still it\'s a real good bet, the best is yet to come.  The best is yet to come and babe won\'t that be fine.  You think you seen the sun, but you ain\'t seen it shine.');
$docblockbuilder->useLineCommentDecorations();
$docblockbuilder->addAttribute('param', ['string', '$frankSinatra']);
// No reason to add attributes to line comments like this, but doesn't really
// matter.

$docblockbuilder->write();

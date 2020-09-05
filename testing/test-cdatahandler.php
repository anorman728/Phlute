<?php

/**
 * File to manually test CDataHandler.
 *
 */

$outputfile = 'test-cdatahandler-output.php';

include_once('./test-setup.php');

$writer = new FileWriter($outputfileFull);
$writer->appendToFile('<?php');


//
$writer->appendToFile('// We should expect this to have the');
$writer->appendToFile('// indentation "normalized", and by that I mean reduce the indentation of');
$writer->appendToFile('// each line until at least one line has zero indentation, and no further.');

$writer->appendToFile('');

/**
 * Throwaway function just to build a DOMDocument and return a
 * specified DOMElement object.
 *
 * @param   string  $elName
 * @return  DOMElement
 */
function buildDocDummyFunction(string $xmlinput, string $elName): DOMElement
{
    $domdoc = new DOMDocument();
    $domdoc->preserveWhitespace = false;
    $domdoc->loadXml($xmlinput);

    // Note: I generally don't like to use getElementsByTagName
    // because it's recursive, but I'm making an exception here
    // because it's just test code.
    return $domdoc->getElementsByTagName($elName)[0];
}


// Test with full <?php

$xml = <<<__XML
<?xml version="1.0" encoding="UTF-8"?>
<testdoc>
    <value><![CDATA[<?php
        \$myvar = 'a';

        \$myarr = [
            1,
            2,
            3,
            4
        ];
    ?>]]></value>
</testdoc>
__XML;

$el = buildDocDummyFunction($xml, 'value');

$cDataHandler = new CDataHandler($el);

$writer->appendToFile('// With full tag.');

foreach ($cDataHandler->build() as $line) {
    $writer->appendToFile($line);
}

// Test with short tag <?

$xml = <<<__XML
<?xml version="1.0" encoding="UTF-8"?>
<testdoc>
    <value><![CDATA[<?
        \$myvar2 = 'b';

        \$myarr2 = [
            5,
            6,
            7,
            8,
        ];
    ?>]]></value>
</testdoc>
__XML;


$el = buildDocDummyFunction($xml, 'value');

$cDataHandler = new CDataHandler($el);

$writer->appendToFile('');
$writer->appendToFile('// With short tag.');

foreach ($cDataHandler->build() as $line) {
    $writer->appendToFile($line);
}


// Test with no tag.
$xml = <<<__XML
<?xml version="1.0" encoding="UTF-8"?>
<testdoc>
    <value><![CDATA[
        \$myvar3 = 'c';

        \$myarr = [
            9,
            10,
            11,
            12
        ];
    ]]></value>
</testdoc>
__XML;

$el = buildDocDummyFunction($xml, 'value');

$cDataHandler = new CDataHandler($el);

$writer->appendToFile('');
$writer->appendToFile('// With no tag.');

foreach ($cDataHandler->build() as $line) {
    $writer->appendToFile($line);
}


// Test with single line.

$xml = <<<__XML
<?xml version="1.0" encoding="UTF-8"?>
<testdoc>
    <value><![CDATA[<?php \$myvar = 'd'; ?>]]></value>
</testdoc>
__XML;

$el = buildDocDummyFunction($xml, 'value');

$writer->appendToFile('');
$writer->appendToFile('// Single-line.');

$cDataHandler = new CDataHandler($el);

foreach ($cDataHandler->build() as $line) {
    $writer->appendToFile($line);
}

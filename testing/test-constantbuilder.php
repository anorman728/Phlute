<?php

/**
 * File to manually test ConstantBuilder.
 *
 */

$outputfile = 'test-constantbuilder-output.php';

include_once('./test-setup.php');

$writer = new FileWriter($outputfileFull);
$writer->appendToFile('<?php');
$writer->appendToFile('');

$xml = <<<'__XML'
<?xml version="1.0" encoding="UTF-8"?>
<testdoc>
    <constant type="array" doc="A constant value." name="MY_CONST" value="[1,2,3]"/>
</testdoc>
__XML;

$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->loadXML($xml);

$el = $dom->getElementsByTagName('constant')[0];

$constantBuilder = new ConstantBuilder($writer, $el, 1);

$constantBuilder->write();

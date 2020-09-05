<?php

/**
 * File to manually test PropertyBuilder.
 *
 */

$outputfile = 'test-propertybuilder-output.php';

include_once('./test-setup.php');

$writer = new FileWriter($outputfileFull);
$writer->appendToFile('<?php');
$writer->appendToFile('');

$xml = <<<'__XML'
<?xml version="1.0" encoding="UTF-8"?>
<testdoc>
    <uses>
        <use value="App\Model\MyModel"/>
        <use value="App\View\MyView"/>
    </uses>
    <property type="StdClass" name="myprop" doc="Test var." keywords="static"/>
</testdoc>
__XML;

$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->loadXML($xml);

$el = $dom->getElementsByTagName('property')[0];

$propertyBuilder = new PropertyBuilder($writer, $el, 1);
$propertyBuilder->setUsedNamespaces(new UsedNamespaces($dom->getElementsByTagName('uses')[0]));

$propertyBuilder->write();
$propertyBuilder->writeSettersAndGetters();

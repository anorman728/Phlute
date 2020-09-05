<?php

/**
 * File to manually test UsedNamespaces class.
 *
 */

$outputfile = 'test-usednamespaces-output.php';

include_once('./test-setup.php');

$writer = new FileWriter($outputfileFull);
$writer->appendToFile('<?php');

$xml = <<<'__XML'
<?xml version="1.0" encoding="UTF-8"?>
<testdoc>
    <uses>
        <use value="SomeNamespace\SubNamespace\MyClass"/>
        <use value="AnotherNamespace\SubNamespace\MyClass2"/>
    </uses>
</testdoc>
__XML;

$dom = new DOMDocument();
$dom->loadXML($xml);

$el = $dom->getElementsByTagName('uses')[0];

$namespaces = new UsedNamespaces($el);

print_r($namespaces->fullyQualifiedName('DateTime'));
print_r("\n");

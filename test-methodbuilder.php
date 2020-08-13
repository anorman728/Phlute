<?php

/**
 * File to manually test MethodBuilder.
 *
 */

$outputfile = 'test-methodbuilder-output.php';

include_once('./test-setup.php');

$writer = new FileWriter($outputfileFull);
$writer->appendToFile('<?php');
$writer->appendToFile('');

$xml = <<<'__XML'
<?xml version="1.0" encoding="UTF-8"?>
<testdoc>
    <method return="int" name="myfunction">
        <doc>This is a function.</doc>
        <input type="string" name="myvariable" desc="This is a variable."/>
        <content><![CDATA[<?
            $this->setArray([
                1,
                2,
                3,
                [
                    4,
                    5
                ]
            ]);
        ?>]]></content>
    </method>
</testdoc>
__XML;

$dom = new DOMDocument();
$dom->loadXml($xml);
$dom->preserveWhiteSpace = false;

$el = $dom->getElementsByTagName('method')[0];

$methodBuilder = new MethodBuilder($writer, $el, 1);
$methodBuilder->setVisibility('protected');

$methodBuilder->write();

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
    <uses>
        <use value="App\Model\MyModel"/>
        <use value="App\View\MyView"/>
    </uses>
    <method name="myfunction" return="string">
        <doc>This is a function.</doc>
        <input type="string|int" name="myvariable" desc="This is a variable."/>
        <input type="bool" name="model" desc="Model"/>
        <throws exception="Exception" desc="If database connection fails."/>
        <throws exception="InvalidArgumentException" desc="If less than zero."/>
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
$methodBuilder->setUsedNamespaces(new UsedNamespaces($dom->getElementsByTagName('uses')[0]));

$methodBuilder->write();


$writer->appendToFile('');
$writer->appendToFile('// Now testing with abstract functions.');

$xml = <<<'__XML'
<testdoc>
    <uses>
    </uses>
    <method return="void" name="abstFunc" keywords="abstract">
        <doc>This is an abstract function.</doc>
        <input type="?string" name="inputvar" desc="This is an input variable."/>
    </method>
</testdoc>
__XML;

$dom = new DOMDocument();
$dom->loadXml($xml);
$dom->preserveWhiteSpace = false;

$el = $dom->getElementsByTagName('method')[0];

$methodBuilder = new MethodBuilder($writer, $el, 1);
$methodBuilder->setUsedNamespaces(new UsedNamespaces($dom->getElementsByTagname('uses')[0]));
$methodBuilder->write();

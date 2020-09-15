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
    <property type="string" default="7" name="myprop" doc="Test var." keywords="static"/>
</testdoc>
__XML;

$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->loadXML($xml);

$el = $dom->getElementsByTagName('property')[0];

$propertyBuilder = new PropertyBuilder($writer, $el, 1);
$propertyBuilder->setUsedNamespaces(new UsedNamespaces($dom->getElementsByTagName('uses')[0]));
$propertyBuilder->setVisibility('public');

$propertyBuilder->write();
$propertyBuilder->writeSettersAndGetters();

$writer->appendToFile('');

$xml = <<<'__XML'
<?xml version="1.0" encoding="UTF-8"?>
<testdoc>
    <property type="string" name="customgetter">
        <doc>This property should have a custom getter.</doc>
        <getter name="getCustomgetter" doc="Custom getter for customgetter." return="string">
            <content><![CDATA[<?
                $this->getcount++;

                return $this->customgetter;
            ?>]]></content>
        </getter>
    </property>
</testdoc>
__XML;

$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->loadXML($xml);

$el = $dom->getElementsByTagName('property')[0];

$propertyBuilder = new PropertyBuilder($writer, $el, 1);
$propertyBuilder->setUsedNamespaces(new UsedNamespaces($dom->getElementsByTagName('uses')[0]));

$propertyBuilder->writeSettersAndGetters();

$writer->appendToFile('');

$xml = <<<'__XML'
<?xml version="1.0" encoding="UTF-8"?>
<testdoc>
    <property type="int" name="customsetter">
        <doc>This property should have a custom setter.</doc>
        <setter name="setCustomsetter" doc="Custom setter for customsetter.">
            <input name="newCustomSetterVal" type="int"/>
            <content><![CDATA[<?
                $this->setcount++;
                $this->setCustomsetter = $newCustomSetterVal;
            ?>]]></content>
        </setter>
    </property>
</testdoc>
__XML;

$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->loadXML($xml);

$el = $dom->getElementsByTagName('property')[0];

$propertyBuilder = new PropertyBuilder($writer, $el, 1);
$propertyBuilder->setUsedNamespaces(new UsedNamespaces($dom->getElementsByTagName('uses')[0]));

$propertyBuilder->writeSettersAndGetters();

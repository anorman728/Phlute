<?php

/**
 * File to manually test MacroProcessor.
 *
 */

$outputfile = 'test-macroprocessor-output';

include_once('./test-setup.php');

$fileWriter = new FileWriter($outputfileFull);

$xml = <<<__XML
<testdoc>
    <macros>
        <macro name="mymac">This is a macro.  This is "$1", the first argument, and this is "$2", the second argument.</macro>
        <macro name="mysecond">This is another macro.</macro>
    </macros>
</testdoc>
__XML;

$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->loadXml($xml);

$el = $dom->getElementsByTagName('macros')[0];

MacroProcessor::parseMacros($el);

$teststr = 'Testing {{mysecond}} one two {{mymac "testable bestable" four five}} three.';
print_r(MacroProcessor::process($teststr));
print_r("\n");

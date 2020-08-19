#!/usr/bin/env php
<?php


/**
 * Main class to handle XML files and generate output.
 *
 * @author  Andrew Norman
 */
class Main
{
    /** @var DOMDocument The document object representing the xml input.  */
    private $xmlInput;

    /** @var string Default output directory. */
    private $defaultOutputDir;

    // START getters and setters.

    /**
     * Setter for xmlInput.
     *
     * @param   DOMDocument $input
     * @return  void
     */
    public function setXmlInput(DOMDocument $input)
    {
        $this->xmlInput = $input;
    }

    /**
     * Getter for xmlInput.
     *
     * @return  DOMDocument
     */
    public function getXmlInput(): DOMDocument
    {
       return $this->xmlInput;
    }

    /**
     * Setter for defaultOutputDir.
     *
     * @param   string $input
     * @return  void
     */
    public function setDefaultOutputDir(string $input)
    {
        $this->defaultOutputDir = $input;
    }

    /**
     * Getter for defaultOutputDir.
     *
     * @return  string
     */
    public function getDefaultOutputDir(): string
    {
        if (!strlen($this->defaultOutputDir)) {
            throw new Exception('Default output directory doesn\'t seem to be set,'
            . ' but at least one class does not have a hardcoded output'
            . ' directory.');
        }

        return $this->defaultOutputDir;
    }


    // END getters and setters.


    /**
     * Constructor.
     *
     * @param   string  $inputpath
     * @return  void
     */
    public function __construct(string $inputpath)
    {
        $filecontent = file_get_contents($inputpath);

        $this->checkInvalidCData($filecontent);

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $success = $dom->loadXml($filecontent);

        if (!$success) {
            throw new Exception("Invalid XML.  See warnings above.");
        }

        $this->setXmlInput($dom);
    }

    /**
     * Run function.
     *
     * @return  void
     */
    public function run()
    {
        $root = getFirstImmediateChildByName($this->getXmlInput(), 'phlute');

        // Get output directory.
        $this->setDefaultOutputDir(
            $root->getAttribute('default-output')
        );

        // Get the macros.
        $macros = getFirstImmediateChildByName($root, 'macros');
        if (!is_null($macros)) {
            MacroProcessor::parseMacros($macros);
        }

        // Build each individual class.
        foreach (getImmediateChildrenByName($root, 'class') as $node) {
            $this->buildClassFile($node);
        }

        print_r("Done.\n");
    }


    // Helper functions below this line.

    /**
     * Build a class file.
     *
     * This basically serves as a "main" subfunction.
     *
     * @param   DOMElement  $classNode
     * @return  void
     */
    private function buildClassFile(DOMElement $classNode)
    {
        (new ClassBuilder($classNode, $this->buildDirectory($classNode)))->write();
    }

    /**
     * Build a filename from node object and, if needed, the default directory.
     *
     * @param   DOMElement  $classNode
     * @return  string
     */
    private function buildDirectory(DOMElement $classNode): string
    {
        $outputdum = $classNode->getAttribute('output');

        if (strlen($outputdum) > 0) {
            return $outputdum;
        }

        return $this->getDefaultOutputDir();
    }

    /**
     * Check for invalid CData, i.e., if there is a mismatch between the amount
     * of <![CDATA[ and ]]> found.  Throw exception if a problem is found.
     *
     * @param   string  $content
     * @return  void
     * @throws  Exception           If mismatch found.
     */
    private function checkInvalidCData(string $content)
    {
        $open = substr_count($content, '<![CDATA[');
        $close = substr_count($content, ']]>');

        if ($open != $close) {
            throw new Exception("Found mismatch in the amount of"
            . " '<![CDATA[' compared to ']]>'.  If ']]>' is used anywhere in"
            . " PHP code, it needs to be removed!  (You can just add a space"
            . " after the second ].");
        }
    }

}

/**
 * Build an individual class file.  Helper class for Main.
 *
 * @author  Andrew Norman
 */
class ClassBuilder
{
    /** @var DOMElement The node for the class element. */
    private $classNode;

    /** @var string Directory path. */
    private $directoryPath;

    /** @var string Fully-qualified file path. */
    private $filePath;

    /** @var string FileWriter object. */
    private $fileWriter;

    /** @var string[] Array of words in "keyword" attribute. */
    private $keywords;


    // START getters and setters.

    /**
     * Setter for classNode.
     *
     * @param   DOMElement $input
     * @return  void
     */
    public function setClassNode(DOMElement $input)
    {
        $this->classNode = $input;
    }

    /**
     * Getter for classNode.
     *
     * @return  DOMElement
     */
    public function getClassNode(): DOMElement
    {
        return $this->classNode;
    }

    /**
     * Setter for directoryPath.
     *
     * @param   string $input
     * @return  void
     */
    public function setDirectoryPath(string $input)
    {
        $this->directoryPath = $input;
    }

    /**
     * Getter for directoryPath.
     *
     * @return  string
     */
    public function getDirectoryPath(): string
    {
       return $this->directoryPath;
    }

    /**
     * Setter for fileWriter.
     *
     * @param   FileWriter $input
     * @return  void
     */
    public function setFileWriter(FileWriter $input)
    {
        $this->fileWriter = $input;
    }

    /**
     * Getter for fileWriter.
     *
     * @return  FileWriter
     */
    public function getFileWriter(): FileWriter
    {
       return $this->fileWriter;
    }

    /**
     * Setter for keywords.
     *
     * @param   array $input
     * @return  void
     */
    public function setKeywords(array $input)
    {
        $this->keywords = $input;
    }

    /**
     * Getter for keywords.
     *
     * @return  array
     */
    public function getKeywords(): array
    {
       return $this->keywords;
    }


    // END getters and setters.

    /**
     * Constructor.
     *
     * @param   DOMElement  $classNode
     * @param   string      $directoryPath
     * @return  void
     */
    public function __construct(DOMElement $classNode, string $directoryPath)
    {
        $this->setDirectoryPath($directoryPath);
        $this->setClassNode($classNode);
        $this->setFileWriter(new FileWriter($this->buildFilePath()));
        $this->setKeywordsPropertyFromAttribute();
    }

    /**
     * Write everything to file.
     *
     * @return  void
     */
    public function write()
    {
        print_r("Writing " . $this->pullClassName() . " to file.\n");

        // Initiate.
        $this->createDirectoryIfNeeded();
        $this->getFileWriter()->appendToFile('<?php');

        // Build individual parts of class.
        $this->appendNamespace();
        $this->appendUses();
        $this->appendSuperdocs();
        $this->appendDocblock();
        $this->openClass();
        $this->appendTraits();
        $this->appendProperties();
        $this->appendMethods();
        $this->closeClass();
    }


    // Helper functions below this line.

    /**
     * Convert the "keywords" attribute to an array and set the $keywords
     * property.
     *
     * @return  void
     */
    private function setKeywordsPropertyFromAttribute()
    {
        $this->setKeywords(
            explode(' ', $this->getClassNode()->getAttribute('keywords'))
        );
    }

    /**
     * Pull "name" attribute from node.
     *
     * @return  string
     */
    private function pullClassName(): string
    {
        return $this->getClassNode()->getAttribute('name');
    }

    /**
     * Pull the extension declaration (i.e., the "extends <some parent class>"
     * part).
     *
     * If there's nothing to extend, it returns an empty string.
     *
     * @return  string
     */
    private function pullExtension(): string
    {
        $extension = $this->getClassNode()->getAttribute('extends');

        if (strlen($extension) > 0) {
            return " extends $extension";
        }

        return '';
    }

    /**
     * Create 'abstract ' to put before class declaration, or empty string if
     * not an abstract class.
     *
     * @return  string
     */
    private function abstractDeclarationIfApplicable(): string
    {
        if (in_array('abstract', $this->getKeywords())) {
            return 'abstract ';
        }

        return '';
    }

    /**
     * Builder and getter for fully-qualified filepath.
     *
     * @return  string
     */
    private function buildFilePath(): string
    {
        if (is_null($this->filePath)) {
            $this->filePath = $this->getDirectoryPath() . '/' . $this->pullClassName() . '.php';
        }

        return $this->filePath;
    }

    /**
     * Add the namespace line.
     *
     * @return  void
     */
    private function appendNamespace()
    {
        $namespacedum = $this->getClassNode()->getAttribute('namespace');
        $this->getFileWriter()->appendToFile("namespace $namespacedum;");
        $this->getFileWriter()->appendToFile('');
    }

    /**
     * Add the 'uses' references.
     *
     * @return  void
     */
    private function appendUses()
    {
        $usesElement = $this->getClassNode()->getElementsByTagName('uses')->item(0);

        if (is_null($usesElement)) {
            // If there isn't a usesElement, do nothing.
            return;
        }

        foreach ($usesElement->childNodes as $el) {
            $this->getFileWriter()->appendToFile('use ' . $el->getAttribute('value') . ';');
        }

        $this->getFileWriter()->appendToFile('');
    }

    /**
     * Append superdocs (extra docblocks that are printed above the normal
     * class docblock).
     *
     * @return  void
     */
    private function appendSuperdocs()
    {
        $superdocs = getFirstImmediateChildByName(
            $this->getClassNode(),
            'superdocs'
        );

        if (is_null($superdocs) || $superdocs->childNodes->length == 0) {
            // Do nothing.
            return;
        }

        $docBuilder = new DocblockBuilder($this->getFileWriter());

        foreach ($superdocs->childNodes as $node) {
            if ($node->nodeName != 'superdoc') {
                // Have to have this because DOMDocument counts line breaks as
                // nodes.
                continue;
            }

            $docBuilder->setDescription($node->textContent);
            // Superdocs have no attributes (at least at the moment), so don't
            // bother with adding them.
            $docBuilder->write();
            $this->getFileWriter()->appendToFile('');
        }
    }

    /**
     * Write docblock for class.
     *
     * @return  void
     */
    private function appendDocblock()
    {
        $docBuilder = new DocblockBuilder($this->getFileWriter());

        // Description.
        $docBuilder->setDescription(getNodeText(
            getFirstImmediateChildByName($this->getClassNode(), 'doc')
        ));

        // Author, if applicable.
        $author = $this->getClassNode()->getAttribute('author');
        if ($author) {
            $docBuilder->addAttribute('author', [$author]);
        }

        $docBuilder->write();
    }

    /**
     * Start the opening class declaration.
     *
     * @return  void
     */
    private function openClass()
    {
        $openline = $this->abstractDeclarationIfApplicable();
        $openline.= 'class ';
        $openline.= $this->pullClassName();
        $openline.= $this->pullExtension();

        $this->getFileWriter()->appendToFile($openline);
        $this->getFileWriter()->appendToFile('{');
    }

    /**
     * Close the class brackets.
     *
     * @return  void
     */
    private function closeClass()
    {
        $this->getFileWriter()->appendToFile('}');
    }

    /**
     * Append traits to the top of the class.
     *
     * @return  void
     */
    private function appendTraits()
    {
        $traits = getFirstImmediateChildByName($this->getClassNode(), 'traits');

        if (is_null($traits)) {
            // If nothing provided, then do nothing.
            return;
        }

        foreach (getImmediateChildrenByName($traits, 'trait') as $trait) {
            $this->getFileWriter()->appendToFile(
                'use ' . $trait->getAttribute('value') . ';',
                1
            );
        }

        $this->getFileWriter()->appendToFile('');
    }

    /**
     * Append properties to file.
     *
     * @return  void
     */
    private function appendProperties()
    {
        $properties = getFirstImmediateChildByName(
            $this->getClassNode(), 'properties');

        if (is_null($properties)) {
            // If nothing provided, then do nothing.
            return;
        }

        // Start with the constants (which are grouped with the variable
        // properties).
        foreach (getImmediateChildrenByName($properties, 'constant') as $constNode) {
            (new ConstantBuilder($this->getFileWriter(), $constNode, 1))->write();
        }


        // Now handle the properties.

        $propertiesArr = [];
        $propertiesRaw = getImmediateChildrenByName($properties, 'property');

        // Collect all properties into array.
        foreach ($propertiesRaw as $childNode) {
            $propertiesArr[] = new PropertyBuilder(
                $this->getFileWriter(), $childNode, 1);
        }

        // Actually add the properties.
        foreach ($propertiesArr as $property) {
            $property->write();
        }

        // Add the getters and setters.
        $this->getFileWriter()->appendToFile('');
        $this->getFileWriter()->appendToFile('// START getters and setters.', 1);

        foreach ($propertiesArr as $property) {
            $property->writeSettersAndGetters();
        }

        $this->getFileWriter()->appendToFile('');
        $this->getFileWriter()->appendToFile('// END getters and setters.', 1);


        // Add extra space after all is done.
        $this->getFileWriter()->appendToFile('');
    }

    /**
     * Append methods.
     *
     * @return  void
     */
    private function appendMethods()
    {
        $allMethods = getFirstImmediateChildByName($this->getClassNode(), 'methods');

        if (is_null($allMethods)) {
            // Nothing provided, so do nothing.
            return;
        }

        foreach (['public', 'protected', 'private'] as $group) {
            $methodParentObject = getFirstImmediateChildByName($allMethods, $group);

            if (is_null($methodParentObject)) {
                // User did not define any of this type of method.
                continue;
            }

            $methods = getImmediateChildrenByName($methodParentObject, 'method');

            if (count($methods) == 0) {
                // User created parent element, but put in no methods.
                continue;
            }

            if ($group == 'private') {
                for ($i = 0; $i < 2; $i++) {
                    $this->getFileWriter()->appendToFile('');
                }
                $this->getFileWriter()->appendToFile(
                    '// Helper functions below this line.', 1);
            }

            foreach ($methods as $method) {
                $methodBuilder = new MethodBuilder(
                    $this->getFileWriter(), $method, 1);
                $methodBuilder->setVisibility($group);
                $methodBuilder->write();
            }
        }
    }

    /**
     * Create the directory for the class, if it does not already exist.
     *
     * @return  void
     */
    private function createDirectoryIfNeeded()
    {
        if (!file_exists($this->getDirectoryPath())) {
            // 0777 is default mode value.  Last arg is to make it recursive.
            mkdir($this->getDirectoryPath(), 0777, true);
        }
    }

}

/**
 * Manage writing to a particular file.
 *
 * @author  Andrew Norman
 */
class FileWriter
{
    /** @var string File path. */
    private $filepath;

    /**
     * Setter for filepath.
     *
     * @param   string $input
     * @return  void
     */
    public function setFilepath(string $input)
    {
        $this->filepath = $input;
    }

    /**
     * Getter for filepath.
     *
     * @return  string
     */
    public function getFilepath(): string
    {
       return $this->filepath;
    }

    /**
     * Constructor.
     *
     * @param   string  $filepath
     * @return  void
     */
    public function __construct(string $filepath)
    {
        if (file_exists($filepath)) {
            throw new Exception("$filepath already exists.");
        }

        $this->setFilepath($filepath);
    }

    /**
     * Append a line to the output file.
     *
     * @param   string  $content
     * @param   int     $indentlvl      Defaults to 0.
     * @return  void
     */
    public function appendToFile(string $content, int $indentlvl = 0)
    {
        $lineContent = strlen($content) > 0
            ? (buildIndent($indentlvl) . $content)
            : ''
        ;

        file_put_contents(
            $this->getFilePath(),
            $lineContent . "\n",
            FILE_APPEND
        );
    }

}

/**
 * Build a docblock.  Helper class for ClassBuilder, PropertyBuilder, and
 * FunctionBuilder.
 *
 * NOTE: Every function here handles its own indentation, so appendToFile should
 * never have an indent value passed to it.
 *
 * (And I do realize that this is less-than-ideal design, because that makes it
 * inconsistent.)
 *
 * @author  Andrew Norman
 */
class DocblockBuilder
{
    /** @var FileWriter FileWriter object. */
    private $fileWriter;

    /** @var int Indent level. */
    private $indentlvl;

    /** @var string Description string. */
    private $description;


    // START getters and setters.

    /**
     * Array of attribute arrays.
     *
     * Keys are 'type' (like `@param`), and 'data', an array containing at most
     * three elements (the third one will be treated as a description and put on
     * another line).
     *
     * @var array
     */
    private $attributeArrays = [];

    /**
     * Setter for fileWriter.
     *
     * @param   FileWriter $input
     * @return  void
     */
    public function setFileWriter(FileWriter $input)
    {
        $this->fileWriter = $input;
    }

    /**
     * Getter for fileWriter.
     *
     * @return  FileWriter
     */
    public function getFileWriter(): FileWriter
    {
        return $this->fileWriter;
    }

    /**
     * Setter for indentlvl.
     *
     * @param   int $input
     * @return  void
     */
    public function setIndentlvl(int $input)
    {
        $this->indentlvl = $input;
    }

    /**
     * Getter for indentlvl.
     *
     * @return  int
     */
    public function getIndentlvl(): int
    {
        return $this->indentlvl;
    }

    /**
     * Setter for description.
     *
     * @param   string $input
     * @return  void
     */
    public function setDescription(string $input)
    {
        $this->description = $input;
    }

    /**
     * Getter for description.
     *
     * @return  string
     */
    public function getDescription(): string
    {
        if (is_null($this->description) || (strlen($this->description) == 0)) {
            throw new Exception("Missing description in docblock.");
        }

        return $this->description;
    }

    /**
     * Getter for attributeArrays.
     *
     * @return  array
     */
    public function getAttributeArrays(): array
    {
        return $this->attributeArrays;
    }

    // END getters and setters.


    /**
     * Constructor.
     *
     * @param   FileWriter  $fileWriter
     * @param   int         $indentlvl
     * @return  void
     */
    public function __construct(FileWriter $fileWriter, int $indentlvl = 0)
    {
        $this->setFileWriter($fileWriter);
        $this->setIndentlvl($indentlvl);
    }

    /**
     * Add an attribute.
     *
     * @param   string  $type
     *  Like `@param`, though without the @.
     * @param   array   $data
     *  All information past the type (like the data type, variable name, for
     *  params).  Can be empty for nothing past the type, and can be up to three
     *  in length, with the third one being treated as a description and placed
     *  on another line.  Defaults to empty array.
     * @return  void
     */
    public function addAttribute(string $type, array $data= [])
    {
        $this->attributeArrays[] = ['type' => $type, 'data' => $data];
    }

    /**
     * Write the docblock to file.
     *
     * @return  void
     */
    public function write()
    {
        $this->initializeDocblock();

        $this->writeDescriptionToFile();

        if (count($this->getAttributeArrays()) != 0) {
            $this->addBlankLine();

            $this->writeAttributesToFile();

        }

        $this->finalizeDocblock();

    }


    // Helper functions below this line.

    /**
     * Write the first line to file.
     *
     * @return  void
     */
    private function initializeDocblock()
    {
        $this->getFileWriter()->appendToFile(
            buildIndent($this->getIndentLvl()) . '/**'
        );
    }

    /**
     * Write the last line to file.
     *
     * @return  void
     */
    private function finalizeDocblock()
    {
        $this->getFileWriter()->appendToFile(
            buildIndent($this->getIndentLvl()) . ' */'
        );
    }

    /**
     * Add a blank docblock line.
     *
     * @return  void
     */
    private function addBlankLine()
    {
        $this->getFileWriter()->appendToFile($this->buildDocblockLine());
    }

    /**
     * Build a line for a docblock.
     *
     * @param   string  $content        Content, defaulting to empty string.
     * @return  string
     */
    private function buildDocblockLine($content = ''): string
    {
        $sp = (strlen($content) == 0) ? '' : ' ';

        return buildIndent($this->getIndentLvl()) . ' *' . $sp . $content;
    }

    /**
     * Add description to file.
     *
     * @return  void
     */
    private function writeDescriptionToFile()
    {
        foreach ($this->buildDocblockDescArray($this->getDescription()) as $line) {
            $this->getFileWriter()->appendToFile($line);
        }
    }

    /**
     * Build docblock description array from string input.
     *
     * (This one seriously stinks.)
     *
     * @param   string  $input
     * @param   bool    $indent
     *  Added indent (for parameter desc).  Defaults to empty string.
     * @return  array
     */
    private function buildDocblockDescArray(
        string $input,
        string $indent = ''
    ): array {
        $str = $input;
        $str = trim($str); // Remove whitespace on ends.

        $str = preg_replace('/[\r\n](\s+)?[\r\n]/', "##PARA4672##\r\n", $str);
        // Replace paragraph breaks with marker.
        $str = preg_replace('/[\r\n](\s)+/', ' ' , $str);
        // Remove whitespace after line breaks.
        $paragraphs = explode('##PARA4672##', $str);
        // Break paragraphs into different strings.

        $output = [];
        $maxind = count($paragraphs) - 1;

        foreach ($paragraphs as $ind => $paragraph) {
            $paragraph = trim($paragraph);
            // Not 100% sure why this is necessary, but there seems to be an
            // extra space before each paragraph otherwise.

            while (strlen($paragraph) > 0) {
                $paragraph = $this->buildDocblockLine($indent . $paragraph);

                if(strlen($paragraph) < 81) {
                    $outputDum = $paragraph;
                    $paragraph = '';
                } else {
                    $breakind = findLastBreak($paragraph);
                    $outputDum = substr($paragraph, 0, $breakind);
                    $paragraph = substr($paragraph, $breakind+1);
                }

                $output[] = $outputDum;
            }
            if ($ind != $maxind) {
                $output[] = $this->buildDocblockLine();
                // Empty line after each paragraph, except the last one.
            }
        }

        return $output;
    }

    /**
     * Build an attribute in a docblock (like @param, etc.).
     *
     * @param   string  $defname
     *  Minus the @ sign.
     * @param   array   $contentArr
     *  Array, like ['int', '$count', 'My description'].  Minimum of one, max of
     *  three.
     * @param   int     $indent
     *  Indentation level, defaulting to 0.
     * @return  array
     */
    private function buildDocblockAttribute(
        string $defname,
        array $contentArr
    ): array {
        $line1 = $this->buildDocblockLine('@' . $defname);
        $line1 = addPseudoTab($line1);
        $line1.= $contentArr[0];

        if (isset($contentArr[1])) {
            $line1 = addPseudoTab($line1);
            $line1.= $contentArr[1];
        }

        $returnArr = [$line1];

        if (isset($contentArr[2])) {
            $returnArr = array_merge($returnArr, $this->buildDocblockDescArray($contentArr[2], ' '));
        }

        return $returnArr;

    }

    /**
     * Write attributes to file.
     *
     * @return  void
     */
    private function writeAttributesToFile()
    {
        foreach ($this->getAttributeArrays() as $attribute) {
            $dumarr = $this->buildDocblockAttribute(
                $attribute['type'],
                $attribute['data']
            );

            foreach ($dumarr as $line) {
                $this->getFileWriter()->appendToFile($line);
            }

        }
    }

}

/**
 * Abstract class to act as parent class to PropertyBuilder and MethodBuilder.
 *
 * @author  Andrew Norman
 */
abstract class ElementBuilder
{
    /** @var FileWriter FileWriter object. */
    private $fileWriter;

    /** @var int Indent level. */
    private $indentlvl;

    /** @var DOMElement DOMElement object representing the property or method. */
    private $elementNode;

    /** @var string[] Array of words in "keyword" attribute. */
    private $keywords;


    // START getters and setters.

    /**
     * Setter for fileWriter.
     *
     * @param   FileWriter $input
     * @return  void
     */
    public function setFileWriter(FileWriter $input)
    {
        $this->fileWriter = $input;
    }

    /**
     * Getter for fileWriter.
     *
     * @return  FileWriter
     */
    public function getFileWriter(): FileWriter
    {
       return $this->fileWriter;
    }

    /**
     * Setter for indentlvl.
     *
     * @param   int $input
     * @return  void
     */
    public function setIndentlvl(int $input)
    {
        $this->indentlvl = $input;
    }

    /**
     * Getter for indentlvl.
     *
     * @return  int
     */
    public function getIndentlvl(): int
    {
       return $this->indentlvl;
    }

    /**
     * Setter for elementNode.
     *
     * @param   DOMElement $input
     * @return  void
     */
    public function setElementNode(DOMElement $input)
    {
        $this->elementNode = $input;
    }

    /**
     * Getter for elementNode.
     *
     * @return  DOMElement
     */
    public function getElementNode(): DOMElement
    {
       return $this->elementNode;
    }

    /**
     * Setter for keywords.
     *
     * @param   array $input
     * @return  void
     */
    public function setKeywords(array $input)
    {
        $this->keywords = $input;
    }

    /**
     * Getter for keywords.
     *
     * @return  array
     */
    public function getKeywords(): array
    {
       return $this->keywords;
    }


    // END getters and setters.

    /**
     * Constructor.
     *
     * @param   FileWriter  $fileWriter
     * @param   DOMElement  $node
     * @param   int         $indentlvl      Defaults to 0.
     * @return  void
     */
    public function __construct(
        FileWriter $fileWriter,
        DOMElement $node,
        int $indentlvl = 1
    ) {
        $this->setFileWriter($fileWriter);
        $this->setElementNode($node);
        $this->setIndentlvl($indentlvl);
        $this->setKeywordsPropertyFromAttribute();
    }

    /**
     * Write to file.
     *
     * @return  void
     */
    abstract function write();


    // Helper functions below this line.

    /**
     * Get an attribute from the node.
     *
     * @param   string  $attName
     * @return  string
     */
    protected function getAttribute(string $attName): string
    {
        return $this->getElementNode()->getAttribute($attName);
    }

    /**
     * Get a immediate children of the element node by name.
     *
     * @param   string  $name
     * @return  array
     */
    protected function getImmediateChildrenByName(string $name): array
    {
        return getImmediateChildrenByName($this->getElementNode(), $name);
    }

    /**
     * Determine if element is declared as static.
     *
     * @return  bool
     */
    protected function isStatic(): bool
    {
        return in_array('static', $this->getKeywords());
    }

    /**
     * Convert the "keywords" attribute to an array and set the $keywords
     * property.
     *
     * @return  void
     */
    private function setKeywordsPropertyFromAttribute()
    {
        $this->setKeywords(
            explode(' ', $this->getElementNode()->getAttribute('keywords'))
        );
    }

}

/**
 * Build a property.  Helper class for ClassBuilder.
 *
 * @author  Andrew Norman
 */
class PropertyBuilder extends ElementBuilder
{
    /**
     * {@inheritDoc}
     *
     */
    public function write()
    {
        $docblock = new DocBlockBuilder($this->getFileWriter(), $this->getIndentlvl());
        $docblock->setDescription($this->getAttribute('doc'));
        $docblock->addAttribute('var', [$this->getAttribute('type')]);
        $docblock->write();

        $this->writeDeclaration();

        $this->getFileWriter()->appendToFile('');
    }

    /**
     * Writer the setter and getter iff applicable.
     *
     * @return  void
     */
    public function writeSettersAndGetters()
    {
        $this->writeSetter();
        $this->writeGetter();
    }


    // Helper functions below this line.

    /**
     * Write the declaration to file.
     *
     * @return  void
     */
    private function writeDeclaration()
    {
        $stat = $this->isStatic() ? 'static ' : '';
        $declaration = "private {$stat}\$" . $this->getAttribute('name');

        if (strlen($this->getAttribute('default')) > 0) {
            $declaration.= ' = ' . $this->buildDefaultValueString();
        }

        $declaration.= ';';

        $this->getFileWriter()->appendToFile($declaration, $this->getIndentlvl());
    }

    /**
     * Build the default value string.  Helper function for writeDeclaration.
     *
     * @return  string
     */
    private function buildDefaultValueString(): string
    {
        $returnVal = $this->getAttribute('default');

        if ($this->getAttribute('type') == 'string') {
            return "\"$returnVal\"";
        }

        return $returnVal;
    }

    /**
     * Write the getter function iff it's not disabled by xml.
     *
     * @return  void
     */
    private function writeGetter()
    {
        if ($this->getAttribute('getter') === '0') {
            // Do nothing; user disabled getter for this property.
            return;
        }

        $name = $this->getAttribute('name');

        // Want to dynamically create method element from scratch.
        $domDum = new DOMDocument('1.0', 'utf-8');

        $el = $domDum->createElement('method');
        $el->setAttribute('return', $this->getAttribute('type'));
        $el->setAttribute('name', 'get' . ucfirst($name));
        $el->setAttribute('keywords', ($this->isStatic() ? 'static' : ''));

        $doc = $domDum->createElement('doc', "Getter for $name.");
        $el->appendChild($doc);

        $contentContainer = $domDum->createElement('content');
        $content = $domDum->createCDATASection('return $this->' . $name . ';');
        $contentContainer->appendChild($content);
        $el->appendChild($contentContainer);

        $this->writeMethodElToFile($el);

    }

    /**
     * Write the setter function iff it's not disabled byxml.
     *
     * @return  void
     */
    private function writeSetter()
    {
        // I feel like there should be a way to synthesize this and writeGetter
        // to avoid code duplication, but they're actually fairly different from
        // each other.

        if ($this->getAttribute('setter') === '0'){
            // Do nothing; user disabled setter for this property.
            return;
        }

        $name = $this->getAttribute('name');

        // Want to dynamically create method element from scratch.

        $domDum = new DOMDocument('1.0', 'utf-8');

        $el = $domDum->createElement('method');
        $el->setAttribute('return', 'void');
        $el->setAttribute('name', 'set' . ucfirst($name));
        $el->setAttribute('keywords', ($this->isStatic() ? 'static' : ''));

        $doc = $domDum->createElement('doc', "Setter for $name.");
        $el->appendChild($doc);

        $input = $domDum->createElement('input');
        $input->setAttribute('type', $this->getAttribute('type'));
        $input->setAttribute('name', 'input');
        $el->appendChild($input);

        $contentContainer = $domDum->createElement('content');
        $content = $domDum->createCDATASection('$this->' . $name . ' = $input;');
        $contentContainer->appendChild($content);
        $el->appendChild($contentContainer);

        $this->writeMethodElToFile($el);
    }

    /**
     * Write a method to file from DOMElement object.
     *
     * @param   DOMElement  $el
     * @return  void
     */
    private function writeMethodElToFile(DOMElement $el)
    {
        (new MethodBuilder(
            $this->getFileWriter(),
            $el,
            $this->getIndentlvl()
        ))->write();
    }

}

/**
 * Build a constant.  Helper class for ClassBuilder.
 *
 * @author  Andrew Norman
 */
class ConstantBuilder extends ElementBuilder
{
    /**
     * {@inheritDoc}
     *
     */
    public function write()
    {
        $docblock = new DocBlockBuilder($this->getFileWriter(), $this->getIndentlvl());
        $docblock->setDescription($this->getAttribute('doc'));
        $docblock->addAttribute('var', [$this->getAttribute('type')]);
        $docblock->write();

        $this->writeAssignment();

        $this->getFileWriter()->appendToFile('');
    }

    /**
     * Write the assignment itself.
     *
     * @return  void
     */
    private function writeAssignment()
    {
        $assignment =
            'const '
            . $this->getAttribute('name')
            . ' = '
            . $this->getAttribute('value')
            . ';'
        ;

        $this->getFileWriter()->appendToFile($assignment, $this->getIndentlvl());
    }

}

/**
 * Build a method.  Helper class for ClassBuilder and PropertyBuilder.
 *
 * @author  Andrew Norman
 */
class MethodBuilder extends ElementBuilder
{
    /** @var string Visibility, as plain string. */
    private $visibility = 'public';

    /**
     * Setter for visibility.
     *
     * @param   string $input
     * @return  void
     */
    public function setVisibility(string $input)
    {
        $this->visibility = $input;
    }

    /**
     * Getter for visibility.
     *
     * @return  string
     */
    public function getVisibility(): string
    {
       return $this->visibility;
    }


    /**
     * {@inheritDoc}
     *
     */
    public function write()
    {
        $this->getFileWriter()->appendToFile('');

        $this->writeDocblock();

        $this->writeFunction();

    }


    // Helper functions below this line.

    /**
     * Write the docblock for the method.
     *
     * @return  void
     */
    private function writeDocblock()
    {
        // Build docblock.
        $docblock = new DocBlockBuilder($this->getFileWriter(), $this->getIndentlvl());
        $docblock->setDescription(
            getNodeText($this->getImmediateChildrenByName('doc')[0])
        );

        foreach ($this->getImmediateChildrenByName('input') as $input) {
            $dets = [$input->getAttribute('type'), '$' . $input->getAttribute('name')];

            if (strlen($input->getAttribute('desc')) > 0) {
                $dets[] = $input->getAttribute('desc');
            }

            $docblock->addAttribute('param', $dets);
        }

        $returnVal = $this->getAttribute('return');
        $docblock->addAttribute('return', [$returnVal]);
        $docblock->write();

    }

    /**
     * Write the function itself.
     *
     * @return  void
     */
    private function writeFunction()
    {
        $this->writeFunction_Signature();

        if ($this->isAbstract()) {
            // Nothing to do-- Abstract functions don't have content.
            return;
        }

        $this->getFileWriter()->appendToFile('{', $this->getIndentlvl());

        $this->writeFunction_Content();

        $this->getFileWriter()->appendToFile('}', $this->getIndentlvl());
    }

    /**
     * Write the function signature.
     *
     * @return  void
     */
    private function writeFunction_Signature()
    {
        $vis = $this->getVisibility();
        $stat = $this->isStatic() ? ' static' : '';
        $name = $this->getAttribute('name');
        $args = implode(',', $this->buildArgumentsArray());

        $returnType = $this->getAttribute('return');
        $returnType = (($returnType == 'void') || (strlen($returnType) == 0))
            ? ''
            : ": $returnType"
        ;

        $declaration = "{$vis}{$stat} function $name($args)$returnType";

        if ($this->isAbstract()) {
            $declaration = "abstract $declaration;";
        }

        $this->getFileWriter()->appendToFile($declaration, $this->getIndentlvl());
    }

    /**
     * Build arguments to go into signature.
     *
     * @return  void
     */
    private function buildArgumentsArray()
    {
        $inputs = $this->getImmediateChildrenByName('input');

        $returnArr = [];
        foreach ($inputs as $input) {
            $returnEl = '$' . $input->getAttribute('name');

            $typedum = $input->getAttribute('type');
            if (strlen($typedum) > 0) {
                $returnEl = "$typedum $returnEl";
            }

            $returnArr[] = $returnEl;
        }

        return $returnArr;
    }

    /**
     * Write the content of the function.
     *
     * @return  void
     */
    private function writeFunction_Content()
    {
        $contentRaw = $this->getImmediateChildrenByName('content');

        $dumIndent = $this->getIndentlvl() + 1;
        // Indented one extra level for body.

        if (count($contentRaw) == 0) {
            // Content is not defined, so we mark it with a todo.
            $this->getFileWriter()->appendToFile('// Todo.', $dumIndent);
        } else {
            foreach ((new CDataHandler($contentRaw[0]))->build() as $line) {
                $this->getFileWriter()->appendToFile($line, $dumIndent);
            }
        }
    }

    /**
     * Determine if function is abstract.
     *
     * @return  bool
     */
    private function isAbstract(): bool
    {
        return in_array('abstract', $this->getKeywords());
    }

}

/**
 * Handle a CDATA element, converting to array of lines, appropriately spaced.
 *
 * @author  Andrew Norman
 */
class CDataHandler
{
    /** @var DOMElement CData node. */
    private $cDataNode;

    /**
     * Setter for cDataNode.
     *
     * @param   DOMElement $input
     * @return  void
     */
    public function setCDataNode(DOMElement $input)
    {
        $this->cDataNode = $input;
    }

    /**
     * Getter for cDataNode.
     *
     * @return  DOMElement
     */
    public function getCDataNode(): DOMElement
    {
       return $this->cDataNode;
    }


    /**
     * Constructor.
     *
     * @param   DOMElement  $cDataNode
     * @return  void
     */
    public function __construct(DOMElement $cDataNode)
    {
        $this->setCDataNode($cDataNode);
    }

    /**
     * Build an array of individual lines, with indentation that's been
     * corrected from being in XML format.
     *
     * @return  array
     */
    public function build(): array
    {
        $contentArr = explode("\n", $this->getCDataNode()->textContent);

        $this->removePhpTags($contentArr);

        if (count($contentArr) == 1) {
            // User didn't use multiple lines, so there's no space to normalize.
            // But do want to trim it.
            return [trim($contentArr[0])];
        }

        $this->normalizeWhitespace($contentArr);

        $this->unsetEmptyEdges($contentArr);

        return $contentArr;
    }


    // Helper functions below.

    // Arrays are always passed by reference here, unless marked otherwise!!

    /**
     * Remove opening and closing PHP tags.
     *
     * These tags are in CDATA just to make the syntax-highlighting work
     * correctly, and they shouldn't be in end results.
     *
     * Note that this will actually work the same even if all of <content> is on
     * one line.
     *
     * @param   array   $contentArr     Passed by reference.
     * @return  void
     */
    private function removePhpTags(array &$contentArr)
    {
        $lastInd = count($contentArr) - 1;
        $contentArr[0] = preg_replace('/^\<\?(php)?/', '', $contentArr[0]);
        $contentArr[$lastInd] = preg_replace('/\?\>$/', '', $contentArr[$lastInd]);
    }

    /**
     * "Normalize" whitespace, i.e., make the indentation for each individual
     * line what it should be in the final product, minus the base indentation.
     *
     * (There's a very good chance I'm not using the term "normalize" perfectly
     * correctly.)
     *
     * @param   array   $contentArr     Passed by reference.
     * @return  void
     */
    private function normalizeWhitespace(array &$contentArr) {
        $splen = $this->findSmallestIndent($contentArr);

        foreach ($contentArr as $key => $line) {
            $contentArr[$key] = preg_replace(
                '/^\s{' . $splen . '}/',
                '',
                $contentArr[$key]
            );
        }
    }

    /**
     * Find the smallest amount of space in the beginning of each string.
     *
     * @param   array   $contentArr     *Not* passed by reference.
     * @return  int
     */
    private function findSmallestIndent(array $contentArr): int
    {
        $splen = null;

        foreach ($contentArr as $key => $line) {
            if (strlen($line) == 0 || $key == 0) {
                // Skip first line-- It starts at the end of the CDATA declaration.
                // Also skip empty lines.
                continue;
            }

            if (strlen(trim($line)) == 0) {
                // Skip lines of *just* whitespace.
                continue;
            }

            preg_match('/^\s+/', $line, $matcharr);

            $dumlen = strlen($matcharr[0]);
            if (is_null($splen) || $dumlen < $splen) {
                $splen = $dumlen;
            }
        }

        return $splen;
    }

    /**
     * Unset first and last lines iff they are empty.
     *
     * @param   array   $contentArr     Passed by reference.
     * @return  void
     */
    private function unsetEmptyEdges(array &$contentArr)
    {
        $this->unsetIfEmpty($contentArr, count($contentArr) - 1);
        $this->unsetIfEmpty($contentArr, 0);
    }

    /**
     * Unset a particular line (defined by index) in an array iff it's empty or
     * only whitespace.
     *
     * @param   array   $contentArr     Passed by reference.
     * @param   int     $ind
     * @return  void
     */
    private function unsetIfEmpty(array &$contentArr, int $ind)
    {
        if (strlen(trim($contentArr[$ind])) == 0) {
            unset($contentArr[$ind]);
        }
    }

}

/**
 * Collection of static variables and functions to run lines pulled from XML
 * through a process to search-and-replace with defined macros.
 *
 * @author  Andrew Norman
 */
class MacroProcessor
{
    /**
     * Array mapping name of macro to its corresponding string.
     *
     * @var array
     */
    private static $macros = [];

    /**
     * Parse macros from XML.
     *
     * @param   DOMNode $macronode
     * @return  void
     */
    public static function parseMacros(DOMNode $macronode)
    {
        foreach (getImmediateChildrenByName($macronode, 'macro') as $node) {
            static::$macros[$node->getAttribute('name')] = $node->textContent;
        }
    }

    /**
     * Process a string according to the macros.
     *
     * @param   string  $nodedata
     * @return  string
     */
    public static function process(string $nodedata): string
    {
        preg_match_all('/{{.*?}}/', $nodedata, $matches);

        if (count($matches) == 0) {
            return $nodedata;
        }

        foreach ($matches[0] as $match) {
            $nodedata = static::modifyString($nodedata, $match);
        }

        return $nodedata;
    }


    // Helper functions below this line.

    /**
     * Modify string to replace with macro data.
     *
     * @param   string  $nodedata
     * @param   string  $match
     * @return  string
     */
    private static function modifyString(string $nodedata, $match): string
    {
        $maccall = trim(trim($match, '{'), '}');
        // Remove braces and make a copy (the copy part is important!)

        // Get the name of the macro.
        $key = '';
        static::next($maccall, $key);

        // Get the macro definition.  This will be modified if arguments are
        // passed.
        $mac = static::getMacroString($key);

        // For each argument passed, replace the value in the macro.
        $next = '';
        $i = 1;

        // Note that if there are no arguments passed, then $maccall will
        // already be an empty string.
        while (strlen($maccall) > 0) {
            static::next($maccall, $next);
            $mac = str_replace("\$$i", $next, $mac);
            $i++;
        }

        return str_replace($match, $mac, $nodedata);

    }

    /**
     * Find the "next" argument in a macro call, remove it from $maccall, and
     * set $next as as new value.  (So both arguments are passed by reference!)
     *
     * For example, in the macro call "mymac "testing 123" seven", we're calling
     * the macro called "mymac" using the arguments "testing 123" and "seven"
     * (notice there are no quotes around seven in the actual call).  So,
     * calling on this string the first time will set $next as "mymac" and
     * remove "mymac " from the string.  We call next again to get the
     * arguments.
     *
     * @param   string  $maccall
     *  The string used to call the macro.  Passed by reference, and it will be
     *  changed!
     * @param   string  $next
     *  The next value to pull out of the $maccall.
     * @return  void
     */
    private static function next(string &$maccall, string &$next)
    {
        if (substr($maccall, 0, 1) == '"') {
            // If start with quote, find next quote.
            $nextstop = strpos($maccall, '"', 1);

            $next       = substr($maccall, 1, $nextstop - 1);
            // Start at 1, stop short 1, because don't want to include the
            // actual quotes.

            // Remove $next from $maccall.
            $maccall    = trim(substr($maccall, $nextstop + 1));
            // Trim because there is probably a space after the closing quote,
            // but there may not be.

        } elseif (strpos($maccall, ' ')) {
            // Else if contains a space, find next space.
            $nextstop = strpos($maccall, ' ');

            $next = substr($maccall, 0, $nextstop);

            // Remove $next from $maccall.
            $maccall = substr($maccall, $nextstop + 1);
        } else {
            // No spaces, no quotes, so this must be the last one.
            $next = $maccall;
            $maccall = '';

        }
    }

    /**
     * Get macro string.
     *
     * @param   string  $key
     * @return  string
     * @throws  Exception
     *  If macro does not exist.
     */
    private static function getMacroString(string $key): string
    {
        if (!array_key_exists($key, static::$macros)) {
            throw new Exception("Macro \"$key\" is not defined.");
        }

        return static::$macros[$key];

    }

}


// Shared functions below.

/**
 * Build an indent level.
 *
 * @param   int     $indentlvl
 * @return  string
 */
function buildIndent(int $indentlvl): string
{
    return str_repeat(' ', 4 * $indentlvl);
}

/**
 * Find all *immediate* children of an element of a particular name.
 *
 * @param   DOMNode     $node
 * @param   string      $name
 * @return  array
 */
function getImmediateChildrenByName(DOMNode $node, string $name): array
{
    // This is ten thousand times easier in Dart.
    $returnArr = [];

    foreach ($node->childNodes as $childNode) {
        if ($childNode->nodeName == $name) {
            $returnArr[] = $childNode;
        }
    }

    return $returnArr;
}

/**
 * Find the first *immediate* child by of en element of a particular name.
 * Return null if there aren't any.
 *
 * @param   DOMNode         $node
 * @param   string          $name
 * @return  DOMNode|null
 */
function getFirstImmediateChildByName(DOMNode $node, string $name)
{
    $dumarr = getImmediateChildrenByName($node, $name);

    if (count($dumarr) == 0) {
        return null;
    }

    return $dumarr[0];
}

/**
 * Find the index of the last space character before the 80th column.
 *
 * @param   string  $input
 * @return  int
 */
function findLastBreak(string $input): int
{
    $cursor = 80;
    while ($cursor > -1 && substr($input, $cursor, 1) != ' ') {
        $cursor--;
    }

    return $cursor;
}

/**
 * Add a pseudo-tab.  Basically add spaces to the end of a string until its
 * length is divisible by four.
 *
 * @param   string  $input
 * @return  string
 */
function addPseudoTab(string $input): string
{
    do {
        $input.=' ';
    } while ((strlen($input) % 4) != 0);

    return $input;
}

/**
 * Get a node's text (the nodeValue), and do any preprocessing we need to do
 * before returning it.
 *
 * With the one exception of CData, we never get nodeValue or textContent
 * directly.  We always use this function, because we want to run it through the
 * MacroProcessor.
 *
 * @param   DOMNode $node
 * @return  string
 */
function getNodeText(DOMNode $node): string
{
    return MacroProcessor::process($node->nodeValue);
}


// Main script below.

if (!isset($argv[1])) {
    print("Missing input xml file.\n");
    // Don't want to exit, because test files are going to be using this.
} else {
    (new Main($argv[1]))->run();
}

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

    /** @var DOMNode Root node. */
    private $rootNode;

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
            throw new Exception('Default output directory doesn\'t seem to'
            . ' be set, but at least one class does not have a hardcoded output'
            . ' directory.');
        }

        return $this->defaultOutputDir;
    }

    /**
     * Setter for rootNode.
     *
     * @param   DOMNode $input
     * @return  void
     */
    public function setRootNode(DOMNode $input)
    {
        $this->rootNode = $input;
    }

    /**
     * Getter for rootNode.
     *
     * @return  DOMNode
     */
    public function getRootNode(): DOMNode
    {
        return $this->rootNode;
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
        $this->loadXmlFile($inputpath);

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

        $this->setRootNode($root);
    }

    /**
     * Build all files.
     *
     * @return  void
     */
    public function run()
    {
        // Build each individual class.
        foreach ($this->getClassElements() as $node) {
            $this->buildClassFile($node);
        }

        print_r("Done.\n");
    }

    /**
     * Delete all files that would otherwise create.
     *
     * @return  void
     */
    public function delete()
    {
        // Delete all class files, if they exist.
        foreach ($this->getClassElements() as $node) {
            $this->deleteClassFile($node);
        }

        print_r("Done deleting files.\n");
    }


    // Helper functions below this line.

    /**
     * Load XML file.
     *
     * @param   string  $inputpath
     * @return  void
     */
    private function loadXmlFile(string $inputpath)
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
     * Get all "class-like" elements from root directory (classes, traits,
     * interfaces, etc).
     *
     * @return  array
     */
    private function getClassElements(): array
    {
        return getImmediateChildrenByName(
            $this->getRootNode(),
            ['class', 'trait', 'interface']
        );
    }

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
     * Delete class file if it exists.  Do nothing otherwise.
     *
     * @param   DOMNode     $classNode
     * @return  void
     */
    private function deleteClassFile(DOMNode $classNode)
    {
        $path = buildFilePath(
            $this->buildDirectory($classNode),
            $classNode->getAttribute('name')
        );

        if (file_exists($path)) {
            print_r("Deleting $path.\n");
            unlink($path);
        }
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

    /** @var UsedNamespaces Object handling namespaces in docblocks. */
    private $usedNamespaces;


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

    /**
     * Setter for usedNamespaces.
     *
     * @param   UsedNamespaces $input
     * @return  void
     */
    public function setUsedNamespaces(UsedNamespaces $input)
    {
        $this->usedNamespaces = $input;
    }

    /**
     * Getter for usedNamespaces.
     *
     * @return  UsedNamespaces
     */
    public function getUsedNamespaces(): UsedNamespaces
    {
        return $this->usedNamespaces;
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
        $this->setUsedNamespacesFromClassNode();
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

        // Whatever the last thing was written, there's an extra line at the end.
        $this->getFileWriter()->deleteLastLine();

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
     * Pull the interfaces (implements).
     *
     * If there are no interfaces, returns an empty string.
     *
     * @return  string
     */
    private function pullImplement(): string
    {
        $interfaces = $this->getClassNode()->getAttribute('implements');

        if (strlen($interfaces) == 0) {
            return '';
        }

        return ' implements ' . str_replace(' ', ', ', $interfaces);
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
            $this->filePath = buildFilePath($this->getDirectoryPath(), $this->pullClassName());
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

        foreach (getImmediateChildrenByName($usesElement, 'use') as $el) {
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
        $docBuilder->setForceVertical(true);

        // Description.
        $docBuilder->setDescription(
            childOverAttribute($this->getClassNode(), 'doc')
        );

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
        //$openline.= 'class ';
        $openline.= $this->getClassNode()->nodeName . ' ';
        $openline.= $this->pullClassName();
        $openline.= $this->pullExtension();
        $openline.= $this->pullImplement();

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
     * Note: This method does some kinda funky stuff.  I'd like to fix it, but
     * I'm not sure what the best way to go about doing it would be atm.  I may
     * want to refactor it in the future.
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


        // Now handle the properties.  (This contains the funky stuff.)
        $writer = $this->getFileWriter();
        $usedNamespaces = $this->getUsedNamespaces();
        $propertiesArr = new ArrayObject();
        // Using ArrayObject as a hacky way to pass by reference through "use".
        // This... Could probably use a refactor.

        $dummyFunc = function(DOMNode $el, string $vis) use ($writer, $usedNamespaces, $propertiesArr) {
            $propDum = new PropertyBuilder($writer, $el, 1);
            $propDum->setUsedNamespaces($usedNamespaces);
            if ($vis) {
                $propDum->setVisibility($vis);
            }
            $propDum->write();

            $propertiesArr->append($propDum);
        };

        $this->writePropertiesOrMethodsLoop($properties, 'property', $dummyFunc);
        // Note: $properties contains constants, but
        // writePropertiesOrMethodsLoop will ignore those elements, because the
        // tag name is not "property", "private", "public", or "protected".


        // Add the getters and setters.
        if ($this->needToWriteSettersOrGetters($propertiesArr)) {
            $this->writeSettersAndGetters($propertiesArr);
        }

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

        $writer = $this->getFileWriter();
        $usedNamespaces = $this->getUsedNamespaces();
        $isInInterface = $this->getClassNode()->nodeName == 'interface';

        $dummyFunc = function(DOMNode $el, string $vis) use ($writer, $usedNamespaces, $isInInterface) {
            $methodBuilder = new MethodBuilder($writer, $el, 1);
            $methodBuilder->setUsedNamespaces($usedNamespaces);
            if ($vis) {
                $methodBuilder->setVisibility($vis);
            }
            $methodBuilder->setIsInInterface($isInInterface);
            $methodBuilder->write();
        };

        $this->writePropertiesOrMethodsLoop($allMethods, 'method', $dummyFunc);
    }

    /**
     * Loop through child elements and run callback function on each element, if
     * it's proper type.
     *
     * (Note: I generally don't like using callable in PHP because I think it
     * makes the code more convoluted, but it really works out well here.)
     *
     * TODO: Better docblock here.  I feel like it isn't very clear what this
     * does.
     *
     * @param   DOMNode         $el
     *  Parent element.
     * @param   string          $elementName
     *  "method" or "property".
     * @param   callable        $handleFunction
     *  Function to actually handle what to do with the elements when they're
     *  found.  Needs to take in DOMNode as first argument and nullable string
     *  for second argument, as the visibility will be passed to it.
     * @param   string          $vis
     *  Visibility-- Not set if empty string.  Optional, defaulting to empty.
     */
    private function writePropertiesOrMethodsLoop(
        DOMNode    $el,
        string     $elementName,
        callable   $handleFunction,
        string     $vis = ''
    ) {
        foreach ($el->childNodes as $childNode) {
            switch ($childNode->nodeName) {
                case $elementName:
                    $handleFunction($childNode, $vis);
                    break;
                case 'private':
                case 'public':
                case 'protected':
                    $this->writePropertiesOrMethodsLoop(
                        $childNode,
                        $elementName,
                        $handleFunction,
                        $childNode->nodeName
                    );
                    break;
                case 'comment':
                    $this->writeCommentNodeToFile($childNode);
                    break;
                default:
                    // Probably something dumb like "TextNode" or something
                    // meaningless from whitespace, so all we can do here is
                    // silently fail.
                    // But this also means that unanticipated nodes will be
                    // ignored.  This is used to our advantage for constants in
                    // the properties element.
                    break;
            }
        }
    }

    /**
     * Write line comment to file from comment element.
     *
     * @param   DOMNode     $el
     * @return  void
     */
    private function writeCommentNodeToFile(DOMNode $el)
    {
        $docblock = new DocBlockBuilder(
            $this->getFileWriter(), 1);
        $docblock->setDescription(getNodeText($el));
        $docblock->useLineCommentDecorations();
        $docblock->write();

        $this->getFileWriter()->appendToFile('');

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

    /**
     * Set the used namespace from the class node.
     *
     * @return  void
     */
    private function setUsedNamespacesFromClassNode()
    {
        $this->setUsedNamespaces(new UsedNamespaces(
            getFirstImmediateChildByName($this->getClassNode(), 'uses')
        ));
    }

    /**
     * Write the setters and getters for properties.
     *
     * @param   ArrayObject     $propertiesArr
     * @return  void
     */
    private function writeSettersAndGetters(ArrayObject $propertiesArr)
    {
        $this->getFileWriter()->appendToFile('');
        $this->getFileWriter()->appendToFile('// START getters and setters.', 1);
        $this->getFileWriter()->appendToFile('');

        foreach ($propertiesArr as $property) {
            $property->writeSettersAndGetters();
        }

        $this->getFileWriter()->appendToFile('');
        $this->getFileWriter()->appendToFile('// END getters and setters.', 1);
        $this->getFileWriter()->appendToFile('');


        // Add extra space after all is done.
        $this->getFileWriter()->appendToFile('');
    }

    /**
     * Determine if there are going to be setters and/or getters to write.
     *
     * @param   ArrayObject     $propertiesArr
     * @return  bool
     */
    private function needToWriteSettersOrGetters(
        ArrayObject $propertiesArr
    ): bool {
        foreach ($propertiesArr as $property) {
            foreach (['setter', 'getter'] as $type) {
                $attval = $property->getElementNode()->getAttribute($type);
                if ((!$attval) || $attval = 1) {
                    return true;
                }
            }
        }

        return false;
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

    /** @var string|null The next line to actually be written to disk. */
    private $bufferline = null;

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
     * Destructor.
     *
     * @return  void
     */
    public function __destruct()
    {
        $this->popAndWrite('//END');
        // If we ever *see* "//END", then we know something's wrong, because it
        // should go into the buffer and just be discarded.
    }

    /**
     * Append a line to the output file.
     *
     * (Note: From an inner-workings perspective, it doesn't actually write the
     * line to disk until the *next* time the method is called, or during the
     * destructor.)
     *
     * @param   string  $content
     * @param   int     $indentlvl      Defaults to 0.
     * @return  void
     */
    public function appendToFile(string $content, int $indentlvl = 0)
    {
        $this->popAndWrite(buildIndent($indentlvl) . $content);
    }

    /**
     * Append string to the last line.
     *
     * @param   string  $content
     * @return  void
     */
    public function appendToLine(string $content)
    {
        $this->bufferline.= $content;
    }

    /**
     * Delete the last line.
     *
     * (Note: From an inner-workings perspective, it doesn't delete anything
     * from disk, but just sets the buffer to null, so it won't be written to
     * disk.  This function is actually why there's a buffer in the first
     * place.)
     *
     * @return  void
     */
    public function deleteLastLine()
    {
        $this->bufferline = null;
    }


    // Helper functions below this line.

    /**
     * Pop a buffer with new content and write the result to file.
     *
     * @param   string  $newcontent
     * @return  void
     */
    private function popAndWrite(string $newcontent)
    {
        $bufferline = $this->popBuffer($newcontent);

        if (is_string($bufferline)) {
            // The alternative is null-- Don't write to file if there's nothing
            // in the buffer.  But *do* write empty lines to the buffer, which
            // is why we're not just checking !$bufferline.
            $this->writeLineToDisk($bufferline);
        }
    }

    /**
     * Replace buffer with new value and return the old value.
     *
     * @param   string          $newvalue
     * @return  string|null
     */
    public function popBuffer(string $newvalue)
    {
        $returnval = $this->bufferline;
        $this->bufferline = $newvalue;
        return $returnval;
    }

    /**
     * Write a line to disk.
     *
     * @param   string  $lineContent
     * @return  void
     */
    public function writeLineToDisk(string $lineContent)
    {
        $lineContent = rtrim($lineContent);

        file_put_contents(
            $this->getFilePath(),
            $lineContent . PHP_EOL,
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

    /** @var bool Force "vertical" mode (i.e., don't allow one-line docblock. */
    private $forceVertical = false;

    /**
     * Decorations (key-value pairs describing what the docblock looks like).
     *
     * @var array
     */
    private $decorations;


    // Class contants

    /** @var array Decorations for typical docblock. */
    const DECORATIONS__DOC_BLOCK = [
        'open'                      => '/**',
        'line_start'                => ' *',
        'close'                     => ' */',
        'single_line_compatible'    => true,
    ];

    /** @var array Decorations for line comment. */
    const DECORATIONS__LINE_COMMENT = [
        'open'                     => null,
        'line_start'               => '//',
        'close'                    => null,
        'single_line_compatible'   => false,
    ];


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

    /**
     * Setter for forceVertical.
     *
     * @param   bool $input
     * @return  void
     */
    public function setForceVertical(bool $input)
    {
        $this->forceVertical = $input;
    }

    /**
     * Getter for forceVertical.
     *
     * @return  bool
     */
    public function getForceVertical(): bool
    {
        return $this->forceVertical;
    }

    /**
     * Setter for decorations.
     *
     * @param   array $input
     * @return  void
     */
    public function setDecorations(array $input)
    {
        $this->decorations = $input;
    }

    /**
     * Getter for decorations.
     *
     * @return  array
     */
    public function getDecorations(): array
    {
        return $this->decorations;
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
        $this->useDocblockDecorations();
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
        if ($this->isSingleLine()) {
            $this->getFileWriter()->appendToFile($this->singleLineDoc());
            return;
        }

        $this->initializeDocblock();

        $this->writeDescriptionToFile();

        if (count($this->getAttributeArrays()) != 0) {
            $this->addBlankLine();

            $this->writeAttributesToFile();

        }

        $this->finalizeDocblock();

    }


    // Decoration setting functions below.

    /**
     * Use docblock decorations.
     *
     * @return  void
     */
    public function useDocblockDecorations()
    {
        $this->setDecorations(static::DECORATIONS__DOC_BLOCK);
    }

    /**
     * Use line comment decorations.
     *
     * @return  void
     */
    public function useLineCommentDecorations()
    {
        $this->setDecorations(static::DECORATIONS__LINE_COMMENT);
    }


    // Helper functions below this line.

    /**
     * Write the first line to file.
     *
     * @return  void
     */
    private function initializeDocblock()
    {
        $open = $this->getDecorations()['open'];

        if (is_null($open)) {
            return;
        }

        $this->getFileWriter()->appendToFile(
            buildIndent($this->getIndentLvl()) . $open
        );
    }

    /**
     * Write the last line to file.
     *
     * @return  void
     */
    private function finalizeDocblock()
    {
        $close = $this->getDecorations()['close'];

        if (is_null($close)) {
            return;
        }

        $this->getFileWriter()->appendToFile(
            buildIndent($this->getIndentLvl()) . $close
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
        $startline = $this->getDecorations()['line_start'];

        if (is_null($startline)) {
            throw new Exception(__METHOD__ . ':: Decorations can never have'
            . ' null line starts.');
        }

        $sp = (strlen($content) == 0) ? '' : ' ';

        return buildIndent($this->getIndentLvl()) . $startline . $sp . $content;
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

        $str = preg_replace('/[\r\n](\s+)?[\r\n]/', "##PARA4672##" . PHP_EOL, $str);
        // Replace paragraph breaks with marker.
        $str = preg_replace('/[\r\n](\s)+/', '' , $str);
        // Remove whitespace after line breaks.
        $paragraphs = explode('##PARA4672##', $str);
        // Break paragraphs into different strings.

        $output = [];
        $maxind = count($paragraphs) - 1;

        foreach ($paragraphs as $ind => $paragraph) {
            while (strlen($paragraph) > 0) {
                $paragraph = $this->buildDocblockLine($indent . $paragraph);

                if(strlen($paragraph) < CommonConstants::LENGTH_LESS_THAN) {
                    $outputDum = $paragraph;
                    $paragraph = '';
                } else {
                    $breakind = findLastBreak($paragraph);
                    $outputDum = substr($paragraph, 0, $breakind);
                    $paragraph = substr($paragraph, $breakind+1);
                }

                $output[] = rtrim($outputDum);
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
        $line1 = $this->buildDocblockline($this->attDefForm($defname));
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

    /**
     * Build the full docblock as a single line, assuming that there's one or
     * fewer attributes and only one element in that attributes `data`.
     *
     * @return  string
     */
    private function singleLineDoc(): string
    {
        $returnStr = buildIndent($this->getIndentlvl());

        $dec = $this->getDecorations();

        if (!is_null($dec['open'])) {
            $returnStr.= $dec['open'];
        }

        $atts = $this->getAttributeArrays();
        if (count($atts) > 0) {
            $returnStr.= ' ' . $this->attDefForm($atts[0]['type']);
            $returnStr.= ' ' . $atts[0]['data'][0];
        }

        $returnStr.= ' ' . $this->getDescription();
        if (!is_null($dec['close'])) {
            $returnStr.= $dec['close'];
        }

        return $returnStr;
    }

    /**
     * Determine if docblock would fit on a single line.
     *
     * @return  bool
     */
    private function isSingleLine(): bool
    {
        if (!$this->getDecorations()['single_line_compatible']) {
            return false;
        }

        if ($this->getForceVertical()) {
            return false;
        }

        $attArr = $this->getAttributeArrays();

        if (count($attArr) > 1) {
            return false;
        }

        if ((count($attArr) == 1) && (count($attArr[0]['data']) > 1)) {
            return false;
        }

        if (strlen($this->singleLineDoc()) >= CommonConstants::LENGTH_LESS_THAN) {
            return false;
        }

        return true;
    }

    /**
     * Return string with '@' appended to front-- Format for attribute
     * "definition".
     *
     * @param   string  $input
     * @return  string
     */
    private function attDefForm(string $input): string
    {
        return '@' . $input;
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

    /**
     * UsedNamespaces object.  Not used by all child classes.
     *
     * @var UsedNamespaces
     */
    private $usedNamespaces;


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

    /**
     * Setter for usedNamespaces.
     *
     * @param   UsedNamespaces $input
     * @return  void
     */
    public function setUsedNamespaces(UsedNamespaces $input)
    {
        $this->usedNamespaces = $input;
    }

    /**
     * Getter for usedNamespaces.
     *
     * @return  UsedNamespaces
     */
    public function getUsedNamespaces(): UsedNamespaces
    {
       return $this->usedNamespaces;
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


    // Helper functions below this line, to be used in child classes.

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
     * Build a string of attribute $valueAtt, and if $typeAtt is equal to
     * "string", then wrap it in double quotes.
     *
     * This is a useful function for some child classes.
     *
     * @param   string  $valueAtt
     * @param   string  $typeAtt
     * @return  string
     */
    protected function buildValueString(string $valueAtt, string $typeAtt): string
    {
        $returnVal = $this->getAttribute($valueAtt);

        if ($this->getAttribute($typeAtt) == 'string') {
            return "\"$returnVal\"";
        }

        return $returnVal;
    }

    /**
     * Build a type for use in a docblock using the usedNamespaces object, or
     * return 'variant' if empty string is passed.
     *
     * @param   string  $type
     * @return  string
     */
    protected function buildFullyQualifiedClassOrType(string $type): string
    {
        if (!$this->usedNamespaces) {
            throw new Exception(__METHOD__ . ':: usedNamespaces is not set.');
        }

        if (!$type) {
            // Not defined, so could be anything.
            return 'variant';
        }

        return $this->getUsedNamespaces()->fullyQualifiedName($type);
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
    use VisibilityTrait;

    /**
     * {@inheritDoc}
     *
     */
    public function write()
    {
        $docblock = new DocBlockBuilder($this->getFileWriter(), $this->getIndentlvl());
        $docblock->setDescription(childOverAttribute($this->getElementNode(), 'doc'));
        $docblock->addAttribute('var', [
            $this->getUsedNamespaces()->fullyQualifiedName(
                $this->getAttribute('type')
            )
        ]);
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
        $vis = $this->getVisibility();
        $declaration = "$vis {$stat}\$" . $this->getAttribute('name');

        if (strlen($this->getAttribute('default')) > 0) {
            $declaration.= ' = ' . $this->buildValueString('default', 'type');
        }

        $declaration.= ';';

        $this->getFileWriter()->appendToFile($declaration, $this->getIndentlvl());
    }

    /**
     * Write the getter function iff it's not disabled by xml.
     *
     * @return  void
     */
    private function writeGetter()
    {
        // Do nothing if disabled.
        if ($this->getAttribute('getter') === '0') {
            // Do nothing; user disabled getter for this property.
            // Todo: Warning if custom getter is defined, but is disabled from
            // here.
            return;
        }


        // Use custom, if provided.
        $customGetter = getFirstImmediateChildByName(
            $this->getElementNode(),
            'getter'
        );

        if (!is_null($customGetter)) {
            $this->writeMethodElToFile($customGetter);
            return;
        }


        // Generate plain getter otherwise.
        $name = $this->getAttribute('name');

        // Want to dynamically create method element from scratch.
        $domDum = new DOMDocument('1.0', 'utf-8');

        $el = $domDum->createElement('method');
        $el->setAttribute('return', $this->getAttribute('type'));
        $el->setAttribute('name', 'get' . ucfirst($name));
        $el->setAttribute('keywords', ($this->isStatic() ? 'static' : ''));
        $el->setAttribute('doc', "Getter for $name.");

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

        // Do nothing if disabled.
        if ($this->getAttribute('setter') === '0'){
            // Do nothing; user disabled setter for this property.
            return;
        }


        // Use custom, if provided.
        $customSetter = getFirstImmediateChildByName(
            $this->getElementNode(),
            'setter'
        );

        if (!is_null($customSetter)) {
            $this->writeMethodElToFile($customSetter);
            return;
        }


        // Generate plain setter otherwise.
        $name = $this->getAttribute('name');

        // Want to dynamically create method element from scratch.

        $domDum = new DOMDocument('1.0', 'utf-8');

        $el = $domDum->createElement('method');
        $el->setAttribute('return', 'void');
        $el->setAttribute('name', 'set' . ucfirst($name));
        $el->setAttribute('keywords', ($this->isStatic() ? 'static' : ''));
        $el->setAttribute('doc', "Setter for $name.");

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
        $methodDum = new MethodBuilder(
            $this->getFileWriter(),
            $el,
            $this->getIndentlvl()
        );
        $methodDum->setUsedNamespaces($this->getUsedNamespaces());
        $methodDum->setVisibility('public'); // Getters and setters always public.

        $methodDum->write();
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
        $docblock->setDescription(childOverAttribute($this->getElementNode(), 'doc'));
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
            . $this->buildValueString('value', 'type')
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
    use VisibilityTrait;

    /** @var bool True if using a vertical signature. */
    private $verticalSig = false;

    /** @var bool True if this is a method in an interface. */
    private $isInInterface = false;


    // START getters and setters.

    /**
     * Setter for isInInterface.
     *
     * @param   bool $input
     * @return  void
     */
    public function setIsInInterface(bool $input)
    {
        $this->isInInterface = $input;
    }

    /**
     * Getter for isInInterface.
     *
     * @return  bool
     */
    public function getIsInInterface(): bool
    {
        return $this->isInInterface;
    }


    // END getters and setters.


    /**
     * {@inheritDoc}
     *
     */
    public function write()
    {
        $this->writeDocblock();

        $this->writeFunction();

        $this->getFileWriter()->appendToFile('');

    }


    // Helper functions below this line.

    /**
     * Write the docblock for the method.
     *
     * @return  void
     */
    private function writeDocblock()
    {
        // Build docblock object and description.
        $docblock = new DocBlockBuilder(
            $this->getFileWriter(), $this->getIndentlvl());
        $docblock->setDescription(
            childOverAttribute($this->getElementNode(), 'doc'));
        $docblock->setForceVertical(true);

        // Input lines.
        foreach ($this->getImmediateChildrenByName('input') as $input) {
            $type = $this->buildFullyQualifiedClassOrType(
                $input->getAttribute('type'));

            $dets = [
                $type,
                '$' . $input->getAttribute('name'),
                childOverAttribute($input, 'desc')
            ];

            $docblock->addAttribute('param', $dets);
        }

        // Return line.
        $returnVal = $this->buildFullyQualifiedClassOrType(
            $this->getAttribute('return'));

        $docblock->addAttribute('return', [$returnVal]);

        // Throws lines.
        foreach ($this->getImmediateChildrenByName('throws') as $input) {
            $exceptionClass = $this->buildFullyQualifiedClassOrType(
                $input->getAttribute('exception'));

            $docblock->addAttribute('throws', [
                $exceptionClass,
                null,
                childOverAttribute($input, 'desc'),
            ]);
        }

        // Write.
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

        if ($this->isAbstract() || $this->getIsInInterface()) {
            $this->getFileWriter()->appendToLine(';');
            return;
        }

        if ($this->verticalSig) {
            $this->getFileWriter()->appendToLine(' {');
        } else {
            $this->getFileWriter()->appendToFile('{', $this->getIndentlvl());
        }

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
        $args = implode(', ', $this->buildArgumentsArray());

        $declaration = $this->buildSignature($args);

        $decLength = strlen($declaration)
            + $this->getIndentLvl() * CommonConstants::INDENT_WIDTH;

        if ($decLength >= CommonConstants::LENGTH_LESS_THAN) {
            // Too long, then retry.
            $declaration = $this->buildVerticalSignature();
            $this->verticalSig = true;
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
            $passby = ($this->getPassby($input) == 'ref') ? '&' : '';

            $returnEl = $passby . '$' . $input->getAttribute('name');

            $typedum = $input->getAttribute('type');
            if ($this->enforceReturnType($typedum)) {
                $returnEl = "$typedum $returnEl";
            }

            $returnArr[] = $returnEl;
        }

        return $returnArr;
    }

    /**
     * Determine if want to enforce type.  For both parameters and outputs.
     *
     * @param   string  $type
     * @return  bool
     */
    private function enforceReturnType(string $type): bool
    {
        if ($type == 'void') {
            return false;
        }

        if (strlen($type) == 0) {
            return false;
        }

        if (strpos($type, '|') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Put together the various stuff to make the signature.
     *
     * @param   string  $vis
     *  Visibility.
     * @param   string  $stat
     *  Static, or empty string.
     * @param   string  $name
     *  Method name.
     * @param   string  $args
     *  Arguments.
     * @param   string  $returnType
     *  Return type.
     * @return  string
     */
    private function buildSignature(string $args): string
    {
        $abs  = $this->isAbstract() ? 'abstract ' : '';
        $vis  = $this->getIsInInterface() ? 'public' : $this->getVisibility();
        $stat = $this->isStatic() ? ' static' : '';
        $name = $this->getAttribute('name');

        $returnType = $this->getAttribute('return');

        $returnType = $this->enforceReturnType($returnType)
            ? ": $returnType"
            : ''
        ;

        $declaration = "{$abs}{$vis}{$stat} function $name($args)$returnType";

        return $declaration;
    }

    /**
     * Build vertical signature, i.e., put the arguments on separate lines.
     *
     * @param   string  $vis
     *  Visibility.
     * @param   string  $stat
     *  Static, or empty string.
     * @param   string  $name
     *  Method name.
     * @param   string  $returnType
     *  Return type.
     * @return  string
     */
    private function buildVerticalSignature(): string
    {
        $argsArr = [];

        foreach ($this->buildArgumentsArray() as $arg) {
            $argsArr[] = PHP_EOL . buildIndent($this->getIndentlvl() + 1) . $arg;
        }

        $args = implode(',', $argsArr);
        $args.= PHP_EOL . buildIndent($this->getIndentlvl());

        return $this->buildSignature($args);
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

    /**
     * Get an input's "passby" value ('val' or 'ref').
     *
     * @param   DOMNode     $inputEl
     * @return  string
     */
    private function getPassby(DOMNode $inputEl): string
    {
        return ($inputEl->getAttribute('passby') ?? 'val');
    }

}

/**
 * Handle visibility.
 *
 * @author  Andrew Norman
 */
trait VisibilityTrait
{
    /** @var string Visibility, as plain string. */
    private $visibility = 'private';

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
        $contentArr = explode(PHP_EOL, $this->getCDataNode()->textContent);

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

/**
 * Manage namespaces and convert a class name to its fully-qualified name.
 *
 * Intended to be used only in docblocks.
 *
 * @author  Andrew Norman
 */
class UsedNamespaces
{
    /** @var array Map of class name to fully-qualified namespace. */
    private $map;


    // START getters and setters.

    /**
     * Setter for map.
     *
     * @param   array $input
     * @return  void
     */
    public function setMap(array $input)
    {
        $this->map = $input;
    }

    /**
     * Getter for map.
     *
     * @return  array
     */
    public function getMap(): array
    {
       return $this->map;
    }


    // END getters and setters.

    /**
     * Constructor, parsing the `use` element to build the map.
     *
     * @param   DOMNode|null    $useEl
     * @return  void
     */
    public function __construct($useEl)
    {
        $this->map = [];

        if (is_null($useEl)) {
            // Nothing to do.
            return;
        }

        foreach (getImmediateChildrenByName($useEl, 'use') as $child) {
            $value = $child->getAttribute('value');
            $strpos = strrpos($value, '\\');
            $this->map[substr($value, $strpos+1)] = $value;
        }
    }

    /**
     * Get the fully-qualified namespace of a type if it exists.  Return
     * input if it does not.
     *
     * (Note that this means can enter types that have no namespaces, like
     * 'string', 'int', etc, and it will return normally.)
     *
     * @param   string  $type
     * @return  string
     */
    public function fullyQualifiedName(string $type): string
    {
        $parts = explode('|', $type);
        $returnArr = [];

        foreach ($parts as $typepart) {
            $returnArr[] = $this->fullyQualifiedNamePart($typepart);
        }

        return implode('|', $returnArr);
    }


    // Helper functions below this line.

    /**
     * Get the fully-qualified namespace of a type if it exists, for one
     * particular part.
     *
     * @param   string  $typepart
     * @return  string
     */
    private function fullyQualifiedNamePart(string $typepart): string
    {
        $nullable = $this->strNullable($typepart);
        $typepart = $this->extractNullable($typepart);

        $map = $this->getMap();

        if (array_key_exists($typepart, $map)) {
            $returnVal = '\\' . $map[$typepart];
        } elseif (!$this->isPrimitiveType($typepart)) {
            $returnVal = '\\' . $typepart;
        } else {
            $returnVal = $typepart;
        }

        return $returnVal . $nullable;
    }

    /**
     * Determine if a type (name as string) is a primitive type (including
     * scalar types, void, arrays, and resources).
     *
     * @param   string  $typename
     * @return  bool
     */
    private function isPrimitiveType(string $typename): bool
    {
        return in_array($typename, [
            'int',
            'integer',
            'float',
            'double',
            'string',
            'bool',
            'boolean',
            'iterable',
            'callable',
            'void',
            'array',
            'resource',
        ]);
    }

    /**
     * Remove leading '?', if it exists.
     *
     * @param   string  $type
     * @return  string
     */
    private function extractNullable(string $type): string
    {
        return ltrim($type, '?');
    }

    /**
     * Return '|null' if variable type starts with '?', else return empty
     * string.
     *
     * @param   string  $type
     * @return  string
     */
    private function strNullable(string $type): string
    {
        return (substr($type, 0, 1) == '?') ? '|null' : '';
    }

}

/**
 * Common constants used among multiple classes.
 *
 * @author  Andrew Norman
 */
class CommonConstants
{
    /** @var int The upper bound, exclusive, of the line length. */
    const LENGTH_LESS_THAN = 81;

    /** @var int Width of indents. */
    const INDENT_WIDTH = 4;
}


// Shared functions below.

/**
 * Build a file path from a directory and filename, excluding the php
 * extension.
 *
 * @param   string      $directory
 * @param   string      $filename
 * @return  string
 */
function buildFilePath(string $directory, string $filename): string
{
    return "{$directory}/{$filename}.php";
}

/**
 * Build an indent level.
 *
 * @param   int     $indentlvl
 * @return  string
 */
function buildIndent(int $indentlvl): string
{
    return str_repeat(' ', CommonConstants::INDENT_WIDTH * $indentlvl);
}

/**
 * Find all *immediate* children of an element of a particular name.
 *
 * @param   DOMNode         $node
 * @param   string|array    $name
 * @return  array
 */
function getImmediateChildrenByName(DOMNode $node, $name): array
{
    // This is ten thousand times easier in Dart.
    $returnArr = [];

    if (!is_array($name) && !is_string($name)) {
        throw new Exception("Received invalid value for \$name.");
    }

    if (is_string($name)) {
        $name = [$name];
    }

    foreach ($node->childNodes as $childNode) {
        if (in_array($childNode->nodeName, $name)) {
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

/**
 * Look for a child element of a particular name and return its text value.  If
 * it doesn't exist, return the value of the attribute of the name.
 *
 * @param   DOMNode     $el
 * @param   string      $name
 * @return  string
 */
function childOverAttribute(DOMNode $el, string $name): string
{
    $child = getFirstImmediateChildByName($el, $name);

    if (is_null($child)) {
        return $el->getAttribute($name);
    }

    return getNodeText($child);
}


// Main script below.

/**
 * Class for handling CLI inputs.
 *
 * @author  Andrew Norman
 */
class CliInputs
{
    /** @var array $arguments. */
    private $args;

    /**
     * Constructor.
     *
     * @param   array   $argv
     * @return  void
     */
    public function __construct(array $argv)
    {
        $this->args = $argv;
    }

    /**
     * Determine if a flag is set.
     *
     * @param   string  $flagName
     * @return  bool
     */
    public function isFlagSet(string $flagName): bool
    {
        return in_array("--$flagName", $this->args);
    }

    /**
     * Get a flag value.
     *
     * @param   string  $flagName
     * @return  string
     */
    public function getFlagVal(string $flagName): string
    {
        $ind = array_search("--$flagName", $this->args) + 1;

        $missingErr = 'Missing value for ' . $flagName;

        if (!array_key_exists($ind, $this->args)) {
            throw new Exception($missingErr);
        }

        $returnVal = $this->args[$ind];

        if (!$this->isVal($returnVal)) {
            throw new Exception($missingErr);
        }

        return $returnVal;
    }

    /**
     * Determine if the input file is set.
     *
     * @return  bool
     */
    public function isInputFileSet(): bool
    {
        return array_key_exists(1, $this->args) && $this->isVal($this->getByIndex(1));
    }

    /**
     * Return the input file as passed.
     *
     * @return  string
     */
    public function getInputFile(): string
    {
        if (!$this->isInputFileSet()) {
            throw new Exception("First argument must be path to input xml.");
        }

        return $this->getByIndex(1);
    }

    /**
     * Determine if should attempt to run normally.
     *
     * @return  bool
     */
    public function shouldRunNormally(): bool
    {
        return count($this->args) == 2;
    }


    // Helper functions below this line.

    /**
     * Determine if a passed string is a a value (the alternative being a flag).
     *
     * @param   string      $inputStr
     * @return  bool
     */
    private function isVal(string $inputStr): bool
    {
        return substr($inputStr, 0, 2) != '--';
    }

    /**
     * Get a value or flag by its index.
     *
     * @param   int     $ind
     * @return  string
     */
    private function getByIndex(int $ind): string
    {
        return $this->args[$ind];
    }

}

$cliInputs = new CliInputs($argv);

if (!$cliInputs->isFlagSet('donotrun')) {
    if (!$cliInputs->isInputFileSet()) {
        die("Missing input xml file.\n");
    }

    $main = new Main($cliInputs->getInputFile());

    if ($cliInputs->shouldRunNormally()) {
        $main->run();
    } elseif ($cliInputs->isFlagSet('delete')) {
        $main->delete();
    } elseif ($cliInputs->isFlagSet('force')) {
        $main->delete();
        $main->run();
    } else {
        print("Missing input xml file.\n");
    }
}

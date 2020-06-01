<?php
/**
 * sFire Framework (https://sfire.io)
 *
 * @link      https://github.com/sfire-framework/ for the canonical source repository
 * @copyright Copyright (c) 2014-2020 sFire Framework.
 * @license   http://sfire.io/license BSD 3-CLAUSE LICENSE
 */

declare(strict_types=1);

namespace sFire\Template;

use sFire\Dom\Elements\DomElementAbstract;
use sFire\Dom\Elements\Node;
use sFire\Dom\Elements\Text;
use sFire\Dom\Parser as DomParser;
use sFire\FileControl\File;
use sFire\FileControl\Directory;
use sFire\Template\Exceptions\RuntimeException;


/**
 * Class Parser
 * @package sFire\Template
 */
class Parser {


    /**
     * Contains the type of the content that needs to be parsed (html, xml)
     * @var null|int
     */
    private ?int $contentType = null;


    /**
     * Contains the path to the HTML file that needs to be parsed
     * @var null|string
     */
    private ?string $file = null;


    /**
     * Contains if the template should be loaded from cache
     * @var bool
     */
    private bool $cacheEnabled = true;


    /**
     * Contains the amount of maximum cached results that may be stored while calling a template method or function with arguments
     * @var int
     */
    private int $amountCacheResults = 5;


    /**
     * Contains all the assigned functions
     * @var array
     */
    private array $functions = [];


    /**
     * Contains all the assigned variables
     * @var array
     */
    private array $variables = [];


    /**
     * Contains the path to the cache directory
     * @var null|Directory
     */
    private ?Directory $cacheDirectory = null;


    /**
     * Contains the directory root of the template files
     * @var null|Directory
     */
    private ?Directory $templateDirectory = null;


    /**
     * Contains the results of the executed custom functions
     * @var array
     */
    private array $computedResults = [];


    /**
     * Contains a node that needs to be skipped from parsing
     * @var null|Node
     */
    private ?Node $skip = null;


    /**
     * Contains an instance of Translate
     * @var null|Translate
     */
    private ?Translate $translate = null;


    /**
     * Constructor
     * @param null|Translate $translate
     */
    public function __construct(Translate $translate = null) {
        $this -> translate = $translate ?? new Translate();
    }


    /**
     * Magic method for rendering the template
     * @return false|string
     */
    public function __toString() {
        return $this -> render();
    }


    /**
     * Returns the rendered template
     * @return false|string
     */
    public function render() {

        $file =  $this -> convert();
        return $this -> obRender($file -> getPath());
    }


    /**
     * Magic method to catch all called functions from within the template
     * Saves the result if cache is enabled for given function
     * @param string $name The name of the function
     * @param array $arguments An array with parameters
     * @return mixed
     * @throws RuntimeException
     */
    public function __call(string $name, array $arguments) {

        $hash = md5(serialize($arguments));

        if(false === isset($this -> functions[$name])) {
            throw new RuntimeException(sprintf('Error in render: "%s" is not a function', $name));
        }

        if(false === isset($this -> computedResults[$name][$hash])) {
            $results = $this -> functions[$name]['callable'](...$arguments);
        }
        else {
            $results = $this -> computedResults[$name][$hash];
        }

        if($this -> functions[$name]['cache'] > 0) {

            $this -> computedResults[$name][$hash] ??= [];

            if(count($this -> computedResults[$name]) > $this -> functions[$name]['cache']) {
                array_shift($this -> computedResults[$name]);
            }

            $this -> computedResults[$name][$hash] = $results;
        }

        return $results;
    }


    /**
     * Sets the path of the file to be parsed
     * @param string $filePath
     * @return void
     */
    public function setFile(string $filePath): void {
        $this -> file = $filePath;
    }


    /**
     * Sets the amount of maximum cached results that may be stored while calling a template method or function with arguments
     * @param int $amountCacheResults
     * @return void
     */
    public function setAmountCacheResults(int $amountCacheResults): void {
        $this -> amountCacheResults = $amountCacheResults;
    }


    /**
     * Assigns variables. Will merge recursive if needed.
     * @param float|int|string|array $key The title of the variable. If given an array, it wil be recursively merged into the existing variables.
     * @param mixed $value [optional] The value of the variable
     * @return self
     */
    public function assign($key, &$value = null): self {

        if(true === is_array($key)) {

            $this -> variables = array_replace_recursive($this -> variables, $key);
            return $this;
        }

        $this -> variables[$key] = &$value;
        return $this;
    }


    /**
     * Sets the cache directory for the parsed HTML files to be saved in
     * @param string $path The path of the directory where all cache will stored
     * @return void
     * @throws RuntimeException
     */
    public function setCacheDir(string $path): void {

        $directory = new Directory($path);

        if(false === $directory -> isWritable()) {
            throw new RuntimeException(sprintf('Cache directory "%s" does not exists or is not writable', $path));
        }

        $this -> cacheDirectory = $directory;
    }


    /**
     * Sets the root of the template directory where all the template files are saved
     * @param string $path The path of the directory where all templates are saved
     * @return void
     * @throws RuntimeException
     */
    public function setTemplateDir(string $path): void {

        $directory = new Directory($path);

        if(false === $directory -> isReadable()) {
            throw new RuntimeException(sprintf('Template directory "%s" does not exists or is not readable', $path));
        }

        $this -> templateDirectory = $directory;
    }


    /**
     * Adds a new template function by giving a callable function
     * @param string $functionName The name of the template function
     * @param callable $function A callable function (closure)
     * @param int $cache The amount of maximum cached results that may be stored while calling this function with arguments
     * @return void
     * @throws RuntimeException
     */
    public function addFunction(string $functionName, callable $function, int $cache = 1000): void {

        if(true === isset($this -> functions[$functionName])) {
            throw new RuntimeException(sprintf('Cannot register a new function. A %s with the name "%s" already exists.', $this -> functions[$functionName]['type'], $functionName));
        }

        $this -> functions[$functionName] = ['type' => 'function', 'callable' => $function, 'cache' => $cache];
    }


    /**
     * Adds a new template function by giving a method name from an existing object
     * @param string $functionName The name of the template function
     * @param object $class The object that contains the method
     * @param string $methodName The name of the method of the object
     * @param int $cache The amount of maximum cached results that may be stored while calling this method with arguments
     * @throws RuntimeException
     */
    public function addMethod(string $functionName, object $class, string $methodName, int $cache = 1000): void {

        if(true === isset($this -> functions[$functionName])) {
            throw new RuntimeException(sprintf('Cannot register a new method. A %s with the name "%s" already exists.', $this -> functions[$functionName]['type'], $functionName));
        }

        $this -> functions[$functionName] = ['type' => 'method', 'callable' => [$class, $methodName], 'cache' => $cache];
    }


    /**
     * Sets if the template should be loaded from cache or not
     * @param bool $enabledCache
     * @return void
     */
    public function enableCache(bool $enabledCache): void {
        $this -> cacheEnabled = $enabledCache;
    }


    /**
     * Returns if the template should be loaded from cache or not
     * @return bool
     */
    public function isCachedEnabled(): bool {
        return $this -> cacheEnabled;
    }


    /**
     * @param string $filePath The path to the file
     * @param string $language The language for the translation data
     */
    public function translation(string $filePath, string $language) {
        $this -> translate -> loadTranslationFile($filePath, $language);
    }


    /**
     * Renders the HTML
     * @return File
     * @throws RuntimeException
     */
    public function convert(): File {

        //Check if the cache directory has been set
        if(null === $this -> cacheDirectory) {
            throw new RuntimeException('Cache directory has not been set. Set the cache directory with the setCacheDir() method');
        }

        $path         = null === $this -> templateDirectory ?: $this -> templateDirectory -> getPath();
        $count        = count(explode('.', $this -> file));
        $file         = preg_replace('#\.#', DIRECTORY_SEPARATOR, $this -> file, $count - 2);
        $templateFile = new File($path . $file);

        //Determine content type
        switch(strtolower($templateFile -> getExtension())) {

            case 'xml' : $this -> contentType = DomParser::CONTENT_TYPE_XML; break;
            default    : $this -> contentType = DomParser::CONTENT_TYPE_HTML;
        }

        if(false === $templateFile -> exists()) {
            throw new RuntimeException(sprintf('Template file "%s" does not exists', $this -> file));
        }

        $cacheFile = new File($this -> cacheDirectory -> getPath() . $this -> generateCacheFileName($this -> file));

        if(false === $this -> isCachedEnabled() || $templateFile -> getModificationTime() >= $cacheFile -> getModificationTime()) {

            $cacheFile -> flush();
            $cacheFile -> create();
            $cacheFile -> append($this -> parse($templateFile -> getContent() ?? ''));
        }

        return $cacheFile;
    }


    /**
     * Parses the template
     * @param string $content
     * @return string
     */
    private function parse(string $content): string {

        $document = new DomParser($this -> contentType);
        $nodes    = $document -> parse($content);

        return implode('', $this -> output($nodes));
    }


    /**
     * Set a node that needs to be skipped from parsing
     * @param Node $skip
     */
    private function setSkip(Node $skip) {
        $this -> skip = $skip;
    }


    /**
     * Include a partial template file
     * @param string $filePath The path to the template file
     * @param bool $render Set to true if the render should be executed
     * @return string|self
     */
    private function partial(string $filePath, bool $render = false) {

        $parser = new self($this -> translate);
        $parser -> setCacheDir($this -> cacheDirectory -> getPath());
        $parser -> setTemplateDir($this -> templateDirectory -> getPath());
        $parser -> setFile($filePath);
        $parser -> enableCache($parser -> isCachedEnabled());
        $parser -> setAmountCacheResults($this -> amountCacheResults);

        if(null !== $this -> skip) {
            $parser -> setSkip($this -> skip);
        }

        //Return the content to be parsed from the partial file if render is not enabled
        if(false === $render) {
            return $parser -> convert() -> getContent();
        }

        //Assign all the functions and methods
        foreach($this -> functions as $name => $function) {

            if('function' === $function['type']) {

                $parser -> addFunction($name, $function['callable'], $function['cache']);
                continue;
            }

            $parser -> addMethod($name, $function['callable'][0], $function['callable'][1], $function['cache']);
        }

        //Assign all the variables
        $parser -> assign($this -> variables);

        return $parser;
    }


    /**
     * Translates a node attribute value
     * @param string $path
     * @param array|null $variables
     * @param int $plural
     * @param null|string $language
     * @return string
     */
    public function translateAttribute(string $path, array $variables = null, int $plural = 0, string $language = null): string {
        return htmlentities($this -> translate -> translateAttribute($path, $variables, $plural, $language), ENT_QUOTES);
    }


    /**
     * Translates a node text value
     * @param string $content
     * @param string $path
     * @param null|array $variables
     * @param int $plural
     * @param null|string $language
     * @return string
     */
    public function translate(string $content, string $path, array $variables = null, int $plural = 0, string $language = null): string {
        return $this -> translate -> translate($content, $path, $variables, $plural, $language);
    }


    /**
     * Append the output to the translation (if needed) or to the general output array
     * @param string $code
     * @param $output
     * @param bool $escape
     * @return void
     */
    private function appendOutput(string $code, &$output, bool $escape = true): void {

        if(null === $this -> translate -> getNode()) {

            $output[] = $code;
            return;
        }

        $code = true === $escape ? $this -> escape($code, "'") : $code;
        $this -> translate -> appendContent($code);
    }


    /**
     * Formats all the nodes to XML/Html and returns an array with the results
     * @param DomElementAbstract[] $nodes
     * @param array $output
     * @return array
     * @throws RuntimeException
     */
    private function output(array $nodes, array &$output = []): array {

        foreach($nodes as $index => $node) {

            //Parses element nodes
            if($node instanceof Node) {

                //Format all attributes
                $attributes = new AttributeCollection($node, null !== $this -> translate -> getNode());
                $tag        = $node -> getTag();
                $attr       = [];
                $open       = [];

                //Parsing should be skipped, so append the output as plaint text
                if(null !== $this -> skip) {

                    $code = $tag -> getContent();
                    $this -> appendOutput($code, $output);
                }

                //Parsing should not be skipped
                if(null === $this -> skip) {

                    foreach($attributes -> getAttributes() as $attribute) {

                        $name  = $attribute -> getKey();
                        $value = $attribute -> getValue();

                        //Format the value that function calls will be converted
                        $value = $value ? (new Functions($value)) -> parse() : $value;

                        //Check if the node needs to be translated
                        if($name === 's-translate') {

                            if(null !== $this -> translate -> getNode()) {
                                throw new RuntimeException(sprintf('Error parsing file "%s". Current node (\'%s\' tag) may not be translated. A parent node (\'%s\' tag) is already been translated and translations may not be nested.', $this -> file, $tag -> getName(), $this -> translate -> getNode() -> getTag() -> getName()));
                            }

                            $this -> translate -> setParameters($attribute -> getValue());
                            $this -> translate -> setNode($node);
                            continue;
                        }

                        //Check for dynamic partials
                        elseif($name === 's-partial-var') {

                            $text = new Text();
                            $text -> setContent($attribute -> getParsed());
                            $node -> addChild($text);

                            continue;
                        }

                        //Check if the node needs to be skipped from parsing and should be rendered as text
                        elseif($name === 's-skip') {

                            $this -> skip = $node;
                            continue;
                        }

                        //Check for static partials
                        elseif($name === 's-partial') {
                            continue;
                        }

                        //Check if there should be a for loop
                        elseif($name === 's-for') {

                            if(true === (bool) preg_match('#^\(*(?<item>\$[a-z0-9]+)(?:,[ ]*(?<index>(:?\$)*[a-z0-9]+)\))*[\s]+in[\s]+(?<items>.*)$#i', (string) $value, $loop)) {

                                //Build a for loop
                                if(true === is_numeric($loop['items'])) {

                                    $output[] = sprintf('<?php for(%1$s = 0; %1$s < %2$s; %1$s++): ?>', $loop['item'], $loop['items']);
                                    $open[] = 'for';
                                }

                                //Build a foreach loop
                                else {

                                    //Foreach loop with key value
                                    if(strlen($loop['index']) > 0) {
                                        $output[] = sprintf('<?php foreach(%1$s as %2$s => %3$s): ?>', $loop['items'], $loop['index'], $loop['item']);
                                    }

                                    //Foreach loop with only value
                                    else {
                                        $output[] = sprintf('<?php foreach(%1$s as %2$s): ?>', $loop['items'], $loop['item']);
                                    }

                                    $open[] = 'foreach';
                                }
                            }

                            continue;
                        }

                        //Check if there is an if statement
                        elseif($name === 's-if') {

                            $output[] = sprintf('<?php if(%s): ?>', $value);
                            $open[] = 'if';
                            continue;
                        }

                        //Check if there is an elseif statement
                        elseif($name === 's-elseif') {

                            $output[] = sprintf('<?php elseif(%s): ?>', $value);
                            $open[] = 'elseif';
                            continue;
                        }

                        //Check if there is an else statement
                        elseif($name === 's-else') {

                            $output[] = '<?php else: ?>';
                            $open[] = 'if';
                            continue;
                        }

                        if($parsed = $attribute -> getParsed()) {
                            $attr[] = $parsed;
                        }
                    }

                    //Render the tag, but skip this process if the special s-tag is summoned
                    if('s-tag' !== $tag -> getName()) {

                        $code = sprintf('<%s%s%s%s>', ($tag -> isLanguageNode() ? '?' : null), $tag -> getName(), implode('', $attr), (true === $tag -> isSelfClosingNode() ? ' /' : ''));

                        if($node === $this -> translate -> getNode()) {
                            $output[] = $code;
                        }
                        else{
                            $this -> appendOutput($code, $output, false);
                        }
                    }

                    //Process partials
                    if($partial = $tag -> getAttribute('s-partial')) {

                        $code = $this -> partial($partial -> getValue());
                        $this -> appendOutput($code ?? '', $output, false);
                    }
                }

                //Parse and append the child nodes to the output if the current node has children
                if(true === $node -> hasChildren()) {
                    $this -> output($node -> getChildren(), $output);
                }

                //All the translation data are gathered so close the translation node and translate it contents
                if($this -> translate -> getNode() === $node) {

                    $output[] = sprintf('<?php echo $this -> translate(\'%s\', %s); ?>', $this -> translate -> getContent(), $this -> translate -> getParameters());
                    $this -> translate -> reset();
                }

                //Close the tag manually if the tag should be closed
                if('s-tag' !== $tag -> getName() && true === $tag -> shouldHaveClosingTag()) {

                    $code = sprintf('</%s>', $tag -> getName());
                    $this -> appendOutput($code, $output, false);
                }

                if($node === $this -> skip) {
                    $this -> skip = null;
                }

                //Close all if, elseif, else, foreach and for loops
                while($close = array_pop($open)) {

                    if('if' === $close || 'elseif' === $close) {

                        while($sibling = $node -> getNextSibling()) {

                            if($sibling instanceof Node) {
                                break;
                            }

                            $node = $sibling;
                        }

                        if(null === $sibling || ($sibling instanceof Node && (false === $sibling -> hasAttribute('s-elseif') && false === $sibling -> hasAttribute('s-else')))) {

                            $code = '<?php endif; ?>' . PHP_EOL;
                            $this -> appendOutput($code, $output, false);
                        }
                    }
                    elseif('foreach' === $close) {

                        $code = '<?php endforeach; ?>' . PHP_EOL;
                        $this -> appendOutput($code, $output, false);
                    }
                    elseif('for' === $close) {

                        $code = '<?php endfor; ?>' . PHP_EOL;
                        $this -> appendOutput($code, $output, false);
                    }
                }

                continue;
            }

            //Parses text nodes
            if($node instanceof Text) {

                if(null !== $this -> skip) {

                    $code = $node -> getContent();
                    $this -> appendOutput($code, $output);
                    continue;
                }

                $content  = $node -> getContent();
                $brackets = $this -> parseBrackets($content);

                foreach(array_reverse($brackets) as $code) {

                    //Format the content that function calls will be converted
                    $code['content'] = (new Functions($code['content'])) -> parse();

                    if(true === $code['escape']) {
                        $content = substr_replace($content, '<?php echo htmlentities((string) ' . $code['content'] . '); ?>', $code['begin'], $code['length']) . PHP_EOL;
                    }
                    else {
                        $content = substr_replace($content, '<?php echo ' . $code['content'] . '; ?>', $code['begin'], $code['length']) . PHP_EOL;
                    }
                }

                $node -> setContent($content);
                $code = $node -> getContent();
                $this -> appendOutput($code, $output);
            }
        }

        return $output;
    }


    /**
     * Escapes a given character in a given text
     * @param string $content The text where the character in it should be escaped
     * @param string $character The character that needs to be escaped
     * @return string
     */
    private function escape(string $content, string $character = '"'): string {

        $amount = strlen($content);
        $output = '';

        for($i = 0; $i < $amount; $i++) {

            if($content[$i] === $character) {

                $escape = true;

                for($a = $i - 1; $a >= 0; $a--) {

                    if($content[$a] !== '\\') {
                        break;
                    }

                    $escape = !$escape;
                }

                if(true === $escape) {
                    $output .= '\\';
                }
            }

            $output .= $content[$i];
        }

        return $output;
    }


    /**
     * Renders the content
     * @param string $file
     * @return false|string
     */
    private function obRender(string $file) {

        ob_start();
        extract($this -> variables, EXTR_OVERWRITE);
        include($file);

        return ob_get_clean();
    }


    /**
     * Returns the content of a string that is between double open and closed brackets "{{ content }}"
     * @param string $content
     * @return array
     */
    private function parseBrackets(string $content): array {

        $length           = strlen($content);
        $escapeCharacters = ['\'' => 0, '"' => 0];
        $chunks           = [];
        $escapeCharacter  = null;
        $position         = null;

        for($i = 0; $i < $length; $i++) {

            if(true === isset($escapeCharacters[$content[$i]])) {

                if($content[$i] === $escapeCharacter) {
                    $escapeCharacter = null;
                }
                elseif(null === $escapeCharacter) {
                    $escapeCharacter = $content[$i];
                }
            }

            if($content[$i] === '{' && null === $escapeCharacter) {

                if('!!' === substr($content, $i + 1, 2)) {

                    if(null === $position) {
                        $position = $i + 3;
                    }
                }
                elseif('{' === substr($content, $i + 1, 1)) {

                    if(null === $position) {
                        $position = $i + 2;
                    }
                }
            }
            elseif($content[$i] === '!' && null === $escapeCharacter) {

                if('!}' === substr($content, $i + 1, 2)) {

                    if(null !== $position) {

                        $data     = substr($content, $position, $i - $position);
                        $chunks[] = ['begin' => $position - 3, 'end' => $i, 'content' => $data, 'length' => strlen($data) + 6, 'escape' => false];
                        $position = null;
                    }
                }
            }
            elseif($content[$i] === '}' && null === $escapeCharacter) {

                if(($content[$i + 1] ?? null) === '}') {

                    if(null !== $position) {

                        $data     = substr($content, $position, $i - $position);
                        $chunks[] = ['begin' => $position - 2, 'end' => $i, 'content' => $data, 'length' => strlen($data) + 4, 'escape' => true];
                        $position = null;
                    }
                }
            }
        }

        return $chunks;
    }


    /**
     * Generates a unique name as file name for the cache file and returns it
     * @param string $filePath The path to the file
     * @return string
     */
    private function generateCacheFileName(string $filePath): string {

        $file      = new File($filePath);
        $name      = substr($file -> getBasePath() . $file -> getName(), -30);
        $name      = preg_replace('#[ \\\/]#', '-', $name);
        $name      = preg_replace('#[^0-9a-zA-Z_\-.]#', '', $name);
        $name      = $name . '-' . md5($file -> getPath());
        $extension = $file -> getExtension();

        if(null !== $extension) {
            $name .= '.' . $extension;
        }

        return $name;
    }
}
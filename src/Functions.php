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


/**
 * Class Functions
 * @package sFire\Template
 */
class Functions {


    /**
     * Contains all the PHP operators and special characters
     */
    private const OPERATORS = [

        'other' => [
            '(', ':', '?', '-->', 'in'
        ],

        'allowed' => [

            //Assignment
            '=', '+=', '-=', '*=', '/=', '%=',

            //Logical
            'and', 'or', 'xor', '&&', '||', '!',

            //Comparison
            '==', '===', '!=', '<>', '!==', '>', '<', '>=', '<=', '<=>',

            //Arithmetic
            '+', '-', '*', '/', '%', '**',

            //String
            '.', '.=',
        ],

        'disallowed' => [
            '->', '\\'
        ]
    ];


    /**
     * Contains all the PHP variable handling function that is not detected with the function_exists function
     * @link https://www.php.net/manual/en/ref.var.php
     * @var array
     */
    private const VARIABLE_HANDLING_FUNCTIONS = [

        'boolval',
        'debug_zval_dump',
        'doubleval',
        'empty',
        'floatval',
        'get_defined_vars',
        'get_resource_type',
        'gettype',
        'intval',
        'is_array',
        'is_bool',
        'is_callable',
        'is_countable',
        'is_double',
        'is_float',
        'is_int',
        'is_integer',
        'is_iterable',
        'is_long',
        'is_null',
        'is_numeric',
        'is_object',
        'is_real',
        'is_resource',
        'is_scalar',
        'is_string',
        'isset',
        'print_r',
        'serialize',
        'settype',
        'strval',
        'unserialize',
        'unset',
        'var_dump',
        'var_export'
    ];


    /**
     * Contains all found functions with arguments, start and end positions
     * @var array
     */
    private array $functions = [];


    /**
     * Contains the content to be parsed
     * @var null|string
     */
    private ?string $content = null;


    /**
     * Constructor
     * @param string $content
     */
    public function __construct(string $content) {

        $this -> content = $content;
    }


    /**
     * Returns all found functions
     * @return array
     */
    public function getFunctions(): array {
        return $this -> functions;
    }


    /**
     * Converts all found functions and returns the parsed content
     * @return null|string
     */
    public function parse(): ?string {

        foreach(array_reverse($this -> findFunctions()) as $function) {
            $this -> content = substr_replace($this -> content, sprintf('$this->%s%s', $function['name'], $function['arguments']), $function['start'], $function['length']);
        }

        return $this -> content;
    }


    /**
     * Parses the content and looks for stand alone functions and returns these
     * @return array
     */
    public function findFunctions(): array {

        $content          = $this -> content;
        $escapeCharacters = ['\'' => 0, '"' => 0];
        $escapeCharacter  = null;
        $length           = strlen($content);

        for($i = 0; $i < $length; $i++) {

            if(true === isset($escapeCharacters[$content[$i]])) {

                if($content[$i] === $escapeCharacter) {
                    $escapeCharacter = null;
                }
                elseif(null === $escapeCharacter) {
                    $escapeCharacter = $content[$i];
                }
            }

            $open = [];

            if($content[$i] === '(' && null === $escapeCharacter) {

                //Find arguments and closing tag
                for($a = $i; $a < $length; $a++) {

                    if(true === isset($escapeCharacters[$content[$a]])) {

                        if($content[$a] === $escapeCharacter) {
                            $escapeCharacter = null;
                        }
                        elseif(null === $escapeCharacter) {
                            $escapeCharacter = $content[$a];
                        }
                    }

                    if($content[$a] === '(' && null === $escapeCharacter) {

                        $open[] = true;
                        continue;
                    }

                    if($content[$a] === ')' && null === $escapeCharacter) {

                        array_pop($open);

                        if(0 === count($open)) {
                            break;
                        }
                    }
                }

                $arguments = substr($content, $i, $a - $i + 1);

                //Find function name
                for($b = $i - 1; $b >= 0; $b--) {

                    if(false === ctype_alpha($content[$b]) && false === in_array($content[$b], ['_']) && false === is_numeric($content[$b])) {
                        break;
                    }
                }

                $name = substr($content, $b + 1, $i - $b - 1);

                //Check if function is a stand alone function
                if($b < 0) {
                    $this -> add($name, $arguments, 0, $a + 1);
                }

                for($c = $b; $c >= 0; $c--) {

                    if(0 === $c) {

                        $this -> add($name, $arguments, $c + 1, $a + 1);
                        break;
                    }

                    if(true === in_array($content[$c], ["\n", "\t", "\r", ' '])) {
                        continue;
                    }

                    //Check for operators
                    foreach(self::OPERATORS['other'] as $operator) {

                        if($operator === strtolower(substr($content, $c - (strlen($operator) - 1), strlen($operator)))) {

                            $this -> add($name, $arguments, $b + 1, $a + 1);
                            break 2;
                        }
                    }

                    foreach(self::OPERATORS['disallowed'] as $operator) {

                        if($operator === strtolower(substr($content, $c - (strlen($operator) - 1), strlen($operator)))) {
                            break 2;
                        }
                    }

                    //Check for operators
                    foreach(self::OPERATORS['allowed'] as $operator) {

                        if($operator === strtolower(substr($content, $c - (strlen($operator) - 1), strlen($operator)))) {

                            $this -> add($name, $arguments, $b + 1, $a + 1);
                            break 2;
                        }
                    }

                    break;
                }
            }
        }

        return $this -> functions;
    }


    /**
     * Add a new found function
     * @param string $name The name of the function
     * @param string $arguments The arguments of the function
     * @param int $startPosition The start position of the function in the content
     * @param int $endPosition The end position of the function in the content
     * @return void
     */
    private function add(string $name, string $arguments, int $startPosition, int $endPosition): void {

        if(true === (bool) preg_match('#^[_a-z][_a-z0-9]*#i', (string) $name)) {

            if(false === function_exists($name) && false === in_array($name, self::VARIABLE_HANDLING_FUNCTIONS)) {
                $this -> functions[] = ['name' => $name, 'arguments' => $arguments, 'start' => $startPosition, 'end' => $endPosition, 'length' => strlen($name) + strlen($arguments)];
            }
        }
    }
}
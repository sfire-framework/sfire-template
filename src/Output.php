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

use sFire\Template\Exceptions\RuntimeException;


/**
 * Class Output
 * @package sFire\Template
 */
class Output {


    /**
     * Contains the results of the executed custom functions
     * @var array
     */
    private array $computedResults = [];


    /**
     * Contains an instance of Translate
     * @var null|Translate
     */
    private ?Translate $translate = null;


    /**
     * Contains an instance of Parser
     * @var null|Parser
     */
    private ?Parser $parser = null;


    /**
     * Constructor
     * @param Parser $parser
     * @param Translate $translate
     */
    public function __construct(Parser $parser, Translate $translate) {

        $this -> translate = $translate;
        $this -> parser = $parser;
    }


    /**
     * Include a partial template file
     * @param string $filePath The path to the template file
     * @param bool $render Set to true if the render should be executed
     * @return string|Parser
     */
    private function partial(string $filePath, bool $render = false) {
        return $this -> parser -> partial($filePath, $render);
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
        $functions = $this -> parser -> getFunctions();

        if(false === isset($functions[$name])) {
            throw new RuntimeException(sprintf('Error in render: "%s" is not a function', $name));
        }

        if($functions[$name]['cache'] > 0) {

            $this -> computedResults[$name][$hash] ??= ['results' => null, 'amount' => 0];
            $amount = $this -> computedResults[$name][$hash]['amount'];

            if(0 === $amount) {

                $this -> computedResults[$name][$hash]['results'] = $functions[$name]['callable'](...$arguments);
                $this -> computedResults[$name][$hash]['amount']++;
            }
            elseif($amount < $functions[$name]['cache']) {
                $this -> computedResults[$name][$hash]['amount']++;
            }
            else {

                $this -> computedResults[$name][$hash]['results'] = $functions[$name]['callable'](...$arguments);
                $this -> computedResults[$name][$hash]['amount'] = 1;
            }

            return $this -> computedResults[$name][$hash]['results'];
        }

        return $functions[$name]['callable'](...$arguments);
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
     * Renders the content
     * @param string $file
     * @return false|string
     */
    public function render(string $file) {

        ob_start();
        extract($this -> parser -> getVariables(), EXTR_OVERWRITE);
        include($file);

        return ob_get_clean();
    }


    /**
     * Converts s:bind HTML attributes (i.e. class and stlye) to normal HTML attributes
     * @param $data
     * @param string|null $classes
     * @return string|null
     */
    public function toHtmlAttribute($data, string $classes = null, string $delimeter = ' '): ?string {

        $output = [];

        if(true === is_array($data)) {

            foreach($data as $key => $value) {

                if(true === is_string($key)) {

                    if(true === (bool) $value) {
                        $output[] = $key;
                    }
                }
                else {
                    $output[] = $value;
                }
            }
        }
        elseif(true === is_string($data) || is_numeric($data)) {
            $output[] = $data;
        }

        $output = array_filter(array_unique(array_merge($output, explode($delimeter, ($classes ?? '')))), function($class) { return false === in_array($class, ['', null]);});

        if(count($output) > 0) {
            return implode($delimeter, $output);
        }

        return null;
    }
}
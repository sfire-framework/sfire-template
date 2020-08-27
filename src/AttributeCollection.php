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

use sFire\Dom\Tags\Attribute as NodeAttribute;
use sFire\Dom\Elements\Node;
use sFire\Template\Exceptions\BadFunctionCallException;


/**
 * Class Attribute
 * @package sFire\Template
 */
class AttributeCollection {


    /**
     * Contains known html boolean attributes
     * @var array
     */
    private array $booleanAttributes = [

        'async',
        'autocomplete' => [1 => 'on', 0 => 'off'],
        'autofocus',
        'autoplay',
        'border' => [1 => '1', 0 => '0'],
        'checked',
        'compact',
        'contenteditable' => [1 => 'true', 0 => 'false'],
        'controls',
        'default',
        'defer',
        'disabled',
        'formnovalidate' => ['formnovalidate'],
        'frameborder' => [1 => '1', 0 => '0'],
        'hidden',
        'indeterminate',
        'ismap',
        'loop',
        'multiple',
        'muted',
        'nohref',
        'noresize' => ['noresize'],
        'noshade',
        'novalidate',
        'nowrap',
        'open',
        'readonly',
        'required',
        'reversed',
        'scoped',
        'seamless',
        'selected',
        'sortable',
        'spellcheck' => [1 => 'true', 0 => 'false'],
        'translate' => [1 => 'yes', 0 => 'no'],
    ];


    /**
     * Contains an instance of Node
     * @var null|Node
     */
    private ?Node $node = null;


    /**
     * Contains all attributes
     * @var NodeAttribute[]
     */
    private array $attributes = [];


    /**
     * Contains all the node attributes that may co exists for "s-bind"
     * @var array
     */
    private array $coExistsAttributes = ['class', 'style'];


    /**
     * Contains all the node attributes that needs to be skipped for processing if an s-bind variant has been found as alternative
     * @var array
     */
    private array $skip = [];


    /**
     * Contains if translation should be printed with php tags or not
     * @var bool
     */
    private bool $concat = false;


    /**
     * Attribute constructor.
     * @param Node $node
     * @param bool $concat
     */
    public function __construct(Node $node, bool $concat = false) {

        $this -> node = $node;
        $this -> concat = $concat;
        $this -> parseAttributes();
    }


    /**
     * Returns if an attribute exists or not
     * @param string $attributeName
     * @return bool
     */
    public function exists(string $attributeName): bool {
        return true === isset($this -> attributes[$attributeName]);
    }


    /**
     * Returns an instance of NodeAttribute
     * @param string $attributeName
     * @return null|NodeAttribute
     */
    public function get(string $attributeName): ?NodeAttribute {
        return $this -> attributes[$attributeName] ?? null;
    }


    /**
     * Returns all parsed attributes
     * @return NodeAttribute[]
     */
    public function getAttributes(): array {
        return $this -> attributes;
    }


    /**
     * Formats all the node attributes
     * @return void
     */
    private function parseAttributes(): void {

        foreach($this -> node -> getAttributes() as $attribute) {

            if(true === isset($this -> skip[$attribute -> getName()])) {
                continue;
            }
            
            $attr      = new NodeAttribute();
            $enclosure = $attribute -> getEnclosure();
            $value     = $attribute -> getValue();

            if(null !== $enclosure) {
                $attr -> setEnclosure($enclosure);
            }

            if(null !== $value) {
                $attr -> setValue($value);
            }

            if('s-bind' === $attribute -> getKey()) {

                $type = $attribute -> getType();

                if(null === $type) {
                    throw new BadFunctionCallException('No type given for "s-bind" key');
                }

                $attr -> setName($type);

                $value = (new Functions($value)) -> parse();

                //Check if there are duplicate attributes and merge them
                if(($v = $this -> node -> getAttribute($type)) && false === in_array($attr -> getName(), $this -> coExistsAttributes)) {

                    $attr -> setParsed(sprintf('<?php echo " %1$s=%2$s" . (htmlentities(@%3$s ?: "%4$s", ENT_QUOTES)) . "%2$s"; ?>', $type, $this -> escape($enclosure, '"'), $value, $v -> getValue()));
                    $attr -> setEnclosure($v -> getEnclosure());
                    $this -> attributes[$type] = $attr;
                    $this -> skip[$type] = true;

                    continue;
                }

                //Attribute which should be removed if disabled or showed if enabled
                if(true === in_array($type, $this -> booleanAttributes)) {
                    $attr -> setParsed(sprintf('<?php echo @((%s) ? " %s" : null); ?>', $value, $type));
                }

                //Attributes with boolean values
                elseif(true === isset($this -> booleanAttributes[$type])) {

                    $values = $this -> booleanAttributes[$type];

                    //Attributes with only one possible value if enabled
                    if(count($values) === 1) {
                        $attr -> setParsed(sprintf('<?php echo @((%s) ? " %s=%s%s%3$s" : null); ?>', $value, $type, $enclosure, $values[0]));
                    }

                    //Attributes with two possible values
                    else {
                        $attr -> setParsed(sprintf('<?php echo @((%s) ? " %s=%s%s%3$s" : " %2$s=%3$s%s%3$s"); ?>', $value, $type, $enclosure, $values[1], $values[0]));
                    }
                }

                //Attributes that are not in the known boolean list
                else {

                    switch($type) {

                        case 'class'     : $this -> parseAttribute($attr, ' '); break;
                        case 'style'     : $this -> parseAttribute($attr, '; '); break;
                        case 's-partial' : $this -> parsePartial($attr); break;

                        default : $attr -> setParsed(sprintf(' %1$s=%2$s<?php echo @htmlentities((string) %3$s, ENT_QUOTES); ?>%2$s', $type, $enclosure, $value));
                    }
                }
            }
            elseif('s-translate' === $attribute -> getKey() && null !== $attribute -> getType()) {

                if(true === $this -> concat) {

                    $enclosure = $enclosure === "'" ? $this -> escape($enclosure) : $enclosure;
                    $attr -> setParsed(sprintf(' %1$s=%2$s\' . $this -> translateAttribute(%3$s) . \'%2$s', $attribute -> getType(), $enclosure, $value));
                }
                else {
                    $attr -> setParsed(sprintf(' %1$s=%2$s<?php echo $this -> translateAttribute(%3$s); ?>%2$s', $attribute -> getType(), $enclosure, $value));
                }
            }
            else {

                $attr -> setName($attribute -> getName());
                $attr -> setParsed(' ' . $attr -> getParsed());
            }

            $this -> attributes[$attr -> getName()] = $attr;
        }

        //Make sure the attribute are in the correct order for execution
        uasort($this -> attributes, function(NodeAttribute $a, NodeAttribute $b) {

            $sort = ['s-if' => 0, 's-elseif' => 1, 's-else' => 2, 's-for' => 3];
            return ($sort[$a -> getName()] ?? 4) > ($sort[$b -> getName()] ?? 4);
        });
    }


    /**
     * Escapes a given character by adding a back slash before the character
     * @param string $content The content to be searched for
     * @param string $character The character that needs to be escaped
     * @return string
     */
    private function escape(string $content, string $character = "'"): string {

        $length = strlen($content);
        $output = '';

        for($i = 0; $i < $length; $i++) {

            if($content[$i] === $character) {

                $escaped = false;

                for($a = $i; $a >= 0; $a--) {

                    if($content[$a] === '\\') {
                        $escaped = !$escaped;
                    }
                    else {
                        break;
                    }
                }

                if(false === $escaped) {
                    $output .= '\\';
                }
            }

            $output .= $content[$i];
        }

        return $output;
    }


    /**
     * Parses the s-bind:class and class attribute of a given node
     * @param NodeAttribute $attribute
     * @return void
     */
    private function parseAttribute(NodeAttribute $attribute, string $delimiter): void {

        $enclosure = $attribute -> getEnclosure();
        $value     = $attribute -> getValue();
        $value     = $value ? (new Functions($value)) -> parse() : $value;

        if($class = $this -> node -> getAttribute($attribute -> getName())) {

            $attribute -> setParsed(sprintf(' %1$s=%2$s<?php echo htmlentities($this -> toHtmlAttribute(%3$s, "%4$s", "%5$s"), ENT_QUOTES); ?>%2$s', $attribute -> getName(), $enclosure, $value, $class -> getValue(), $delimiter));
            $this -> node -> removeAttribute($attribute -> getName());
            unset($this -> attributes[$attribute -> getName()]);
            $this -> skip[$attribute -> getName()] = true;
        }
        else {
            $attribute -> setParsed(sprintf('<?php if($___attributes = $this -> toHtmlAttribute(%3$s, null, "%4$s")): ?> %1$s=%2$s<?php echo htmlentities($___attributes, ENT_QUOTES); ?>%2$s<?php endif; ?>', $attribute -> getName(), $enclosure, $value, $delimiter));
        }
    }


    /**
     *
     * @param NodeAttribute $attribute
     * @return void
     */
    private function parsePartial(NodeAttribute $attribute): void {

        $value     = $attribute -> getValue();
        $value     = $value ? (new Functions($value)) -> parse() : $value;

        $code = sprintf('<?php echo $this -> partial(%s, true); ?>', $value);
        $attribute -> setParsed($code);
        $attribute -> setName('s-partial-var');

        unset($this -> attributes['partial']);
        $this -> skip['partial'] = true;
    }
}
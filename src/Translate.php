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

use sFire\Dom\Tags\Tag;
use sFire\Dom\Parser;
use sFire\Dom\Elements\Node;
use sFire\Dom\Elements\Text;
use sFire\Localization\Translation as Translator;
use sFire\Template\Exceptions\RuntimeException;


/**
 * Class Translate
 * @package sFire\Template
 */
class Translate {


    /**
     * Contains the text that needs to be translated
     * @var string
     */
    private string $content = '';


    /**
     * Contains the parameters for the translation function
     * @var null|string
     */
    private ?string $parameters = null;


    /**
     * Contains the node that wanted to start the translation
     * @var null|Node
     */
    private ?Node $node = null;


    /**
     * Contains an instance of Translator
     * @var null|Translator
     */
    private ?Translator $translator = null;


    /**
     * Constructor
     */
    public function __construct() {
        $this -> translator = new Translator();
    }


    /**
     * Resets the node, parameters and content
     * @return void
     */
    public function reset(): void {

        $this -> node = null;
        $this -> content = '';
        $this -> parameters = null;
    }


    /**
     * Translates a node attribute value
     * @param string $path
     * @param int $plural
     * @param null|array $variables
     * @param null|string $language
     * @return string
     */
    public function translateAttribute(string $path, array $variables = null, int $plural = 0, string $language = null): string {
        return $this -> translator -> translate($path, $variables, $plural, $language);
    }


    /**
     * Translates a node text value
     * @param string $content
     * @param string $path
     * @param int $plural
     * @param null|array $variables
     * @param null|string $language
     * @return string
     */
    public function translate(string $content, string $path, array $variables = null, int $plural = 0, string $language = null): string {

        $text = $this -> translator -> translate($path, $variables, $plural, $language, $content);

        if(null !== $text) {

            $parser = new Parser();
            $content = $this -> parseTranslationText($parser -> parse($content), $parser -> parse($text));
            return implode('', $content);
        }

        return '';
    }


    /**
     * @param string $filePath
     * @param string $language
     * @return void
     */
    public function loadTranslationFile(string $filePath, string $language): void {
        $this -> translator -> loadFile($filePath, $language);
    }


    /**
     * Set the text that needs to be translated
     * @param string $content
     * @return void
     */
    public function setContent(string $content): void {
        $this -> content = $content;
    }


    /**
     * Set the node that wants to start the translation
     * @param Node $node
     * @return void
     */
    public function setNode(Node $node): void {
        $this -> node = $node;
    }


    /**
     * Appends the current text that needs to be translated
     * @param $content
     * @return void
     */
    public function appendContent($content): void {
        $this -> content .= $content;
    }


    /**
     * Sets the parameters for the translation function
     * @param string $parameters
     * @return void
     */
    public function setParameters(string $parameters): void {
        $this -> parameters = $parameters;
    }


    /**
     * Returns the node that wanted to start the translation
     * @return null|Node
     */
    public function getNode(): ?Node {
        return $this -> node;
    }


    /**
     * Returns the parameters for the translation function
     * @return null|string
     */
    public function getParameters(): ?string {
        return $this -> parameters;
    }


    /**
     * Returns the text that needs to be translated
     * @return string
     */
    public function getContent(): string {
        return $this -> content;
    }


    /**
     * @param array $contentNodes
     * @param array $translationNodes
     * @param array $output
     * @return array
     * @throws RuntimeException
     */
    private function parseTranslationText(array $contentNodes, array $translationNodes, array &$output = []): array {

        foreach($translationNodes as $index => $translationNode) {

            if($translationNode instanceof Text) {

                $output[] = $translationNode -> getContent();
                continue;
            }

            if(false === isset($contentNodes[$index]) || $contentNodes[$index] instanceof Text) {
                throw new RuntimeException(sprintf('Blueprint of translation text does not match. Found a "%s" tag element in translation text, but not in the text that needs to be translated.', $translationNode -> getTag() -> getName()));
            }

            $contentNode = $contentNodes[$index];

            /** @var Tag $tag */
            $tag = $contentNode -> getTag();

            if($translationNode -> getTag() -> getName() !== $tag -> getName()) {
                throw new RuntimeException(sprintf('Translation should contain a "%s" tag element but found a "%s" tag element', $tag -> getName(), $translationNode -> getTag() -> getName()));
            }

            $output[] = $tag -> getContent();

            if(true === $contentNode -> hasChildren()) {
                $this -> parseTranslationText($contentNode -> getChildren(), $translationNode -> getChildren(), $output);
            }

            if(true ===  $tag -> shouldHaveClosingTag()) {
                $output[] = sprintf('</%s>',  $tag -> getName());
            }
        }

        return $output;
    }
}
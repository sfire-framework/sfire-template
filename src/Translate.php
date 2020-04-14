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
use sFire\FileControl\File;
use sFire\Template\Exception\RuntimeException;
use sFire\DataControl\Translators\StringTranslator;
use sFire\Dom\Parser;
use sFire\Dom\Elements\Node;
use sFire\Dom\Elements\Text;


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
     * Contains all the translations
     * @var array
     */
    private array $translations = [];


    /**
     * Contains the current language (i.e. en or nl)
     * @var null|string
     */
    private ?string $language = null;


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

        //Get the translations array
        $translations = $this -> getTranslation($this -> translations[$language ?? $this -> language], $path);

        //Find the correct translation
        $content = $this -> replaceContentWithTranslations('', $translations, $plural);

        //Replace named variables
        return $this -> replaceNamedVariables($content, $variables);
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

        //Get the translations array
        $translations = $this -> getTranslation($this -> translations[$language ?? $this -> language], $path);

        //Find the correct translation
        $content = $this -> replaceContentWithTranslations($content, $translations, $plural);

        //Replace named variables
        return $this -> replaceNamedVariables($content, $variables);
    }


    /**
     * Returns an array with translation text that matches a given path
     * @param $data
     * @param $path
     * @return array
     */
    private function getTranslation($data, $path): array {

        $translator   = new StringTranslator();
        $translations = $translator -> get($data, $path);

        if(null === $translations) {
            return [];
        }

        $translations = true === is_array($translations) ? $translations : ['a' => $translations];
        return array_reverse($translations, true);
    }


    /**
     * Replaces given text content with a found translation array
     * @param string $content
     * @param array $translations
     * @param int $plural
     * @return array|string
     */
    private function replaceContentWithTranslations(string $content, array $translations, int $plural = 0) {

        $text = null;

        foreach($translations as $amount => $translation) {

            if(true === (bool) preg_match('#^(?<from>(?:-)?(?:[0-9]+))?(?<separator>,?)(?<to>(?:-)?(?:[0-9]+)?)$#', $amount, $match)) {

                if($match['separator'] === '') {

                    if($plural == $match['from']) {

                        $text = $translation;
                        break;
                    }
                }
                else {

                    if($match['from'] !== '' && $match['to'] !== '') {

                        if($plural >= $match['from'] && $plural <= $match['to']) {

                            $text = $translation;
                            break;
                        }
                    }
                    elseif($match['from'] !== '' && $match['to'] === '') {

                        if($plural >= $match['from']) {

                            $text = $translation;
                            break;
                        }
                    }
                    elseif($match['from'] === '' && $match['to'] !== '') {

                        if($plural <= $match['from']) {

                            $text = $translation;
                            break;
                        }
                    }
                }
            }
            else {
                $text = $translation;
            }
        }

        if(null !== $text) {

            $parser = new Parser();
            $content = $this -> parseTranslationText($parser -> parse($content), $parser -> parse($text));
            return implode('', $content);
        }

        return $content;
    }


    /**
     * Replaces all named attributes in a given text
     * @param string $text
     * @param null|array $variables
     * @return null|string
     */
    private function replaceNamedVariables(string $text, ?array $variables): ?string {

        //Replace named variables
        if(null !== $variables) {

            foreach($variables as $replace => $replacement) {
                $text = str_replace(':' . $replace, $replacement, $text);
            }
        }

        return $text;
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


    /**
     * @param string $data
     * @param string $language
     * @return void
     * @throws RuntimeException
     */
    public function loadTranslationFile(string $data, string $language): void {

        $file = new File($data);

        if(false === $file -> exists()) {
            throw new RuntimeException(sprintf('Template file "%s" does not exists', $file));
        }

        $content = $this -> parseTranslationFile($file);

        $this -> translations[$language] = array_merge($content, $this -> translations[$language] ?? []);
        $this -> language = $language;
    }


    /**
     * @param File $file
     * @return array
     */
    private function parseTranslationFile(File $file): array {

        $content = require($file -> getPath());
        return $this -> parseTranslationArray($content);
    }


    /**
     * @param array $translations
     * @param array $output
     * @return array
     */
    private function parseTranslationArray(array $translations, &$output = []) {

        foreach($translations as $key => $translation) {

            if(true === is_array($translation)) {

                $output[$key] = [];
                $this -> parseTranslationArray($translation, $output[$key]);
                continue;
            }

            $output[$key] = $translation;
        }

        return $output;
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
}
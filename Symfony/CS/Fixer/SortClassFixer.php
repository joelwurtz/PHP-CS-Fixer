<?php

/*
 * This file is part of the PHP CS utility.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\CS\Fixer;

use Symfony\CS\FixerInterface;

/**
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
class SortClassFixer implements FixerInterface
{
    const IN_FILE = 0;
    const IN_CLASS = 1;
    const IN_METHOD = 2;
    const IN_PROPERTY = 3;

    public function fix(\SplFileInfo $file, $content)
    {
        $tokens = token_get_all($content);
        $baseClassTree = array(
            'constant' => "",
            'properties' => array(),
            'methods' => array()
        );

        $newFileContent = $currentContent = "";
        $currentPlacment = self::IN_FILE;
        $tokenIterator = new \ArrayIterator($tokens);
        $newFileContent = $this->getTokenContent($tokenIterator->current());

        while ($tokenIterator->valid()) {
            $tokenIterator->next();
            $token = $tokenIterator->current();

            if ($currentPlacment == self::IN_FILE) {
                $newFileContent .= $this->getTokenContent($token);

                if ($this->getTokenType($token) == T_CLASS || $this->getTokenType($token) == T_ABSTRACT || $this->getTokenType($token) == T_INTERFACE) {

                    while ($this->getTokenContent($token) != "{") {
                        $tokenIterator->next();
                        $token = $tokenIterator->current();
                        $newFileContent .= $this->getTokenContent($token);
                    }

                    $currentPlacment = self::IN_CLASS;
                    $currentClass = $baseClassTree;
                    $currentContent = "";
                }
            } elseif ($currentPlacment == self::IN_CLASS) {
                if ($this->getTokenContent($token) == "}") {
                    ksort($currentClass['methods']);
                    ksort($currentClass['properties']);

                    $newFileContent .= $currentClass['constant'];

                    foreach ($currentClass['properties'] as $prop) {
                        $newFileContent .= $prop;
                    }

                    foreach ($currentClass['methods'] as $method) {
                        $newFileContent .= $method;
                    }

                    $newFileContent .= $currentContent.$this->getTokenContent($token);
                    $currentPlacment = self::IN_FILE;
                    continue;
                }

                $currentContent .= $this->getTokenContent($token);

                if ($this->getTokenType($token) == T_CONST) {

                    while ($this->getTokenContent($token) != ";") {
                        $tokenIterator->next();
                        $token = $tokenIterator->current();
                        $currentContent .= $this->getTokenContent($token);
                    }

                    $currentClass['constant'] .= $currentContent;
                    $currentContent = "";
                } elseif (
                    $this->getTokenType($token) == T_ABSTRACT
                    || $this->getTokenType($token) == T_PUBLIC
                    || $this->getTokenType($token) == T_PROTECTED
                    || $this->getTokenType($token) == T_PRIVATE
                    || $this->getTokenType($token) == T_STATIC
                    || $this->getTokenType($token) == T_FUNCTION
                    || $this->getTokenType($token) == T_VAR
                ) {
                    if ($this->getTokenType($token) == T_FUNCTION) {
                        $currentPlacment = self::IN_METHOD;
                    } else {
                        $position = $tokenIterator->key();

                        while ($this->getTokenContent($token) != ";" && $this->getTokenType($token) != T_FUNCTION) {
                            $tokenIterator->next();
                            $token = $tokenIterator->current();
                        }

                        if ($this->getTokenContent($token) == ";") {
                            $currentPlacment = self::IN_PROPERTY;
                        } else {
                            $currentPlacment = self::IN_METHOD;
                        }

                        $tokenIterator->seek($position);
                    }

                    if ($currentPlacment == self::IN_METHOD) {
                        while ($this->getTokenType($token) != T_STRING) {
                            $tokenIterator->next();
                            $token = $tokenIterator->current();
                            $currentContent .= $this->getTokenContent($token);
                        }

                        $methodName = $this->getTokenContent($token);

                        while ($this->getTokenContent($token) != '{' && $this->getTokenContent($token) != ';') {
                            $tokenIterator->next();
                            $token = $tokenIterator->current();
                            $currentContent .= $this->getTokenContent($token);
                        }

                        if ($this->getTokenContent($token) == "{") {
                            $blockLevel = 1;
                            while ($blockLevel >= 1) {
                                $tokenIterator->next();
                                $token = $tokenIterator->current();
                                $currentContent .= $this->getTokenContent($token);

                                if ($this->getTokenContent($token) == "{") {
                                    $blockLevel++;
                                } elseif ($this->getTokenContent($token) == "}") {
                                    $blockLevel--;
                                }
                            }
                        }

                        $currentPlacment = self::IN_CLASS;
                        $currentClass['methods'][$methodName] = $currentContent;
                        $currentContent = "";
                    } elseif ($currentPlacment == self::IN_PROPERTY) {
                        while ($this->getTokenType($token) != T_VARIABLE) {
                            $tokenIterator->next();
                            $token = $tokenIterator->current();
                            $currentContent .= $this->getTokenContent($token);
                        }

                        $variableName = $this->getTokenContent($token);

                        while ($this->getTokenContent($token) != ';') {
                            $tokenIterator->next();
                            $token = $tokenIterator->current();
                            $currentContent .= $this->getTokenContent($token);
                        }

                        $currentPlacment = self::IN_CLASS;
                        $currentClass['properties'][$variableName] = $currentContent;
                        $currentContent = "";
                    }
                }
            }
        }

        return $newFileContent;
    }

    public function getDescription()
    {
        return "Sort class methods and properties in alphabetic order";
    }

    public function getLevel()
    {
        return FixerInterface::ALL_LEVEL;
    }

    public function getName()
    {
        return 'sort_class';
    }

    public function getPriority()
    {
        return 0;
    }

    private function getTokenContent($token)
    {
        if (!is_array($token)) {
            return $token;
        }

        return $token[1];
    }

    private function getTokenType($token)
    {
        if (!is_array($token)) {
            return null;
        }

        return $token[0];
    }

    public function supports(\SplFileInfo $file)
    {
        return 'php' == pathinfo($file->getFilename(), PATHINFO_EXTENSION);
    }
}

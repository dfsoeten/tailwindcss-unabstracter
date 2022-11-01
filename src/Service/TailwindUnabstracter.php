<?php

namespace DFSoeten\TailwindcssUnabstracter\Service;

use Exception;
use Generator;
use ScssPhp\ScssPhp\Parser;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class TailwindUnabstracter
{
    private string $htmlFilesPath;

    private Finder $htmlFiles;
    private Finder $tailwindcssFiles;

    private array $tailwindAbstractions = [];

    private array $warnings = [];

    /**
     * @param string $htmlFilesPath
     * @param string $tailwindcssFilesPath
     * @param array $excludedDirectories
     * @throws Exception
     */
    public function __construct(
        string $htmlFilesPath,
        string $tailwindcssFilesPath,
        array  $excludedDirectories,
    )
    {
        $this->htmlFilesPath = $htmlFilesPath;
        $this->htmlFiles = (new Finder())
            ->files()->in($this->htmlFilesPath)
            ->exclude($excludedDirectories)
            ->name('*.html.twig');

        $this->tailwindcssFiles = (new Finder())
            ->files()->in($tailwindcssFilesPath)
            ->exclude($excludedDirectories)
            ->name('*.scss');


        if ($this->htmlFiles->count() === 0) {
            throw new Exception(sprintf('Provided path to HTML files: \'%s\' is not valid.', $htmlFilesPath));
        }

        if ($this->tailwindcssFiles->count() === 0) {
            throw new Exception(sprintf('Provided path to Tailwindcss files: \'%s\' is not valid.', $tailwindcssFilesPath));
        }
    }

    public function prepare(): array
    {
        foreach ($this->tailwindcssFiles as $tailwindcssFile) {
            $parser = new Parser(null);
            $this->populate($parser->parse($tailwindcssFile->getContents())->children, $tailwindcssFile);
        }

        return $this->warnings;
    }

    /**
     * For each Tailwind CSS class, find replacement candidate in target HTML.
     *
     * @return Generator
     */
    public function getReplacementCandidates(): Generator
    {
        foreach ($this->tailwindAbstractions as $selector => $tailwindAbstraction) {
            $tailwindClasses = '';

            if (array_key_exists('tailwind_classes', $tailwindAbstraction)) {
                $tailwindClasses .= ' ' . $tailwindAbstraction['tailwind_classes'];
            }

            if (array_key_exists('extend', $tailwindAbstraction)) {
                $tailwindClasses .= ' ' . $this->tailwindAbstractions[$tailwindAbstraction['extend']]['tailwind_classes'];
            }

            $htmlFiles = clone $this->htmlFiles;
            $htmlFiles->contains($selector);

            $tailwindClasses = trim($tailwindClasses);

            foreach ($htmlFiles as $htmlFile) {
                foreach (file($htmlFile->getRealPath()) as $lineNumber => $line) {
                    if (
                        $tailwindClasses &&
                        (str_contains($line, 'class') || str_contains($line, 'Class')) &&
                        str_contains($line, $selector)
                    ) {
                        yield [
                            'selector' => $selector,
                            'tailwindClasses' => $tailwindClasses,
                            'line' => $line,
                            'lineNumber' => $lineNumber,
                            'realPath' => $htmlFile->getRealPath()
                        ];

                    }
                }
            }
        }
    }

    /**
     * For each SCSS class obtain the corresponding Tailwind CSS classes & identify additional information such as SASS directives or custom styles.
     *
     * @param array $children
     * @param SplFileInfo $file
     * @param array|null $selectors
     * @return void
     * @throws Exception
     */
    private function populate(array $children, SplFileInfo $file, array|null $selectors = null): void
    {
        foreach ($children as $child) {
            $block = $child[1];

            if (is_object($block) && count($block->children) !== 0) {
                $this->populate($block->children, $file, $block->selectors);
            }

            if (is_array($block) && $selectors !== null) {
                /**
                 * Loop over all selectors for current SCSS block e.g. "h1, .heading--first"
                 */
                foreach ($selectors as $selector) {
                    foreach ($selector as $selectorParts) {
                        /**
                         * For each of these selectors, which ones are classes?
                         */
                        if (in_array('.', $selectorParts)) {
                            $selector = $this->getSelector($selectorParts);
                            $this->tailwindAbstractions[$selector] = array_key_exists($selector, $this->tailwindAbstractions) ? $this->tailwindAbstractions[$selector] : [];

                            /**
                             * For the current SASS block, find Tailwind CSS @apply and SASS @extend directives.
                             *
                             * For custom CSS code, either throw a warning or use tailwind's arbitrary CSS properties: https://tailwindcss.com/docs/adding-custom-styles#arbitrary-properties
                             */
                            switch ($child[0]) {
                                case 'directive':
                                    /**
                                     * Tailwind's @apply directive
                                     */
                                    if ($block[0] === 'apply') {
                                        $tailwindClasses = trim(preg_replace('/\s\s+/', ' ', $block[1][2][0]));

                                        $this->tailwindAbstractions[$selector]['tailwind_classes'] = $tailwindClasses;
                                    }

                                    break;
                                /**
                                 * SASS @extend directive
                                 */
                                case 'extend':
                                    foreach ($block[0] as $extension) {
                                        $this->tailwindAbstractions[$selector]['extend'] = $extension[1];
                                    }

                                    break;
                                case 'assign':
                                case 'custom':
                                    $this->warnings[] = [
                                        'reason' => 'Custom CSS',
                                        'source_file' => $file->getRealPath()
                                    ];

                                    break;

                                default:
                                    throw new Exception('Unhandled parser block!');
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Perform replacement
     */
    public function replace(
        string $realPath,
        int $lineNumber,
        string $selector,
        string $tailwindClasses,
        string $line
    ): void
    {
        $file = file($realPath);
        $file[$lineNumber] = preg_replace('/' . $selector . '/', $tailwindClasses, $line, 1);
        file_put_contents($realPath, implode($file));
    }

    /**
     * @return array
     */
    public function getTailwindAbstractions(): array
    {
        return $this->tailwindAbstractions;
    }

    /**
     * @return Finder
     */
    public function getTailwindcssFiles(): Finder
    {
        return $this->tailwindcssFiles;
    }

    private function getSelector(array $selectorParts): string
    {
        $selectorParts = array_filter($selectorParts, function ($selectorPart) {
            return is_string($selectorPart);
        });

        preg_match('/(\.)([\w_-]+)/', implode(null, $selectorParts), $matches);

        return $matches[2];
    }
}
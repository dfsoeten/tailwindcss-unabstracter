<?php

namespace DFSoeten\TailwindcssUnabstracter\Command;

use DFSoeten\TailwindcssUnabstracter\Service\TailwindUnabstracter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class TailwindUnabstractCommand extends Command
{
    private const HTML_FILES_ARGUMENT = 'html-files';
    private const TAILWINDCSS_FILES_ARGUMENT = 'tailwindcss-files';
    private const EXCLUDED_DIRECTORIES_ARGUMENT = 'excluded-directories';

    public function __construct()
    {
        parent::__construct('dfs:tailwindcss:unabstract');
    }

    protected function configure()
    {
        $this
            ->setDescription('Un-abstracts TailwindCSS classes given your HTML & SCSS files.')
            ->addArgument(TailwindUnabstractCommand::HTML_FILES_ARGUMENT, InputArgument::REQUIRED, 'Path to HTML files.')
            ->addArgument(TailwindUnabstractCommand::TAILWINDCSS_FILES_ARGUMENT, InputArgument::REQUIRED, 'Path to Tailwindcss files.')
            ->addArgument(TailwindUnabstractCommand::EXCLUDED_DIRECTORIES_ARGUMENT, InputArgument::IS_ARRAY, 'Directories to exclude.', [
                'vendor',
                'node_modules'
            ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tailwindUnabstracter = new TailwindUnabstracter(
            $input->getArgument(TailwindUnabstractCommand::HTML_FILES_ARGUMENT),
            $input->getArgument(TailwindUnabstractCommand::TAILWINDCSS_FILES_ARGUMENT),
            $input->getArgument(TailwindUnabstractCommand::EXCLUDED_DIRECTORIES_ARGUMENT)
        );

        $warnings = $tailwindUnabstracter->prepare();

        foreach ($warnings as $warning) {
            $output->writeln(sprintf('WARNING: %s in %s.', $warning['reason'], $warning['source_file']));
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            sprintf(
                'I have found %s abstractions in %s files with %s warnings (see above). Would you like to un-abstract? This will override your files! You will be asked each time. %s[y/n]: ',
                count($tailwindUnabstracter->getTailwindAbstractions()),
                $tailwindUnabstracter->getTailwindcssFiles()->count(),
                count($warnings),
                PHP_EOL
            ),
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('Cancelled.');

            return 0;
        }

        foreach ($tailwindUnabstracter->getReplacementCandidates() as $replacementCandidate) {
            $question = new ConfirmationQuestion(
                sprintf(
                    '%sReplace abstraction \'%s\' with \'%s\' on line %s in file %s?.%sContext: \'%s\'%s[y/n]: ',
                    PHP_EOL,
                    $replacementCandidate['selector'],
                    $replacementCandidate['tailwindClasses'],
                    $replacementCandidate['lineNumber'] + 1,
                    $replacementCandidate['realPath'],
                    PHP_EOL,
                    trim($replacementCandidate['line']),
                    PHP_EOL
                ),
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                continue;
            }

            $tailwindUnabstracter->replace(
                $replacementCandidate['realPath'],
                $replacementCandidate['lineNumber'],
                $replacementCandidate['selector'],
                $replacementCandidate['tailwindClasses'],
                $replacementCandidate['line']
            );
        }

        $output->writeln('Done.');

        return 0;
    }
}
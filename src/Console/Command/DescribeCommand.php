<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Console\Command;

use PhpCsFixer\Config;
use PhpCsFixer\Console\Application;
use PhpCsFixer\Console\ConfigurationResolver;
use PhpCsFixer\Differ\DiffConsoleFormatter;
use PhpCsFixer\Differ\FullDiffer;
use PhpCsFixer\Documentation\FixerDocumentGenerator;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\DeprecatedFixerInterface;
use PhpCsFixer\Fixer\ExperimentalFixerInterface;
use PhpCsFixer\Fixer\FixerInterface;
use PhpCsFixer\Fixer\InternalFixerInterface;
use PhpCsFixer\FixerConfiguration\AliasedFixerOption;
use PhpCsFixer\FixerConfiguration\AllowedValueSubset;
use PhpCsFixer\FixerConfiguration\DeprecatedFixerOption;
use PhpCsFixer\FixerDefinition\CodeSampleInterface;
use PhpCsFixer\FixerDefinition\FileSpecificCodeSampleInterface;
use PhpCsFixer\FixerDefinition\VersionSpecificCodeSampleInterface;
use PhpCsFixer\FixerFactory;
use PhpCsFixer\Preg;
use PhpCsFixer\RuleSet\DeprecatedRuleSetDescriptionInterface;
use PhpCsFixer\RuleSet\RuleSets;
use PhpCsFixer\StdinFileInfo;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\ToolInfo;
use PhpCsFixer\Utils;
use PhpCsFixer\WordMatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * @internal
 */
#[AsCommand(name: 'describe', description: 'Describe rule / ruleset.')]
final class DescribeCommand extends Command
{
    /** @TODO PHP 8.0 - remove the property */
    protected static $defaultName = 'describe';

    /** @TODO PHP 8.0 - remove the property */
    protected static $defaultDescription = 'Describe rule / ruleset.';

    /**
     * @var ?list<string>
     */
    private ?array $setNames = null;

    private FixerFactory $fixerFactory;

    /**
     * @var null|array<string, FixerInterface>
     */
    private ?array $fixers = null;

    public function __construct(?FixerFactory $fixerFactory = null)
    {
        parent::__construct();

        if (null === $fixerFactory) {
            $fixerFactory = new FixerFactory();
            $fixerFactory->registerBuiltInFixers();
        }

        $this->fixerFactory = $fixerFactory;
    }

    protected function configure(): void
    {
        $this->setDefinition(
            [
                new InputArgument('name', InputArgument::REQUIRED, 'Name of rule / set.', null, fn () => array_merge($this->getSetNames(), array_keys($this->getFixers()))),
                new InputOption('config', '', InputOption::VALUE_REQUIRED, 'The path to a .php-cs-fixer.php file.'),
            ]
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($output instanceof ConsoleOutputInterface) {
            $stdErr = $output->getErrorOutput();
            $stdErr->writeln(Application::getAboutWithRuntime(true));
        }

        $resolver = new ConfigurationResolver(
            new Config(),
            ['config' => $input->getOption('config')],
            getcwd(),
            new ToolInfo()
        );

        $this->fixerFactory->registerCustomFixers($resolver->getConfig()->getCustomFixers());

        $name = $input->getArgument('name');

        try {
            if (str_starts_with($name, '@')) {
                $this->describeSet($output, $name);

                return 0;
            }

            $this->describeRule($output, $name);
        } catch (DescribeNameNotFoundException $e) {
            $matcher = new WordMatcher(
                'set' === $e->getType() ? $this->getSetNames() : array_keys($this->getFixers())
            );

            $alternative = $matcher->match($name);

            $this->describeList($output, $e->getType());

            throw new \InvalidArgumentException(\sprintf(
                '%s "%s" not found.%s',
                ucfirst($e->getType()),
                $name,
                null === $alternative ? '' : ' Did you mean "'.$alternative.'"?'
            ));
        }

        return 0;
    }

    private function describeRule(OutputInterface $output, string $name): void
    {
        $fixers = $this->getFixers();

        if (!isset($fixers[$name])) {
            throw new DescribeNameNotFoundException($name, 'rule');
        }

        $fixer = $fixers[$name];

        $definition = $fixer->getDefinition();

        $output->writeln(\sprintf('<fg=blue>Description of the <info>`%s`</info> rule.</>', $name));
        $output->writeln('');

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln(\sprintf('Fixer class: <comment>%s</comment>.', \get_class($fixer)));
            $output->writeln('');
        }

        if ($fixer instanceof DeprecatedFixerInterface) {
            $successors = $fixer->getSuccessorsNames();
            $message = [] === $successors
                ? \sprintf('it will be removed in version %d.0', Application::getMajorVersion() + 1)
                : \sprintf('use %s instead', Utils::naturalLanguageJoinWithBackticks($successors));

            $endMessage = '. '.ucfirst($message);
            Utils::triggerDeprecation(new \RuntimeException(str_replace('`', '"', "Rule \"{$name}\" is deprecated{$endMessage}.")));
            $message = Preg::replace('/(`[^`]+`)/', '<info>$1</info>', $message);
            $output->writeln(\sprintf('<error>DEPRECATED</error>: %s.', $message));
            $output->writeln('');
        }

        $output->writeln($definition->getSummary());

        $description = $definition->getDescription();

        if (null !== $description) {
            $output->writeln($description);
        }

        $output->writeln('');

        if ($fixer instanceof ExperimentalFixerInterface) {
            $output->writeln('<error>Fixer applying this rule is EXPERIMENTAL.</error>.');
            $output->writeln('It is not covered with backward compatibility promise and may produce unstable or unexpected results.');

            $output->writeln('');
        }

        if ($fixer instanceof InternalFixerInterface) {
            $output->writeln('<error>Fixer applying this rule is INTERNAL.</error>.');
            $output->writeln('It is expected to be used only on PHP CS Fixer project itself.');

            $output->writeln('');
        }

        if ($fixer->isRisky()) {
            $output->writeln('<error>Fixer applying this rule is RISKY.</error>');

            $riskyDescription = $definition->getRiskyDescription();

            if (null !== $riskyDescription) {
                $output->writeln($riskyDescription);
            }

            $output->writeln('');
        }

        if ($fixer instanceof ConfigurableFixerInterface) {
            $configurationDefinition = $fixer->getConfigurationDefinition();
            $options = $configurationDefinition->getOptions();

            $output->writeln(\sprintf('Fixer is configurable using following option%s:', 1 === \count($options) ? '' : 's'));

            foreach ($options as $option) {
                $line = '* <info>'.OutputFormatter::escape($option->getName()).'</info>';
                $allowed = HelpCommand::getDisplayableAllowedValues($option);

                if (null === $allowed) {
                    $allowed = array_map(
                        static fn (string $type): string => '<comment>'.$type.'</comment>',
                        $option->getAllowedTypes(),
                    );
                } else {
                    $allowed = array_map(static fn ($value): string => $value instanceof AllowedValueSubset
                        ? 'a subset of <comment>'.Utils::toString($value->getAllowedValues()).'</comment>'
                        : '<comment>'.Utils::toString($value).'</comment>', $allowed);
                }

                $line .= ' ('.Utils::naturalLanguageJoin($allowed, '').')';

                $description = Preg::replace('/(`.+?`)/', '<info>$1</info>', OutputFormatter::escape($option->getDescription()));
                $line .= ': '.lcfirst(Preg::replace('/\.$/', '', $description)).'; ';

                if ($option->hasDefault()) {
                    $line .= \sprintf(
                        'defaults to <comment>%s</comment>',
                        Utils::toString($option->getDefault())
                    );
                } else {
                    $line .= '<comment>required</comment>';
                }

                if ($option instanceof DeprecatedFixerOption) {
                    $line .= '. <error>DEPRECATED</error>: '.Preg::replace(
                        '/(`.+?`)/',
                        '<info>$1</info>',
                        OutputFormatter::escape(lcfirst($option->getDeprecationMessage()))
                    );
                }

                if ($option instanceof AliasedFixerOption) {
                    $line .= '; <error>DEPRECATED</error> alias: <comment>'.$option->getAlias().'</comment>';
                }

                $output->writeln($line);
            }

            $output->writeln('');
        }

        $codeSamples = array_filter($definition->getCodeSamples(), static function (CodeSampleInterface $codeSample): bool {
            if ($codeSample instanceof VersionSpecificCodeSampleInterface) {
                return $codeSample->isSuitableFor(\PHP_VERSION_ID);
            }

            return true;
        });

        if (0 === \count($definition->getCodeSamples())) {
            $output->writeln([
                'Fixing examples are not available for this rule.',
                '',
            ]);
        } elseif (0 === \count($codeSamples)) {
            $output->writeln([
                'Fixing examples <error>cannot be</error> demonstrated on the current PHP version.',
                '',
            ]);
        } else {
            $output->writeln('Fixing examples:');

            $differ = new FullDiffer();
            $diffFormatter = new DiffConsoleFormatter(
                $output->isDecorated(),
                \sprintf(
                    '<comment>   ---------- begin diff ----------</comment>%s%%s%s<comment>   ----------- end diff -----------</comment>',
                    \PHP_EOL,
                    \PHP_EOL
                )
            );

            foreach ($codeSamples as $index => $codeSample) {
                $old = $codeSample->getCode();
                $tokens = Tokens::fromCode($old);

                $configuration = $codeSample->getConfiguration();

                if ($fixer instanceof ConfigurableFixerInterface) {
                    $fixer->configure($configuration ?? []);
                }

                $file = $codeSample instanceof FileSpecificCodeSampleInterface
                    ? $codeSample->getSplFileInfo()
                    : new StdinFileInfo();

                $fixer->fix($file, $tokens);

                $diff = $differ->diff($old, $tokens->generateCode());

                if ($fixer instanceof ConfigurableFixerInterface) {
                    if (null === $configuration) {
                        $output->writeln(\sprintf(' * Example #%d. Fixing with the <comment>default</comment> configuration.', $index + 1));
                    } else {
                        $output->writeln(\sprintf(' * Example #%d. Fixing with configuration: <comment>%s</comment>.', $index + 1, Utils::toString($codeSample->getConfiguration())));
                    }
                } else {
                    $output->writeln(\sprintf(' * Example #%d.', $index + 1));
                }

                $output->writeln([$diffFormatter->format($diff, '   %s'), '']);
            }
        }

        $ruleSetConfigs = FixerDocumentGenerator::getSetsOfRule($name);

        if ([] !== $ruleSetConfigs) {
            ksort($ruleSetConfigs);
            $plural = 1 !== \count($ruleSetConfigs) ? 's' : '';
            $output->writeln("Fixer is part of the following rule set{$plural}:");

            $ruleSetDefinitions = RuleSets::getSetDefinitions();

            foreach ($ruleSetConfigs as $set => $config) {
                \assert(isset($ruleSetDefinitions[$set]));
                $ruleSetDescription = $ruleSetDefinitions[$set];
                $deprecatedDesc = ($ruleSetDescription instanceof DeprecatedRuleSetDescriptionInterface) ? ' *(deprecated)*' : '';
                if (null !== $config) {
                    $output->writeln(\sprintf('* <info>%s</info> with config: <comment>%s</comment>', $set.$deprecatedDesc, Utils::toString($config)));
                } else {
                    $output->writeln(\sprintf('* <info>%s</info> with <comment>default</comment> config', $set.$deprecatedDesc));
                }
            }

            $output->writeln('');
        }
    }

    private function describeSet(OutputInterface $output, string $name): void
    {
        if (!\in_array($name, $this->getSetNames(), true)) {
            throw new DescribeNameNotFoundException($name, 'set');
        }

        $ruleSetDefinitions = RuleSets::getSetDefinitions();
        $ruleSetDescription = $ruleSetDefinitions[$name];
        $fixers = $this->getFixers();

        $output->writeln(\sprintf('<fg=blue>Description of the <info>`%s`</info> set.</>', $ruleSetDescription->getName()));
        $output->writeln('');

        $output->writeln($this->replaceRstLinks($ruleSetDescription->getDescription()));
        $output->writeln('');

        if ($ruleSetDescription instanceof DeprecatedRuleSetDescriptionInterface) {
            $successors = $ruleSetDescription->getSuccessorsNames();
            $message = [] === $successors
                ? \sprintf('it will be removed in version %d.0', Application::getMajorVersion() + 1)
                : \sprintf('use %s instead', Utils::naturalLanguageJoinWithBackticks($successors));

            Utils::triggerDeprecation(new \RuntimeException(str_replace('`', '"', "Set \"{$name}\" is deprecated, {$message}.")));
            $message = Preg::replace('/(`[^`]+`)/', '<info>$1</info>', $message);
            $output->writeln(\sprintf('<error>DEPRECATED</error>: %s.', $message));
            $output->writeln('');
        }

        if ($ruleSetDescription->isRisky()) {
            $output->writeln('<error>This set contains risky rules.</error>');
            $output->writeln('');
        }

        $help = '';

        foreach ($ruleSetDescription->getRules() as $rule => $config) {
            if (str_starts_with($rule, '@')) {
                $set = $ruleSetDefinitions[$rule];
                $help .= \sprintf(
                    " * <info>%s</info>%s\n   | %s\n\n",
                    $rule,
                    $set->isRisky() ? ' <error>risky</error>' : '',
                    $this->replaceRstLinks($set->getDescription())
                );

                continue;
            }

            $fixer = $fixers[$rule];

            $definition = $fixer->getDefinition();
            $help .= \sprintf(
                " * <info>%s</info>%s\n   | %s\n%s\n",
                $rule,
                $fixer->isRisky() ? ' <error>risky</error>' : '',
                $definition->getSummary(),
                true !== $config ? \sprintf("   <comment>| Configuration: %s</comment>\n", Utils::toString($config)) : ''
            );
        }

        $output->write($help);
    }

    /**
     * @return array<string, FixerInterface>
     */
    private function getFixers(): array
    {
        if (null !== $this->fixers) {
            return $this->fixers;
        }

        $fixers = [];

        foreach ($this->fixerFactory->getFixers() as $fixer) {
            $fixers[$fixer->getName()] = $fixer;
        }

        $this->fixers = $fixers;
        ksort($this->fixers);

        return $this->fixers;
    }

    /**
     * @return list<string>
     */
    private function getSetNames(): array
    {
        if (null !== $this->setNames) {
            return $this->setNames;
        }

        $this->setNames = RuleSets::getSetDefinitionNames();

        return $this->setNames;
    }

    /**
     * @param string $type 'rule'|'set'
     */
    private function describeList(OutputInterface $output, string $type): void
    {
        if ($output->getVerbosity() < OutputInterface::VERBOSITY_VERBOSE) {
            return;
        }

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE || 'set' === $type) {
            $output->writeln('<comment>Defined sets:</comment>');

            $items = $this->getSetNames();
            foreach ($items as $item) {
                $output->writeln(\sprintf('* <info>%s</info>', $item));
            }
        }

        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE || 'rule' === $type) {
            $output->writeln('<comment>Defined rules:</comment>');

            $items = array_keys($this->getFixers());
            foreach ($items as $item) {
                $output->writeln(\sprintf('* <info>%s</info>', $item));
            }
        }
    }

    private function replaceRstLinks(string $content): string
    {
        return Preg::replaceCallback(
            '/(`[^<]+<[^>]+>`_)/',
            static fn (array $matches) => Preg::replaceCallback(
                '/`(.*)<(.*)>`_/',
                static fn (array $matches): string => $matches[1].'('.$matches[2].')',
                $matches[1]
            ),
            $content
        );
    }
}

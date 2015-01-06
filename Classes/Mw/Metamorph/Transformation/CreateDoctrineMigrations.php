<?php
namespace Mw\Metamorph\Transformation;


use Mw\Metamorph\Domain\Model\MorphConfiguration;
use Mw\Metamorph\Domain\Service\MorphExecutionState;
use Mw\Metamorph\Exception\HumanInterventionRequiredException;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Persistence\Doctrine\Service as DoctrineService;


class CreateDoctrineMigrations extends AbstractTransformation
{


    /**
     * @var DoctrineService
     * @Flow\Inject
     */
    protected $doctrineService;


    public function execute(MorphConfiguration $configuration, MorphExecutionState $state, OutputInterface $out)
    {
        $this->log('Validating schema.');

        $validationResults = $this->doctrineService->validateMapping();
        if (count($validationResults) === 0)
        {
            $this->log('Validation <info>passed</info>.');
        }
        else
        {
            $this->log('Validation <error>failed</error>');
            $dump = \TYPO3\Flow\var_dump($validationResults, NULL, TRUE);

            throw new HumanInterventionRequiredException(
                'The schema validation failed (see below). Please try to flush your cache and re-run <comment>doctrine:validate</comment>.' . PHP_EOL .
                'Then, fix remaining mapping errors manually and re-run <comment>morph:execute</comment>.' .
                "\n\n" . $dump
            );
        }
    }



}
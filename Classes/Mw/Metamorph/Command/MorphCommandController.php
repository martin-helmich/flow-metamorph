<?php
namespace Mw\Metamorph\Command;

/*                                                                        *
 * This script belongs to the TYPO3 Flow package "Mw.Metamorph".          *
 *                                                                        *
 * (C) 2014 Martin Helmich <m.helmich@mittwald.de>                        *
 *          Mittwald CM Service GmbH & Co. KG                             *
 *                                                                        */

use Mw\Metamorph\Command\Prompt\MorphCreationDataPrompt;
use Mw\Metamorph\Domain\Repository\MorphConfigurationRepository;
use Mw\Metamorph\Domain\Service\Dto\MorphCreationDto;
use Mw\Metamorph\Domain\Service\MorphServiceInterface;
use Mw\Metamorph\Exception\HumanInterventionRequiredException;
use Mw\Metamorph\Exception\MorphNotFoundException;
use Mw\Metamorph\Io\DecoratedOutput;
use Mw\Metamorph\Logging\LoggingWrapper;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cli\CommandController;

/**
 * @Flow\Scope("singleton")
 */
class MorphCommandController extends CommandController {

	/**
	 * @var MorphConfigurationRepository
	 * @Flow\Inject
	 */
	protected $morphConfigurationRepository;

	/**
	 * @var MorphServiceInterface
	 * @Flow\Inject
	 */
	protected $morphService;

	/**
	 * @var LoggingWrapper
	 * @Flow\Inject
	 */
	protected $loggingWrapper;

	private function initializeLogging() {
		$this->loggingWrapper->setOutput(new DecoratedOutput($this->output));
	}

	/**
	 * Creates a new site package with a morph configuration.
	 *
	 * @param string $packageKey     The package key to use for the morph package.
	 * @param bool   $nonInteractive Set this flag to suppress interactive prompts during package creation.
	 * @return void
	 */
	public function createCommand($packageKey, $nonInteractive = FALSE) {
		$this->initializeLogging();

		$input  = new ArrayInput([]);
		$output = new DecoratedOutput($this->output);

		$data = new MorphCreationDto();

		if (FALSE === $nonInteractive) {
			$helperSet = new HelperSet(array(new FormatterHelper()));

			$helper = new QuestionHelper();
			$helper->setHelperSet($helperSet);

			$prompt = new MorphCreationDataPrompt($input, $output, $helper);
			$prompt->setValuesOnCreateDto($data);
		}

		$this->morphService->create($packageKey, $data, $output);
	}

	/**
	 * List available morphs.
	 *
	 * @return void
	 */
	public function listCommand() {
		$this->initializeLogging();
		$morphs = $this->morphConfigurationRepository->findAll();

		if (count($morphs)) {
			$this->outputLine('Found <comment>%d</comment> morph configurations:', [count($morphs)]);
			$this->outputLine();

			foreach ($morphs as $morph) {
				$this->outputFormatted($morph->getName(), [], 4);
			}

			$this->outputLine();
		} else {
			$this->outputLine('Found <comment>no</comment> morph configurations.');
			$this->outputLine('Use <comment>./flow morph:create</comment> to create a morph configuration.');
		}

	}

	/**
	 * Morph a TYPO3 CMS application.
	 *
	 * @param string $morphConfigurationName The name of the morph configuration to execute.
	 * @param bool   $reset                  Completely reset stored state before beginning.
	 * @throws \Mw\Metamorph\Exception\MorphNotFoundException
	 * @return void
	 */
	public function executeCommand($morphConfigurationName, $reset = FALSE) {
		$this->initializeLogging();
		$morph = $this->morphConfigurationRepository->findByIdentifier($morphConfigurationName);

		if ($morph === NULL) {
			throw new MorphNotFoundException(
				'No morph configuration with identifier <b>' . $morphConfigurationName . '</b> found!',
				1399993315
			);
		}

		if (TRUE === $reset) {
			$this->morphService->reset($morph, $this->output);
		}

		try {
			$this->morphService->execute($morph, new DecoratedOutput($this->output));
		} catch (HumanInterventionRequiredException $e) {
		} catch (\Exception $e) {
			$this->output->writeln('<error>  UNCAUGHT EXCEPTION  </error>');
			$this->output->writeln('  ' . get_class($e) . ': ' . $e->getMessage());
			$this->output->write('<debug>' . $e->getTraceAsString() . '</debug>' . "\n");
			$this->sendAndExit(1);
		}
	}

}
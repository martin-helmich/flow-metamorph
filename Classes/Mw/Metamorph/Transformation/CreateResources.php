<?php
namespace Mw\Metamorph\Transformation;

use Mw\Metamorph\Domain\Model\MorphConfiguration;
use Mw\Metamorph\Domain\Service\MorphExecutionState;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Package\PackageManagerInterface;
use TYPO3\Flow\Utility\Files;

class CreateResources extends AbstractTransformation implements Progressible {

	use ProgressibleTrait;

	/**
	 * @var PackageManagerInterface
	 * @Flow\Inject
	 */
	protected $packageManager;

	public function execute(MorphConfiguration $configuration, MorphExecutionState $state, OutputInterface $out) {
		$this->startProgress(
			'Migrating resources',
			count($configuration->getResourceMappingContainer()->getResourceMappings())
		);

		foreach ($configuration->getResourceMappingContainer()->getResourceMappings() as $resourceMapping) {
			$package = $this->packageManager->getPackage($resourceMapping->getPackage());

			$targetFilePath  = Files::concatenatePaths(
				[$package->getPackagePath(), $resourceMapping->getTargetFile()]
			);
			$targetDirectory = dirname($targetFilePath);

			Files::createDirectoryRecursively($targetDirectory);
			copy($resourceMapping->getSourceFile(), $targetFilePath);

			$this->advanceProgress();
		}

		$this->finishProgress();
	}
}
<?php
namespace Mw\Metamorph\Transformation;

use Helmich\EventBroker\Annotations as Event;
use Mw\Metamorph\Domain\Event\TargetPackageFileModifiedEvent;
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
		$packageResources = [];
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

			if (!isset($packageResources[$resourceMapping->getPackage()])) {
				$packageResources[$resourceMapping->getPackage()] = [];
			}
			$packageResources[$resourceMapping->getPackage()][] = $resourceMapping->getTargetFile();
			$this->advanceProgress();
		}

		$this->finishProgress();

		foreach ($packageResources as $packageKey => $files) {
			$this->emitFilesModifiedEvent(
				new TargetPackageFileModifiedEvent(
					$configuration,
					$this->packageManager->getPackage($packageKey),
					$files,
					'Migrate resources from source extension.'
				)
			);
		}

	}

	/**
	 * @param TargetPackageFileModifiedEvent $event
	 * @Event\Event
	 */
	protected function emitFilesModifiedEvent(TargetPackageFileModifiedEvent $event) { }
}

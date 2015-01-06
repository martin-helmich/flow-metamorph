<?php
namespace Mw\Metamorph\Transformation;

use Helmich\EventBroker\Annotations as Event;
use Helmich\Scalars\Types\String;
use Mw\Metamorph\Domain\Event\TargetPackageFileModifiedEvent;
use Mw\Metamorph\Domain\Model\MorphConfiguration;
use Mw\Metamorph\Domain\Model\State\ClassMapping;
use Mw\Metamorph\Domain\Repository\MorphConfigurationRepository;
use Mw\Metamorph\Domain\Service\MorphExecutionState;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Package\PackageInterface;
use TYPO3\Flow\Utility\Files;

class CreateClasses extends AbstractTransformation implements Progressible {

	use ProgressibleTrait;

	/**
	 * @var \TYPO3\Flow\Package\PackageManagerInterface
	 * @Flow\Inject
	 */
	protected $packageManager;

	/**
	 * @var MorphConfigurationRepository
	 * @Flow\Inject
	 */
	protected $morphRepository;

	public function execute(MorphConfiguration $configuration, MorphExecutionState $state, OutputInterface $out) {
		$classMappingContainer    = $configuration->getClassMappingContainer();
		$modifiedFilesForPackages = [];

		$this->startProgress('Migrating classes', count($classMappingContainer->getClassMappings()));

		foreach ($classMappingContainer->getClassMappings() as $classMapping) {
			if ($classMapping->getAction() !== ClassMapping::ACTION_MORPH) {
				continue;
			}

			$package      = $this->packageManager->getPackage($classMapping->getPackage());
			$source       = file_get_contents($classMapping->getSourceFile());
			$newClassName = new String($classMapping->getNewClassName());

			$relativeFilename = $newClassName->replace('\\', '/')->append('.php');
			$absoluteFilename = $this->getAbsoluteFilename($relativeFilename, $package);

			Files::createDirectoryRecursively(dirname($absoluteFilename));
			file_put_contents($absoluteFilename, $source);

			$classMapping->setTargetFile($absoluteFilename);

			if (!isset($modifiedFilesForPackages[$package->getPackageKey()])) {
				$modifiedFilesForPackages[$package->getPackageKey()] = [];
			}
			$modifiedFilesForPackages[$package->getPackageKey()][] = substr(
				$absoluteFilename,
				strlen($package->getPackagePath())
			);
			$this->advanceProgress();
		}

		$this->finishProgress();
		$this->morphRepository->update($configuration);

		foreach ($modifiedFilesForPackages as $package => $files) {
			$this->log(
				'<comment>%d</comment> classes written to package <comment>%s</comment>.',
				[count($files), $package]
			);

			$this->emitFilesModifiedEvent(
				new TargetPackageFileModifiedEvent(
					$configuration,
					$this->packageManager->getPackage($package),
					$files,
					'Migrate classes from source extension'
				)
			);
		}
	}

	/**
	 * Determines if a class contains a test case.
	 *
	 * @param \Helmich\Scalars\Types\String $relativeFilename The relative class file name.
	 * @return bool TRUE if the class contains a test case, otherwise FALSE.
	 */
	private function isClassTestCase(String $relativeFilename) {
		return $relativeFilename->strip('/')->contains('Tests/') || $relativeFilename->endsWidth('Test.php');
	}

	/**
	 * Gets the absolute target filename for a class file.
	 *
	 * @param \Helmich\Scalars\Types\String $relativeFilename The relative class file name (auto-derived from class name).
	 * @param PackageInterface              $package          The target package.
	 * @return string The target filename.
	 */
	private function getAbsoluteFilename(String $relativeFilename, PackageInterface $package) {
		if (FALSE === $this->isClassTestCase($relativeFilename)) {
			return (new String($package->getClassesPath()))
				->stripRight('/')
				->append('/')
				->append($relativeFilename)
				->toPrimitive();
		} else {
			return (new String(''))
				->append($package->getPackagePath())
				->stripRight('/')
				->append('/Tests/Unit/')
				->append(
					$relativeFilename
						->replace('Tests/', '')
						->replace((new String($package->getPackageKey()))->replace('.', '/')->append('/'), '')
				)
				->toPrimitive();
		}
	}

	/**
	 * @param TargetPackageFileModifiedEvent $event
	 * @Event\Event
	 */
	protected function emitFilesModifiedEvent(TargetPackageFileModifiedEvent $event) { }
}

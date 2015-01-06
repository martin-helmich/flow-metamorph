<?php
namespace Mw\Metamorph\Transformation;

use Helmich\EventBroker\Annotations as Event;
use Mw\Metamorph\Domain\Event\TargetPackageCleanupEvent;
use Mw\Metamorph\Domain\Model\MorphConfiguration;
use Mw\Metamorph\Domain\Service\MorphExecutionState;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Flow\Annotations as Flow;

class CleanupPackages extends AbstractTransformation {

	/**
	 * @var \TYPO3\Flow\Package\PackageManagerInterface
	 * @Flow\Inject
	 */
	protected $packageManager;

	public function execute(MorphConfiguration $configuration, MorphExecutionState $state, OutputInterface $out) {
		foreach ($configuration->getPackageMappingContainer()->getPackageMappings() as $packageMapping) {
			$packageKey = $packageMapping->getPackageKey();
			if ($this->packageManager->isPackageAvailable($packageKey)) {
				$package = $this->packageManager->getPackage($packageKey);

				$this->emitCleanupEvent(new TargetPackageCleanupEvent($configuration, $package));
				$this->log('PKG:<comment>%s</comment>: <fg=cyan>present</fg=cyan>', [$packageKey]);
			} else {
				$this->log('PKG:<comment>%s</comment>: <fg=green>not present</fg=green>', [$packageKey]);
			}
		}
	}

	/**
	 * @param TargetPackageCleanupEvent $event
	 * @Event\Event
	 */
	protected function emitCleanupEvent(TargetPackageCleanupEvent $event) { }
}

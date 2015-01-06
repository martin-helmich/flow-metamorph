<?php
namespace Mw\Metamorph\Scm\Listener;


use Helmich\EventBroker\Annotations as Event;
use Mw\Metamorph\Domain\Event\TargetPackageCleanupEvent;
use Mw\Metamorph\Domain\Event\TargetPackageCreatedEvent;
use Mw\Metamorph\Domain\Event\TargetPackageFileModifiedEvent;
use Mw\Metamorph\Exception\HumanInterventionRequiredException;
use Mw\Metamorph\Scm\BackendLocator;
use TYPO3\Flow\Annotations as Flow;


class TargetPackageScmSynchronizationListener
{



    /**
     * @var BackendLocator
     * @Flow\Inject
     */
    protected $locator;



    /**
     * @param TargetPackageCleanupEvent $cleanupEvent
     * @throws HumanInterventionRequiredException
     *
     * @Event\Listener("Mw\Metamorph\Domain\Event\TargetPackageCleanupEvent", async=FALSE)
     */
    public function onCleanupPackage(TargetPackageCleanupEvent $cleanupEvent)
    {
        $backend   = $this->locator->getBackendByConfiguration($cleanupEvent->getMorphConfiguration());
        $package   = $cleanupEvent->getPackage();
        $directory = $package->getPackagePath();

        if ($backend->isModified($directory))
        {
            throw new HumanInterventionRequiredException(
                'The package directory <comment>' . $package->getPackagePath() . '</comment> contains local ' .
                'modifications.' . PHP_EOL .
                'Please clean these up or commit them into a local branch.' . PHP_EOL
            );
        }

        $backend->checkoutBranch($directory, 'metamorph');
    }



    /**
     * @param TargetPackageCreatedEvent $createdEvent
     *
     * @Event\Listener("Mw\Metamorph\Domain\Event\TargetPackageCreatedEvent", async=FALSE)
     */
    public function onCreatePackage(TargetPackageCreatedEvent $createdEvent)
    {
        $backend   = $this->locator->getBackendByConfiguration($createdEvent->getMorphConfiguration());
        $package   = $createdEvent->getPackage();
        $directory = $package->getPackagePath();

        $backend->initialize($directory);
        $backend->commit($directory, 'Initial commit');
        $backend->checkoutBranch($directory, 'metamorph');
    }



    /**
     * @param TargetPackageFileModifiedEvent $modifiedEvent
     *
     * @Event\Listener("Mw\Metamorph\Domain\Event\TargetPackageFileModifiedEvent", async=FALSE)
     */
    public function onFilesModified(TargetPackageFileModifiedEvent $modifiedEvent)
    {
        $backend   = $this->locator->getBackendByConfiguration($modifiedEvent->getMorphConfiguration());
        $package   = $modifiedEvent->getPackage();
        $directory = $package->getPackagePath();

        $backend->commit($directory, $modifiedEvent->getPurpose(), $modifiedEvent->getRelativeFilenames());
    }


}
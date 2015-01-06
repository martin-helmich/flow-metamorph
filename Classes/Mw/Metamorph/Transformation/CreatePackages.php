<?php
namespace Mw\Metamorph\Transformation;


use Mw\Metamorph\Domain\Event\TargetPackageCreatedEvent;
use Mw\Metamorph\Domain\Model\MorphConfiguration;
use Mw\Metamorph\Domain\Model\State\PackageMapping;
use Mw\Metamorph\Domain\Service\MorphExecutionState;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Package\MetaData;
use Helmich\EventBroker\Annotations as Event;


class CreatePackages extends AbstractTransformation
{



    /**
     * @var \TYPO3\Flow\Package\PackageManagerInterface
     * @Flow\Inject
     */
    protected $packageManager;



    public function execute(MorphConfiguration $configuration, MorphExecutionState $state, OutputInterface $out)
    {
        $packageMappingContainer = $configuration->getPackageMappingContainer();
        $packageMappingContainer->assertReviewed();

        foreach ($packageMappingContainer->getPackageMappings() as $packageMapping)
        {
            if (FALSE === $this->packageManager->isPackageAvailable($packageMapping->getPackageKey()))
            {
                $package = $this->packageManager->createPackage(
                    $packageMapping->getPackageKey(),
                    $this->createPackageMetaData($packageMapping),
                    NULL,
                    'typo3-flow-package'
                );

                $this->emitCreatedEvent(new TargetPackageCreatedEvent($configuration, $package));

                $this->log(
                    'PKG:<comment>%s</comment>: <fg=green>CREATED</fg=green>',
                    [$packageMapping->getPackageKey()]
                );
            }
            else
            {
                $this->log(
                    'PKG:<comment>%s</comment>: <fg=cyan>EXISTS</fg=cyan>',
                    [$packageMapping->getPackageKey()]
                );
            }
        }
    }



    private function createPackageMetaData(PackageMapping $packageMapping)
    {
        $metaData = new MetaData($packageMapping->getPackageKey());
        $metaData->setDescription($packageMapping->getDescription());
        $metaData->setVersion($packageMapping->getVersion());
        $metaData->setPackageType('typo3-flow-package');

        foreach ($packageMapping->getAuthors() as $author)
        {
            $metaData->addParty(
                new MetaData\Person('Developer', $author['name'], isset($author['email']) ? $author['email'] : NULL)
            );
        }

        return $metaData;
    }



    /**
     * @param TargetPackageCreatedEvent $event
     * @Event\Event
     */
    protected function emitCreatedEvent(TargetPackageCreatedEvent $event) { }
} 
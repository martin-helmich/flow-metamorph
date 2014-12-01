<?php
namespace Mw\Metamorph\Transformation;


use Helmich\Scalars\Types\ArrayList;
use Helmich\Scalars\Types\String;
use Mw\Metamorph\Domain\Model\MorphConfiguration;
use Mw\Metamorph\Domain\Model\State\PackageMapping;
use Mw\Metamorph\Domain\Repository\MorphConfigurationRepository;
use Mw\Metamorph\Domain\Service\MorphExecutionState;
use Mw\Metamorph\Domain\Service\MorphValidationService;
use Mw\Metamorph\Exception\HumanInterventionRequiredException;
use Mw\Metamorph\View\ValidationResultRenderer;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\Flow\Annotations as Flow;


class ExtensionInventory extends AbstractTransformation
{



    /**
     * @var MorphConfigurationRepository
     * @Flow\Inject
     */
    protected $morphRepository;


    /**
     * @var MorphValidationService
     * @Flow\Inject
     */
    protected $morphValidationService;


    /**
     * @var ValidationResultRenderer
     * @Flow\Inject
     */
    protected $validationResultRenderer;



    public function execute(MorphConfiguration $configuration, MorphExecutionState $state, OutputInterface $out)
    {
        $rootDirectory     = $configuration->getSourceDirectory() . '/typo3conf/ext';
        $directoryIterator = new \DirectoryIterator($rootDirectory);
        $matcher           = $configuration->getExtensionMatcher();

        $packageMappingContainer = $configuration->getPackageMappingContainer();
        $foundExtensionKeys      = [];

        foreach ($directoryIterator as $directoryInfo)
        {
            /** @var \DirectoryIterator $directoryInfo */
            if (!$directoryInfo->isDir() || !file_exists($directoryInfo->getPathname() . '/ext_emconf.php'))
            {
                continue;
            }

            $extensionKey = $directoryInfo->getBasename();

            if ($matcher->match($extensionKey))
            {
                $this->log('EXT:<comment>%s</comment>: <info>FOUND</info>', [$extensionKey]);

                $mapping = new PackageMapping($directoryInfo->getPathname(), $extensionKey);
                $mapping->setPackageKey($this->convertExtensionKeyToPackageName($extensionKey));

                $this->enrichPackageDataWithEmConfData($mapping);

                $foundExtensionKeys[] = $extensionKey;
                $packageMappingContainer->addPackageMapping($mapping);
            }
            else
            {
                $this->log('EXT:<comment>%s</comment>: <fg=cyan>IGNORING</fg=cyan>', [$extensionKey]);
            }
        }

        // Remove extensions that are defined in the package map, but not present anymore
        // in the source directory.
        foreach ($packageMappingContainer->getPackageMappings() as $packageMapping)
        {
            if (FALSE === in_array($packageMapping->getExtensionKey(), $foundExtensionKeys))
            {
                $packageMappingContainer->removePackageMapping($packageMapping->getExtensionKey());
            }
        }

        $this->morphRepository->update($configuration);

        $validationResults = $this->morphValidationService->validate($configuration);
        if (NULL !== $validationResults && $validationResults->hasErrors())
        {
            throw new HumanInterventionRequiredException(
                $this->validationResultRenderer->renderValidationResult(
                    $validationResults,
                    'The automatically generated morph configuration is invalid. Please fix the validation errors below manually.' . PHP_EOL .
                    'Have a look at the files in the <comment>' . $configuration->getPackage()->getConfigurationPath() . 'Metamorph</comment> directory.'
                )
            );
        }
    }



    private function convertExtensionKeyToPackageName($extensionKey)
    {
        return str_replace(' ', '.', ucwords(str_replace('_', ' ', $extensionKey)));
    }



    private function enrichPackageDataWithEmConfData(PackageMapping $packageMapping)
    {
        $emConfFile = $packageMapping->getFilePath() . '/ext_emconf.php';
        if (FALSE === file_exists($emConfFile))
        {
            return;
        }

        $_EXTKEY = $packageMapping->getExtensionKey();
        /** @noinspection PhpIncludeInspection */
        include_once($emConfFile);
        if (isset($EM_CONF))
        {
            $conf = $EM_CONF[$_EXTKEY];

            $packageMapping->setDescription($conf['description']);
            $packageMapping->setVersion($conf['version']);

            $trimExplode = function ($list)
            {
                return (new String($list))
                    ->split(',')
                    ->map(function (String $s) { return $s->strip(); })
                    ->filter(function (String $s) { return $s->length() > 0; })
                    ->map(function (String $s) { return $s->toPrimitive(); });
            };

            /** @var ArrayList $authors */
            $authors      = $trimExplode($conf['author']);
            $authorEmails = $trimExplode($conf['author_email']);

            for ($i = 0; $i < $authors->length(); $i++)
            {
                $packageMapping->addAuthor($authors[$i], isset($authorEmails[$i]) ? $authorEmails[$i] : NULL);
            }
        }
    }
}
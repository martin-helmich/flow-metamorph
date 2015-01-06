<?php
namespace Mw\Metamorph\Domain\Event;


use Mw\Metamorph\Domain\Model\MorphConfiguration;
use TYPO3\Flow\Package\PackageInterface;


abstract class AbstractTargetPackageEvent
{



    /**
     * @var PackageInterface
     */
    protected $package;


    /**
     * @var MorphConfiguration
     */
    protected $morphConfiguration;



    public function __construct(MorphConfiguration $configuration, PackageInterface $package)
    {
        $this->morphConfiguration = $configuration;
        $this->package            = $package;
    }



    /**
     * @return PackageInterface
     */
    public function getPackage()
    {
        return $this->package;
    }



    /**
     * @return MorphConfiguration
     */
    public function getMorphConfiguration()
    {
        return $this->morphConfiguration;
    }



}
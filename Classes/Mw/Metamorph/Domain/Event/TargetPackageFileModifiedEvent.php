<?php
namespace Mw\Metamorph\Domain\Event;


use Mw\Metamorph\Domain\Model\MorphConfiguration;
use TYPO3\Flow\Package\PackageInterface;


class TargetPackageFileModifiedEvent extends AbstractTargetPackageEvent
{



    protected $relativeFilenames = [];


    protected $purpose;



    public function __construct(
        MorphConfiguration $configuration,
        PackageInterface $package,
        $relativeFilenames,
        $purpose
    ) {
        parent::__construct($configuration, $package);

        $this->relativeFilenames = $relativeFilenames;
        $this->purpose           = $purpose;
    }



    /**
     * @return mixed
     */
    public function getPurpose()
    {
        return $this->purpose;
    }



    /**
     * @return array
     */
    public function getRelativeFilenames()
    {
        return $this->relativeFilenames;
    }



}
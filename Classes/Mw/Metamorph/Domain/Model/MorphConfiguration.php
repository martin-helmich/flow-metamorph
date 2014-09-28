<?php
namespace Mw\Metamorph\Domain\Model;


use Mw\Metamorph\Domain\Model\Extension\AllMatcher;
use Mw\Metamorph\Domain\Model\Extension\ExtensionMatcher;
use Mw\Metamorph\Domain\Model\State\ClassMappingContainer;
use Mw\Metamorph\Domain\Model\State\PackageMappingContainer;


class MorphConfiguration
{



    const TABLE_STRUCTURE_KEEP = 'KEEP';
    const TABLE_STRUCTURE_MIGRATE = 'MIGRATE';

    const PIBASE_REFACTOR_CONSERVATIVE = 'CONSERVATIVE';
    const PIBASE_REFACTOR_PROGRESSIVE = 'PROGRESSIVE';


    /**
     * @var string
     */
    protected $name;


    /**
     * @var string
     */
    protected $sourceDirectory;


    /**
     * @var \Mw\Metamorph\Domain\Model\Extension\ExtensionMatcher
     */
    protected $extensionMatcher;


    /**
     * What to do with existing database structures.
     * @var string
     */
    protected $tableStructureMode = self::TABLE_STRUCTURE_KEEP;


    /**
     * How aggressively to refactor piBase extensions.
     * @var string
     */
    protected $pibaseRefactoringMode = self::PIBASE_REFACTOR_CONSERVATIVE;


    /**
     * @var ClassMappingContainer
     */
    protected $classMappingContainer = NULL;


    /**
     * @var PackageMappingContainer
     */
    protected $packageMappingContainer = NULL;



    public function __construct($name, $sourceDirectory)
    {
        $this->name             = $name;
        $this->sourceDirectory  = $sourceDirectory;
        $this->extensionMatcher = new AllMatcher();

        $this->classMappingContainer   = new ClassMappingContainer();
        $this->packageMappingContainer = new PackageMappingContainer();
    }



    /**
     * @return string
     */
    public function getSourceDirectory()
    {
        return $this->sourceDirectory;
    }



    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }



    /**
     * @param ExtensionMatcher $extensionMatcher
     */
    public function setExtensionMatcher(ExtensionMatcher $extensionMatcher)
    {
        $this->extensionMatcher = $extensionMatcher;
    }



    /**
     * @return \Mw\Metamorph\Domain\Model\Extension\ExtensionMatcher
     */
    public function getExtensionMatcher()
    {
        return $this->extensionMatcher;
    }



    /**
     * @param string $pibaseRefactoringMode
     */
    public function setPibaseRefactoringMode($pibaseRefactoringMode)
    {
        $this->pibaseRefactoringMode = $pibaseRefactoringMode;
    }



    /**
     * @return string
     */
    public function getPibaseRefactoringMode()
    {
        return $this->pibaseRefactoringMode;
    }



    /**
     * @param string $tableStructureMode
     */
    public function setTableStructureMode($tableStructureMode)
    {
        $this->tableStructureMode = $tableStructureMode;
    }



    /**
     * @return string
     */
    public function getTableStructureMode()
    {
        return $this->tableStructureMode;
    }



    /**
     * @return ClassMappingContainer
     */
    public function getClassMappingContainer()
    {
        return $this->classMappingContainer;
    }



    /**
     * @return PackageMappingContainer
     */
    public function getPackageMappingContainer()
    {
        return $this->packageMappingContainer;
    }



} 
<?php
namespace Mw\Metamorph\Step;

use Mw\Metamorph\Domain\Model\Definition\ClassDefinitionContainer;
use Mw\Metamorph\Domain\Model\MorphConfiguration;
use Mw\Metamorph\Domain\Model\State\ClassMapping;
use Mw\Metamorph\Domain\Model\State\ClassMappingContainer;
use Mw\Metamorph\Domain\Service\MorphExecutionState;
use Mw\Metamorph\Parser\ParserInterface;
use Mw\Metamorph\Step\Analyzer\AnalyzerVisitor;
use Mw\Metamorph\Transformation\AbstractTransformation;
use Mw\Metamorph\Transformation\Progressible;
use Mw\Metamorph\Transformation\ProgressibleTrait;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use TYPO3\Flow\Annotations as Flow;

class AnalyzeClasses extends AbstractTransformation implements Progressible {

	use ProgressibleTrait;

	/**
	 * @var ParserInterface
	 * @Flow\Inject
	 */
	protected $parser;

	/**
	 * @var ClassDefinitionContainer
	 * @Flow\Inject
	 */
	protected $classDefinitionContainer;

	public function execute(MorphConfiguration $configuration, MorphExecutionState $state) {
		$classMappingContainer = $configuration->getClassMappingContainer();
		$classMappings = $classMappingContainer->getClassMappingsByFilter(function(ClassMapping $c) {
			return $c->getAction() === ClassMapping::ACTION_MORPH;
		});

		$this->startProgress('Analyzing classes', count($classMappings));
		foreach ($classMappings as $classMapping) {
			$this->analyzeClass($classMapping, $classMappingContainer);
			$this->advanceProgress();
		}
		$this->finishProgress();
	}

	private function analyzeClass(ClassMapping $mapping, ClassMappingContainer $container) {
		$stmts = $this->parser->parseFile($mapping->getTargetFile());

		$traverser = new NodeTraverser();
		$traverser->addVisitor(new NameResolver());
		$traverser->addVisitor(new AnalyzerVisitor($container));

		$traverser->traverse($stmts);
	}

}

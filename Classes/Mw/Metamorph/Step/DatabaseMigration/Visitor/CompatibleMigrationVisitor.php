<?php
namespace Mw\Metamorph\Step\DatabaseMigration\Visitor;

use Helmich\Scalars\Types\String;
use Mw\Metamorph\Step\Task\Builder\AddImportToClassTaskBuilder;
use Mw\Metamorph\Step\Task\Builder\AddPropertyToClassTaskBuilder;
use Mw\Metamorph\Transformation\Helper\Annotation\AnnotationRenderer;
use PhpParser\Node;

/**
 * Database migration visitor that adds Doctrine annotations that allow you
 * to re-use your old database scheme.
 *
 * @package    Mw\Metamorph
 * @subpackage Transformation\DatabaseMigration\Visitor
 */
class CompatibleMigrationVisitor extends AbstractMigrationVisitor {

	public function leaveNode(Node $node) {
		if ($node instanceof Node\Stmt\Class_) {
			$this->addUidPropertyToClass($node);
			$this->addTableAnnotationToClass($node);

			$this->currentClass = NULL;
			$this->currentTca   = NULL;
			$this->currentTable = NULL;
		} elseif ($node instanceof Node\Stmt\Property && $this->currentTable !== NULL) {
			if (NULL === $this->currentTca) {
				return NULL;
			}

			$newProperties = [];

			$comment       = $this->getOrCreateNodeDocComment($node);
			$commentString = new String($comment->getText());

			foreach ($node->props as $propertyNode) {
				$propertyName = new String($propertyNode->name);
				$columnName   = $this->mappingHelper->propertyToColumnName($propertyName);

				if (!isset($this->currentTca['columns']["$columnName"])) {
					return NULL;
				}

				$propertyConfig = $this->currentTca['columns']["$columnName"]['config'];
				$annotation     = $this->getAnnotationRendererForPropertyConfiguration($propertyConfig);

				$this->addColumnAnnotationIfNecessary($propertyConfig, $columnName, $comment);

				if (NULL !== $annotation) {
					if ($this->isTcaColumnManyToOneRelation(
							$propertyConfig
						) && isset($propertyConfig['foreign_table'])
					) {
						$foreignTable = $propertyConfig['foreign_table'];
						$inverse      = NULL;

						foreach ((array)$this->tca[$foreignTable]['columns'] as $foreignColumnName => $config) {
							if (
								isset($config['config']['foreign_field']) &&
								$config['config']['foreign_field'] == $columnName &&
								$config['config']['foreign_table'] == $this->currentTable
							) {
								$inverse = $foreignColumnName;
								break;
							}
						}

						if (NULL !== $inverse) {
							$annotation->addParameter('inversedBy', $inverse);
						}
					}

					if ($this->isTcaColumnOneToManyRelation(
							$propertyConfig
						) && isset($propertyConfig['foreign_field'])
					) {
						$foreignPropertyName = $this->mappingHelper->columnNameToProperty(
							new String($propertyConfig['foreign_field'])
						);
						$annotation->addParameter('mappedBy', "$foreignPropertyName");

						$this->introduceInversePropertyIfNecessary(
							$propertyName,
							$propertyConfig,
							$foreignPropertyName
						);
					}

					if ($this->isTcaColumnManyToManyRelation($propertyConfig)) {
						$this->configureJoinTableIfNecessary($propertyConfig, $comment);
					}

					if ($commentString->regexMatch('/@cascade\s+remove/')) {
						$this->commentHelper->removeAnnotationFromDocComment($comment, '@cascade');
						$annotation->addParameter('cascade', ['remove']);
					}

					$this->commentHelper->addAnnotationToDocComment($comment, $annotation);
					$this->taskQueue->enqueue(
						(new AddImportToClassTaskBuilder())
							->setTargetClassName($this->currentClass->getFullyQualifiedName())
							->setImport('Doctrine\\ORM\\Mapping')
							->setNamespaceAlias('ORM')
							->buildTask()
					);
				}

				$innerProperty   = clone $propertyNode;
				$newProperties[] = new Node\Stmt\Property($node->type, [$innerProperty], $node->getAttributes());
			}

			return $newProperties;
		}

		return NULL;
	}

	/**
	 * @param Node\Stmt\Class_ $classNode
	 */
	protected function addUidPropertyToClass(Node\Stmt\Class_ $classNode) {
		if (TRUE == $this->currentClass->getFact('isEntity')) {
			$targetClassName = $classNode->namespacedName->toString();
			$this->taskQueue->enqueue(
				(new AddPropertyToClassTaskBuilder())
					->setTargetClassName($targetClassName)
					->setPropertyName('uid')
					->setPropertyType('int')
					->setProtected()
					->addAnnotation(new AnnotationRenderer('Flow', 'Identity'))
					->addAnnotation(new AnnotationRenderer('ORM', 'GeneratedValue'))
					->addAnnotation(new AnnotationRenderer('ORM', 'Id'))
					->buildTask()
			);

			$this->taskQueue->enqueue(
				(new AddImportToClassTaskBuilder())
					->setTargetClassName($targetClassName)
					->setImport('TYPO3\\Flow\\Annotations')
					->setNamespaceAlias('Flow')
					->buildTask()
			);

			$this->taskQueue->enqueue(
				(new AddImportToClassTaskBuilder())
					->setTargetClassName($targetClassName)
					->setImport('Doctrine\\ORM\\Mapping')
					->setNamespaceAlias('ORM')
					->buildTask()
			);
		}
	}

	/**
	 * @param Node\Stmt\Class_ $node
	 */
	protected function addTableAnnotationToClass(Node\Stmt\Class_ $node) {
		if (NULL !== $this->currentTable && FALSE === $node->isAbstract()) {
			$tableAnnotation = new AnnotationRenderer('ORM', 'Table');
			$tableAnnotation->addParameter('name', $this->currentTable);

			$comment = $this->getOrCreateNodeDocComment($node);
			$this->commentHelper->addAnnotationToDocComment($comment, $tableAnnotation);
		}
	}

	/**
	 * @param $propertyConfig
	 * @param $comment
	 * @return mixed
	 */
	protected function configureJoinTableIfNecessary($propertyConfig, $comment) {
		if (isset($propertyConfig['MM'])) {
			$buildJoinColumnAnnotation = function ($name) {
				return
					(new AnnotationRenderer('ORM', 'JoinColumn'))
						->addParameter('name', $name)
						->addParameter('referencedColumnName', 'uid');
			};

			$joinTableAnnotation = new AnnotationRenderer('ORM', 'JoinTable');
			$joinTableAnnotation->addParameter('name', $propertyConfig['MM']);
			$joinTableAnnotation->addParameter('joinColumns', [$buildJoinColumnAnnotation('uid_local')]);
			$joinTableAnnotation->addParameter('inverseJoinColumns', [$buildJoinColumnAnnotation('uid_foreign')]);

			$this->commentHelper->addAnnotationToDocComment($comment, $joinTableAnnotation);
		}
	}

	/**
	 * @param $propertyConfig
	 * @param $columnName
	 * @param $comment
	 */
	private function addColumnAnnotationIfNecessary($propertyConfig, $columnName, $comment) {
		if (!$this->isTcaColumnOneToManyRelation($propertyConfig)) {
			$columnAnnotation = new AnnotationRenderer('ORM', 'Column');
			$columnAnnotation->addParameter('name', $columnName);

			$this->commentHelper->addAnnotationToDocComment($comment, $columnAnnotation);
		}
	}

}
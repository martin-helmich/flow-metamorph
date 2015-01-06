<?php
namespace Mw\Metamorph\Tests\Transformation\ClassNameConversion;

use Mw\Metamorph\Transformation\ClassNameConversion\GenericConversionStrategy;
use TYPO3\Flow\Tests\UnitTestCase;

class GenericConversionStrategyTest extends UnitTestCase {

	/** @var GenericConversionStrategy */
	private $strategy;

	public function setUp() {
		$this->strategy = new GenericConversionStrategy();
	}

	/**
	 * @dataProvider getClassNamesAndConversions
	 */
	public function testClassNamesAreConvertedCorrectly($className, $fileName, $expected) {
		$this->assertEquals(
			$expected,
			$this->strategy->convertClassName('Mw\\Example', $className, $fileName, 'he_example')
		);

	}

	public function getClassNamesAndConversions() {
		return [
			['tx_heexample_user', 'lib/class.tx_heexample_user.php', 'Mw\\Example\\User'],
			['foo_user', 'lib/class.foo_user.php', 'Mw\\Example\\Foo\\User'],
			['Foo\\User', 'Classes/Foo/User.php', 'Mw\\Example\\Foo\\User'],
		];
	}

}
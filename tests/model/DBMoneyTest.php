<?php


use SilverStripe\ORM\FieldType\DBMoney;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;


/**
 * Partially based on Zend_CurrencyTest.
 *
 * @copyright  Copyright (c) 2006 Zend Technologies USA Inc. (http://www.zend.com)
 * @license	http://framework.zend.com/license/new-bsd	 New BSD License
 * @version	$Id: CurrencyTest.php 14644 2009-04-04 18:59:08Z thomas $
 */

/**
 * @package framework
 * @subpackage tests
 */
class DBMoneyTest extends SapphireTest {

	protected static $fixture_file = 'MoneyTest.yml';

	protected $extraDataObjects = array(
		'MoneyTest_DataObject',
		'MoneyTest_SubClass',
	);

	public function testMoneyFieldsReturnedAsObjects() {
		$obj = $this->objFromFixture('MoneyTest_DataObject', 'test1');
		$this->assertInstanceOf('SilverStripe\\ORM\\FieldType\\DBMoney', $obj->MyMoney);
	}


	public function testLoadFromFixture() {
		$obj = $this->objFromFixture('MoneyTest_DataObject', 'test1');

		$this->assertInstanceOf('SilverStripe\\ORM\\FieldType\\DBMoney', $obj->MyMoney);
		$this->assertEquals($obj->MyMoney->getCurrency(), 'EUR');
		$this->assertEquals($obj->MyMoney->getAmount(), 1.23);
	}

	public function testDataObjectChangedFields() {
		$obj = $this->objFromFixture('MoneyTest_DataObject', 'test1');

		// Without changes
		$curr = $obj->obj('MyMoney');
		$changed = $obj->getChangedFields();
		$this->assertNotContains('MyMoney', array_keys($changed));

		// With changes
		$this->assertInstanceOf('SilverStripe\\ORM\\FieldType\\DBMoney', $obj->MyMoney);
		$obj->MyMoney->setAmount(99);
		$changed = $obj->getChangedFields();
		$this->assertContains('MyMoney', array_keys($changed), 'Field is detected as changed');
		$this->assertEquals(2, $changed['MyMoney']['level'], 'Correct change level');
	}

	public function testCanOverwriteSettersWithNull() {
		$obj = new MoneyTest_DataObject();

		$m1 = new DBMoney();
		$m1->setAmount(987.65);
		$m1->setCurrency('USD');
		$obj->MyMoney = $m1;
		$obj->write();

		$m2 = new DBMoney();
		$m2->setAmount(null);
		$m2->setCurrency(null);
		$obj->MyMoney = $m2;
		$obj->write();

		$moneyTest = DataObject::get_by_id('MoneyTest_DataObject',$obj->ID);
		$this->assertTrue($moneyTest instanceof MoneyTest_DataObject);
		$this->assertEquals('', $moneyTest->MyMoneyCurrency);
		$this->assertEquals(0.0000, $moneyTest->MyMoneyAmount);
	}

	public function testIsChanged() {
		$obj1 = $this->objFromFixture('MoneyTest_DataObject', 'test1');
		$this->assertFalse($obj1->isChanged());
		$this->assertFalse($obj1->isChanged('MyMoney'));

		// modify non-db field
		$m1 = new DBMoney();
		$m1->setAmount(500);
		$m1->setCurrency('NZD');
		$obj1->NonDBMoneyField = $m1;
		$this->assertFalse($obj1->isChanged()); // Because only detects DB fields
		$this->assertTrue($obj1->isChanged('NonDBMoneyField')); // Allow change detection to non-db fields explicitly named

		// Modify db field
		$obj2 = $this->objFromFixture('MoneyTest_DataObject', 'test2');
		$m2 = new DBMoney();
		$m2->setAmount(500);
		$m2->setCurrency('NZD');
		$obj2->MyMoney = $m2;
		$this->assertTrue($obj2->isChanged()); // Detects change to DB field
		$this->assertTrue($obj2->ischanged('MyMoney'));

		// Modify sub-fields
		$obj3 = $this->objFromFixture('MoneyTest_DataObject', 'test3');
		$obj3->MyMoneyCurrency = 'USD';
		$this->assertTrue($obj3->isChanged()); // Detects change to DB field
		$this->assertTrue($obj3->ischanged('MyMoneyCurrency'));
	}

	/**
	 * Write a Money object to the database, then re-read it to ensure it
	 * is re-read properly.
	 */
	public function testGettingWrittenDataObject() {
		$local = i18n::get_locale();
		//make sure that the $ amount is not prefixed by US$, as it would be in non-US locale
		i18n::set_locale('en_US');

		$obj = new MoneyTest_DataObject();

		$m = new DBMoney();
		$m->setAmount(987.65);
		$m->setCurrency('USD');
		$obj->MyMoney = $m;
		$this->assertEquals("$987.65", $obj->MyMoney->Nice(),
			"Money field not added to data object properly when read prior to first writing the record."
		);

		$objID = $obj->write();

		$moneyTest = DataObject::get_by_id('MoneyTest_DataObject',$objID);
		$this->assertTrue($moneyTest instanceof MoneyTest_DataObject);
		$this->assertEquals('USD', $moneyTest->MyMoneyCurrency);
		$this->assertEquals(987.65, $moneyTest->MyMoneyAmount);
		$this->assertEquals("$987.65", $moneyTest->MyMoney->Nice(),
			"Money field not added to data object properly when read."
		);

		i18n::set_locale($local);
	}

	public function testToCurrency() {
		$USD = new DBMoney();
		$USD->setLocale('en_US');
		$USD->setAmount(53292.18);
		$this->assertSame('$53,292.18', $USD->Nice());
		$this->assertSame('$ 53.292,18', $USD->Nice(array('format' => 'de_AT')));
	}

	public function testGetSign() {
		$SKR = new DBMoney();
		$SKR->setValue(array(
			'Currency' => 'SKR',
			'Amount' => 3.44
		));

		$this->assertSame('€',	$SKR->getSymbol('EUR','de_AT'));
		$this->assertSame(null,	$SKR->getSymbol());

		try {
			$SKR->getSymbol('EGP', 'de_XX');
			$this->setExpectedException("Exception");
		} catch(Exception $e) {
		}

		$EUR = new DBMoney();
		$EUR->setValue(array(
			'Currency' => 'EUR',
			'Amount' => 3.44
		));
		$EUR->setLocale('de_DE');
		$this->assertSame('€',	$EUR->getSymbol());
	}

	public function testGetName()
	{
		$m = new DBMoney();
		$m->setValue(array(
			'Currency' => 'EUR',
			'Amount' => 3.44
		));
		$m->setLocale('ar_EG');

		$this->assertSame('Estnische Krone', $m->getCurrencyName('EEK','de_AT'));
		$this->assertSame('يورو', $m->getCurrencyName());

		try {
			$m->getCurrencyName('EGP', 'xy_XY');
			$this->setExpectedException("Exception");
		} catch(Exception $e) {
		}
	}

	public function testGetShortName() {
		$m = new DBMoney();
		$m->setValue(array(
			'Currency' => 'EUR',
			'Amount' => 3.44
		));
		$m->setLocale('de_AT');

		$this->assertSame('EUR', $m->getShortName('Euro',	 'de_AT'));
		$this->assertSame('USD', $m->getShortName('US-Dollar','de_AT'));
		//$this->assertSame('EUR', $m->getShortName(null, 'de_AT'));
		$this->assertSame('EUR', $m->getShortName());

		try {
			$m->getShortName('EUR', 'xy_ZT');
			$this->setExpectedException("Exception");
		} catch(Exception $e) {
		}
	}

	public function testSetValueAsArray() {
		$m = new DBMoney();
		$m->setValue(array(
			'Currency' => 'EUR',
			'Amount' => 3.44
		));
		$this->assertEquals(
			$m->getCurrency(),
			'EUR'
		);
		$this->assertEquals(
			$m->getAmount(),
			3.44
		);
	}

	public function testSetValueAsMoney() {
		$m1 = new DBMoney();
		$m1->setValue(array(
			'Currency' => 'EUR',
			'Amount' => 3.44
		));
		$m2 = new DBMoney();
		$m2->setValue($m1);
		$this->assertEquals(
			$m2->getCurrency(),
			'EUR'
		);
		$this->assertEquals(
			$m2->getAmount(),
			3.44
		);
	}

	public function testExists() {
		$m1 = new DBMoney();
		$this->assertFalse($m1->exists());

		$m2 = new DBMoney();
		$m2->setValue(array(
			'Currency' => 'EUR',
			'Amount' => 3.44
		));
		$this->assertTrue($m2->exists());

		$m3 = new DBMoney();
		$m3->setValue(array(
			'Currency' => 'EUR',
			'Amount' => 0
		));
		$this->assertTrue($m3->exists());
	}

	public function testLoadIntoDataObject() {
		$obj = new MoneyTest_DataObject();

		$this->assertInstanceOf('SilverStripe\\ORM\\FieldType\\DBMoney', $obj->obj('MyMoney'));

		$m = new DBMoney();
		$m->setValue(array(
			'Currency' => 'EUR',
			'Amount' => 1.23
		));
		$obj->MyMoney = $m;

		$this->assertEquals($obj->MyMoney->getCurrency(), 'EUR');
		$this->assertEquals($obj->MyMoney->getAmount(), 1.23);
	}

	public function testWriteToDataObject() {
		$obj = new MoneyTest_DataObject();
		$m = new DBMoney();
		$m->setValue(array(
			'Currency' => 'EUR',
			'Amount' => 1.23
		));
		$obj->MyMoney = $m;
		$obj->write();

		$this->assertEquals(
			'EUR',
			DB::query(sprintf(
				'SELECT "MyMoneyCurrency" FROM "MoneyTest_DataObject" WHERE "ID" = %d',
				$obj->ID
			))->value()
		);
		$this->assertEquals(
			'1.23',
			DB::query(sprintf(
				'SELECT "MyMoneyAmount" FROM "MoneyTest_DataObject" WHERE "ID" = %d',
				$obj->ID
			))->value()
		);
	}

	public function testMoneyLazyLoading() {
		// Get the object, ensuring that MyOtherMoney will be lazy loaded
		$id = $this->idFromFixture('MoneyTest_SubClass', 'test2');
		$obj = MoneyTest_DataObject::get()->byID($id);

		$this->assertEquals('£2.46', $obj->obj('MyOtherMoney')->Nice());
	}

	public function testHasAmount() {
		$obj = new MoneyTest_DataObject();
		$m = new DBMoney();
		$obj->MyMoney = $m;

		$m->setValue(array('Amount' => 1));
		$this->assertTrue($obj->MyMoney->hasAmount());

		$m->setValue(array('Amount' => 1.00));
		$this->assertTrue($obj->MyMoney->hasAmount());

		$m->setValue(array('Amount' => 1.01));
		$this->assertTrue($obj->MyMoney->hasAmount());

		$m->setValue(array('Amount' => 0.99));
		$this->assertTrue($obj->MyMoney->hasAmount());

		$m->setValue(array('Amount' => 0.01));
		$this->assertTrue($obj->MyMoney->hasAmount());

		$m->setValue(array('Amount' => 0));
		$this->assertFalse($obj->MyMoney->hasAmount());

		$m->setValue(array('Amount' => 0.0));
		$this->assertFalse($obj->MyMoney->hasAmount());

		$m->setValue(array('Amount' => 0.00));
		$this->assertFalse($obj->MyMoney->hasAmount());
	}

}

/**
 * @package framework
 * @subpackage tests
 */
class MoneyTest_DataObject extends DataObject implements TestOnly {
	private static $db = array(
		'MyMoney' => 'Money',
		//'MyOtherMoney' => 'Money',
	);
}

/**
 * @package framework
 * @subpackage tests
 */
class MoneyTest_SubClass extends MoneyTest_DataObject implements TestOnly {

	private static $db = array(
		'MyOtherMoney' => 'Money',
	);

}

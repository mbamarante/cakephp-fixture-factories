<?php
declare(strict_types=1);

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) 2020 Juan Pablo Ramirez and Nicolas Masson
 * @link          https://webrider.de/
 * @since         1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace CakephpFixtureFactories\Test\TestCase\ORM\Locator;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use CakephpFixtureFactories\ORM\TableRegistry\FactoryTableRegistry;
use CakephpFixtureFactories\Test\Factory\ArticleFactory;
use CakephpFixtureFactories\Test\Factory\CountryFactory;
use PHPUnit\Framework\TestCase;
use TestApp\Model\Entity\Country;
use TestApp\Model\Table\AddressesTable;
use TestApp\Model\Table\ArticlesTable;
use TestApp\Model\Table\AuthorsTable;
use TestApp\Model\Table\CitiesTable;
use TestApp\Model\Table\CountriesTable;
use TestPlugin\Model\Table\BillsTable;
use TestPlugin\Model\Table\CustomersTable;

class FactoryTableLocatorTest extends TestCase
{

    public function tables()
    {
        return [
            ['Articles', ArticlesTable::class],
            ['Authors', AuthorsTable::class],
            ['Addresses', AddressesTable::class],
            ['Cities', CitiesTable::class],
            ['Countries', CountriesTable::class],
            ['TestPlugin.Bills', BillsTable::class],
            ['TestPlugin.Customers', CustomersTable::class],
        ];
    }

    /**
     * @dataProvider tables
     */
    public function testReturnedTableShouldHaveSameAssociations(string $tableName, string $table)
    {
        $FactoryTable = FactoryTableRegistry::getTableLocator()->get($tableName);
        $Table = TableRegistry::getTableLocator()->get($tableName);

        $this->assertSame(true, $FactoryTable instanceof $table);
        $this->assertSame(true, $Table instanceof $table);
        $this->assertNotSame(FactoryTableRegistry::getTableLocator(), TableRegistry::getTableLocator());
        $this->assertSame($FactoryTable->getEntityClass(), $Table->getEntityClass());

        $this->assertNotSame($FactoryTable->associations(), $Table->associations());
        foreach ($Table->associations() as $association) {
            $this->assertSame(true, $FactoryTable->hasAssociation($association->getName()));
        }

        // EntitiesTable from factory table locator should not have a Timestamp behavior.
        $this->assertSame(true, $FactoryTable->hasBehavior('Timestamp'));
        // EntitiesTable from application table locator should have a Timestamp behavior
        $this->assertSame(true, $Table->hasBehavior('Timestamp'));
    }


    /**
     * The max length of a country name being set in CountriesTable
     * this test verifies that the validation is triggered on regular marschalling/saving
     * , but is ignored by the factories
     */
    public function testSkipValidation()
    {
        $maxLength = CountriesTable::NAME_MAX_LENGTH;
        $validator = new Validator();
        $validator->maxLength('name', $maxLength);

        $name = str_repeat('a', $maxLength + 1);

        $countriesTable = TableRegistry::getTableLocator()->get('Countries');

        $country = $countriesTable->newEntity(compact('name'));
        $this->assertTrue($country->hasErrors());
        $this->assertFalse($countriesTable->save($country));

        $country = CountryFactory::make(compact('name'))->getEntity();
        $this->assertFalse($country->hasErrors());
        $country = CountryFactory::make(compact('name'))->persist();
        $this->assertInstanceOf(Country::class, $country);

        $country = CountryFactory::makeWithModelEvents(compact('name'))->getEntity();
        $this->assertFalse($country->hasErrors());
        $country = CountryFactory::makeWithModelEvents(compact('name'))->persist();
        $this->assertInstanceOf(Country::class, $country);
    }

    public function runSeveralTimes()
    {
        return [
            [true], [false], [false], [false],
        ];
    }

    /**
     * @dataProvider runSeveralTimes
     * @param $applyEvent Bind the event once to the model
     * @throws \Exception
     */
    public function testApplyOrIgnoreBeforeMarshal($applyEvent)
    {
        $name = 'Foo';
        $eventApplied = true;
        $countriesTable = TableRegistry::getTableLocator()->get('Countries');

        if ($applyEvent) {
            $countriesTable->getEventManager()->on('Model.beforeMarshal', function (Event $event, \ArrayObject $entity) use ($eventApplied) {
                $entity['eventApplied'] = $eventApplied;
            });
        }
        
        // Event should be skipped
        $country = CountryFactory::make(compact('name'))->getEntity();
        $this->assertSame(null, $country->eventApplied);

        // Event should apply
        $country = $countriesTable->newEntity(compact('name'));
        $this->assertSame($eventApplied, $country->eventApplied);

        $country = CountryFactory::makeWithModelEvents(compact('name'))->getEntity();
        $this->assertSame($eventApplied, $country->eventApplied);
    }

    /**
     * @dataProvider runSeveralTimes
     * @param $times
     * @throws \Exception
     */
    public function testApplyOrIgnoreEventInBehaviors(bool $times)
    {
        $title = "This Article";

        $article = ArticleFactory::make(compact('title'))->persist();
        $this->assertEquals(null, $article->slug);

        $article = ArticleFactory::makeWithModelEvents(compact('title'))->persist();
        $this->assertEquals('This-Article', $article->slug);

        $article = ArticleFactory::make(compact('title'))->persist();
        $this->assertEquals(null, $article->slug);

        $article = ArticleFactory::makeWithModelEvents(compact('title'))->persist();
        $this->assertEquals('This-Article', $article->slug);
    }
}
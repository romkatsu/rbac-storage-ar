<?php

declare(strict_types=1);

namespace Yiisoft\Rbac\Storage\ActiveRecord\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Yiisoft\ActiveRecord\BaseActiveRecord;
use Yiisoft\Cache\CacheInterface;
use Yiisoft\Cache\NullCache;
use Yiisoft\Db\Connection\Connection;
use Yiisoft\Db\Connection\ConnectionPool;
use Yiisoft\Db\Helper\Dsn;
use Yiisoft\Profiler\Profiler;
use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Rbac\Storage;
use Yiisoft\Rbac\Storage\ActiveRecord\ActiveRecordStorage;
use Yiisoft\Rbac\Storage\ActiveRecord\ItemModel;
use Yiisoft\Rbac\Storage\ActiveRecord\ItemParentModel;
use Yiisoft\Rbac\Storage\ActiveRecord\RuleModel;
use Yiisoft\Rbac\Storage\ActiveRecord\AssignmentModel;

/**
 * Class ActiveRecordStorageTest
 * @package Yiisoft\Rbac\Storage\ActiveRecord\Tests
 */
final class ActiveRecordStorageTest extends TestCase
{
    private const CONNECTION_PARAMS = ['mysql', 'yii-mysql', 'yiitest', '3306'];

    /**
     * @test
     */
    public function clearItems(): void
    {
        $this->createStorage()->clear();

        $this->assertEmpty(ItemModel::find()->all());
        $this->assertEmpty(AssignmentModel::find()->all());
        $this->assertEmpty(ItemParentModel::find()->all());
        $this->assertEmpty(RuleModel::find()->all());
    }

    /**
     * @test
     */
    public function getItems(): void
    {
        $storage = $this->createStorage();
        $items = $storage->getItems();
        $this->assertCount(9, $storage->getItems());
        foreach ($items as $item) {
            $this->assertInstanceOf(Item::class, $item);
        }
    }

    /**
     * @test
     */
    public function getItemByName(): void
    {
        $storage = $this->createStorage();

        $this->assertInstanceOf(Item::class, $storage->getItemByName('createPost'));
        $this->assertNull($storage->getItemByName('nonExistName'));
    }

    /**
     * @test
     */
    public function addPermissionItem(): void
    {
        $storage = $this->createStorage();
        $item = new Permission('testAddedPermission');
        $storage->addItem($item);

        $this->assertCount(10, $storage->getItems());
        $this->seeInDatabase(
            ItemModel::class,
            [
                'name' => 'testAddedPermission',
                'type' => 'permission'
            ]
        );
    }

    /**
     * @test
     */
    public function addRoleItem(): void
    {
        $storage = $this->createStorage();
        $item = new Role('testAddedRole');
        $storage->addItem($item);

        $this->assertCount(10, $storage->getItems());
        $this->seeInDatabase(
            ItemModel::class,
            [
                'name' => 'testAddedRole',
                'type' => 'role'
            ]
        );
    }

    /**
     * @test
     */
    public function updateItem(): void
    {
        $storage = $this->createStorage();
        $storage->updateItem('reader', $storage->getItemByName('reader')->withName('new reader'));

        $this->seeInDatabase(
            ItemModel::class,
            [
                'name' => 'new reader',
                'type' => 'role'
            ]
        );

        $this->dontSeeInDatabase(
            ItemModel::class,
            [
                'name' => 'reader',
            ]
        );
    }

    /**
     * @test
     */
    public function removeItem(): void
    {
        $storage = $this->createStorage();
        $storage->removeItem($storage->getItemByName('reader'));

        $this->dontSeeInDatabase(
            ItemModel::class,
            [
                'name' => 'reader'
            ]
        );
    }

    /**
     * @test
     */
    public function getChildren(): void
    {
        $children = $this->createStorage()->getChildren();
        $this->assertCount(3, $children);

        $this->assertEquals(['readPost'], array_keys($children['reader']));
        $this->assertEquals(
            [
                'createPost',
                'updatePost',
                'reader'
            ],
            array_keys($children['author'])
        );
        $this->assertEquals(
            [
                'author',
                'updateAnyPost'
            ],
            array_keys($children['admin'])
        );
    }

    /**
     * @test
     */
    public function getRoles(): void
    {
        $roles = $this->createStorage()->getRoles();
        $this->assertCount(4, $roles);
    }

    /**
     * @test
     */
    public function getRoleByName(): void
    {
        $storage = $this->createStorage();

        $this->assertNotNull($storage->getRoleByName('author'));
        $this->assertNull($storage->getRoleByName('nonExistRole'));
    }

    /**
     * @test
     */
    public function getPermissions(): void
    {
        $storage = $this->createStorage();
        $this->assertCount(5, $storage->getPermissions());
    }

    /**
     * @test
     */
    public function getPermissionByName(): void
    {
        $storage = $this->createStorage();

        $this->assertNotNull($storage->getPermissionByName('updatePost'));
        $this->assertNull($storage->getPermissionByName('nonExistPermission'));
    }

    /**
     * @test
     */
    public function getChildrenByName(): void
    {
        $storage = $this->createStorage();
        $this->assertEquals(
            [
                'createPost',
                'updatePost',
                'reader'
            ],
            array_keys($storage->getChildrenByName('author'))
        );
        $this->assertEmpty($storage->getChildrenByName('itemNotExist'));
    }

    /**
     * @test
     */
    public function hasChildren(): void
    {
        $storage = $this->createStorage();
        $this->assertTrue($storage->hasChildren('reader'));
        $this->assertFalse($storage->hasChildren('withoutChildren'));
        $this->assertFalse($storage->hasChildren('nonExistChildren'));
    }

    /**
     * @test
     */
    public function addChild(): void
    {
        $storage = $this->createStorage();
        $role = $storage->getRoleByName('reader');
        $permission = $storage->getPermissionByName('createPost');

        $storage->addChild($role, $permission);
        $this->assertEquals(
            [
                'readPost',
                'createPost'
            ],
            array_keys($storage->getChildrenByName('reader'))
        );
    }

    /**
     * @test
     */
    public function removeChild(): void
    {
        $storage = $this->createStorage();
        $role = $storage->getRoleByName('reader');
        $permission = $storage->getPermissionByName('readPost');

        $storage->removeChild($role, $permission);
        $this->assertEmpty($storage->getChildrenByName('reader'));
        $this->dontSeeInDatabase(
            ItemParentModel::class,
            [
                'item_id' => 2,
                'parent_id' => 7
            ]
        );
    }

    /**
     * @test
     */
    public function removeChildren(): void
    {
        $storage = $this->createStorage();
        $role = $storage->getRoleByName('reader');

        $storage->removeChildren($role);
        $this->assertEmpty($storage->getChildrenByName('reader'));
    }

    /**
     * @test
     */
    public function getAssignments(): void
    {
        $storage = $this->createStorage();
        $this->assertEquals(
            [
                'reader A',
                'author B',
                'admin C'
            ],
            array_keys($storage->getAssignments())
        );
    }

    /**
     * @test
     */
    public function getUserAssignments(): void
    {
        $storage = $this->createStorage();
        $this->assertEquals(
            [
                'author',
                'deletePost'
            ],
            array_keys($storage->getUserAssignments('author B'))
        );
        $this->assertEmpty($storage->getUserAssignments('unknown user'));
    }

    /**
     * @test
     */
    public function getUserAssignmentByName(): void
    {
        $storage = $this->createStorage();
        $this->assertInstanceOf(
            Assignment::class,
            $storage->getUserAssignmentByName('author B', 'author')
        );

        $this->assertNull($storage->getUserAssignmentByName('author B', 'nonExistAssigment'));
    }

    /**
     * @test
     */
    public function addAssignment(): void
    {
        $storage = $this->createStorage();
        $role = $storage->getRoleByName('author');

        $storage->addAssignment('reader A', $role);
        $this->assertEquals(
            [
                'reader',
                'author'
            ],
            array_keys($storage->getUserAssignments('reader A'))
        );
    }

    /**
     * @test
     */
    public function assignmentExist(): void
    {
        $storage = $this->createStorage();

        $this->assertTrue($storage->assignmentExist('deletePost'));
        $this->assertFalse($storage->assignmentExist('nonExistAssignment'));
    }

    /**
     * @test
     */
    public function removeAssignment(): void
    {
        $storage = $this->createStorage();
        $storage->removeAssignment('author B', $storage->getItemByName('deletePost'));
        $this->assertEquals(['author'], array_keys($storage->getUserAssignments('author B')));
    }

    /**
     * @test
     */
    public function removeAllAssignments(): void
    {
        $storage = $this->createStorage();
        $storage->removeAllAssignments('author B');
        $this->assertEmpty($storage->getUserAssignments('author B'));
        $this->assertNotEmpty($storage->getUserAssignments('reader A'));
    }

    /**
     * @test
     */
    public function clearAssignments(): void
    {
        $storage = $this->createStorage();
        $storage->clearAssignments();
        $this->assertCount(0, $this->createStorage()->getAssignments());
    }

    /**
     * @test
     */
    public function getRules(): void
    {
        $storage = $this->createStorage();
        $this->assertEquals(['isAuthor'], array_keys($storage->getRules()));
    }

    /**
     * @test
     */
    public function addRule(): void
    {
        $storage = $this->createStorage();
        $storage->addRule(new EasyRule());
        $this->assertEquals(
            [
                'isAuthor',
                EasyRule::class
            ],
            array_keys($storage->getRules())
        );
    }

    /**
     * @test
     */
    public function getRuleByName(): void
    {
        $storage = $this->createStorage();

        $this->assertInstanceOf(AuthorRule::class, $storage->getRuleByName('isAuthor'));
        $this->assertNull($storage->getRuleByName('nonExistRule'));
    }


    /**
     * @test
     */
    public function removeRule(): void
    {
        $storage = $this->createStorage();
        $storage->removeRule('isAuthor');
        $this->assertEmpty($storage->getRules());

        $this->dontSeeInDatabase(
            RuleModel::class,
            [
                'name' => 'isAuthor'
            ]
        );
    }

    public function testClearRules(): void
    {
        $storage = $this->createStorage();
        $storage->clearRules();

        $storage = $this->createStorage();
        $this->assertCount(0, $storage->getRules());
    }

    protected function setUp(): void
    {
        $this->loadDb();
        $this->loadFixtures();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->clearTables();
        parent::tearDown();
    }

    private function createStorage(): Storage
    {
        return new ActiveRecordStorage();
    }

    private function loadDb(): void
    {
        ConnectionPool::setConnectionsPool('default', $this->createConnection());
        BaseActiveRecord::connectionId('default');
    }

    private function createCache(): CacheInterface
    {
        return new NullCache();
    }

    private function createDsn(): Dsn
    {
        return new Dsn(...self::CONNECTION_PARAMS);
    }

    private function createConnection(): Connection
    {
        $logger = new TestLogger();
        $profiler = new Profiler($logger);
        $db = new Connection($this->createCache(), $logger, $profiler, $this->createDsn()->getDsn());
        $db->setUsername('root');
        $db->setPassword('root');

        return $db;
    }

    /**
     * @return Connection
     */
    private function getConnection(): Connection
    {
        return ConnectionPool::getConnectionPool('default');
    }

    private function seeInDatabase(string $model, array $conditions): void
    {
        $this->assertNotNull($model::findOne($conditions), 'model dont see in database');
    }

    private function dontSeeInDatabase(string $model, array $conditions): void
    {
        $this->assertNull($model::findOne($conditions), 'model see in database');
    }

    private function loadFixtures(): void
    {
        $fixtures = require __DIR__ . '/fixtures.php';
        foreach ($fixtures as $table => $values) {
            foreach ($values as $value) {
                $this->getConnection()->getSchema()->insert($table, $value);
            }
        }
    }

    private function clearTables(): void
    {
        $fixtures = require __DIR__ . '/fixtures.php';
        foreach ($fixtures as $table => $values) {
            $this->getConnection()->createCommand("TRUNCATE TABLE $table")->execute();
        }
    }
}

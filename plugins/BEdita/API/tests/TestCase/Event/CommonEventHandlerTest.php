<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2017 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\API\Test\TestCase\Event;

use BEdita\API\Event\CommonEventHandler;
use BEdita\Core\Utility\LoggedUser;
use Cake\Controller\Controller;
use Cake\Database\Driver\Mysql;
use Cake\Datasource\ConnectionManager;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\MiddlewareQueue;
use Cake\Http\ServerRequest;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\UnauthorizedException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

/**
 * @coversDefaultClass \BEdita\API\Event\CommonEventHandler
 */
class CommonEventHandlerTest extends TestCase
{

    /**
     * Connection name to be used for encoding check tests.
     */
    const CONNECTION_NAME = 'encoding_test';

    /**
     * Database fixtures.
     *
     * @var string[]
     */
    public $fixtures = [
        'plugin.BEdita/Core.fake_animals'
    ];

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        parent::tearDown();

        if (in_array(static::CONNECTION_NAME, ConnectionManager::configured())) {
            ConnectionManager::drop(static::CONNECTION_NAME);
        }
        ConnectionManager::alias('test', 'default');
    }

    /**
     * Test implemented events
     * @covers ::implementedEvents()
     */
    public function testImplementedEvents()
    {
        static::assertCount(0, EventManager::instance()->listeners('Model.beforeSave'));
        static::assertCount(0, EventManager::instance()->listeners('Model.beforeDelete'));
        static::assertCount(0, EventManager::instance()->listeners('Server.buildMiddleware'));

        EventManager::instance()->on(new CommonEventHandler());
        static::assertCount(1, EventManager::instance()->listeners('Model.beforeSave'));
        static::assertCount(1, EventManager::instance()->listeners('Model.beforeDelete'));
        static::assertCount(1, EventManager::instance()->listeners('Server.buildMiddleware'));
    }

    /**
     * test build middleware stack
     *
     * @return void
     * @covers ::buildMiddlewareStack()
     */
    public function testBuildMiddlewareStack()
    {
        EventManager::instance()->on(new CommonEventHandler());

        $middleware = new MiddlewareQueue();
        static::assertCount(0, $middleware);

        $middleware->add(new ErrorHandlerMiddleware());
        static::assertCount(1, $middleware);

        $event = new Event('Server.buildMiddleware', null, ['middleware' => $middleware]);
        EventManager::instance()->dispatch($event);
        static::assertCount(2, $middleware);
        static::assertInstanceOf(ErrorHandlerMiddleware::class, $middleware->get(0));
        static::assertInstanceOf('\BEdita\API\Middleware\CorsMiddleware', $middleware->get(1));
    }

    /**
     * Data Provider for testCheckAuthorized
     *
     * @return array
     */
    public function checkAuthorizedProvider()
    {
        $fakeAnimals = TableRegistry::get('FakeAnimals');
        $pluginWhitelistfakeAnimals = TableRegistry::get('WhitelistFakeAnimals', [
            'table' => 'fale_animals'
        ]);
        $pluginWhitelistfakeAnimals->setRegistryAlias('DebugKit.WhitelistFakeAnimals');

        return [
            'beforeSaveOk' => [
                true,
                new Event('Model.beforeSave'),
                true,
            ],
            'beforeSaveOkSubject' => [
                true,
                new Event('Model.beforeSave', $fakeAnimals),
                true,
            ],
            'beforeSaveOkSubjectWhitelist' => [
                true,
                new Event('Model.beforeSave', $pluginWhitelistfakeAnimals),
                false,
            ],
            'beforeSaveError' => [
                new UnauthorizedException('User not authorized'),
                new Event('Model.beforeSave'),
                false,
            ],
            'beforeSaveErrorSubject' => [
                new UnauthorizedException('User not authorized'),
                new Event('Model.beforeSave', $fakeAnimals),
                false,
            ],
            'beforeDeleteOk' => [
                true,
                new Event('Model.beforeDelete'),
                true,
            ],
            'beforeDeleteOkSubject' => [
                true,
                new Event('Model.beforeDelete', $fakeAnimals),
                true,
            ],
            'beforeDeleteOkSubjectWhitelist' => [
                true,
                new Event('Model.beforeDelete', $pluginWhitelistfakeAnimals),
                false,
            ],
            'beforeDeleteError' => [
                new UnauthorizedException('User not authorized'),
                new Event('Model.beforeDelete'),
                false,
            ],
            'beforeDeleteErrorSubject' => [
                new UnauthorizedException('User not authorized'),
                new Event('Model.beforeDelete', $fakeAnimals),
                false,
            ],
        ];
    }

    /**
     * Test check authorized
     *
     * @param \Exception|bool $expected Expected result.
     * @param \Cake\Event\Event $event Event to be dispatched.
     * @param bool $userLogged Is the user logged in?
     * @return void
     *
     * @dataProvider checkAuthorizedProvider
     * @covers ::checkAuthorized()
     */
    public function testCheckAuthorized($expected, $event, $userLogged)
    {
        EventManager::instance()->on(new CommonEventHandler());
        if ($expected instanceof \Exception) {
            $this->expectException(UnauthorizedException::class);
            $this->expectExceptionMessage($expected->getMessage());
        }

        if ($userLogged) {
            LoggedUser::setUser(['id' => 1]);
        } else {
            LoggedUser::resetUser();
        }

        EventManager::instance()->dispatch($event);

        static::assertTrue($expected);
    }

    /**
     * test after identify
     *
     * @return void
     * @covers ::afterIdentify()
     */
    public function testAfterIdentify()
    {
        LoggedUser::resetUser();
        EventManager::instance()->on(new CommonEventHandler());

        static::assertEquals([], LoggedUser::getUser());
        $event = new Event('Auth.afterIdentify', null, ['user' => ['id' => 1]]);
        EventManager::instance()->dispatch($event);
        static::assertEquals(['id' => 1], LoggedUser::getUser());
    }

    /**
     * Data provider for `testCheckEncoding` test case.
     *
     * @return array
     */
    public function checkEncodingProvider()
    {
        /* @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test', false);
        $isMysql = $connection->getDriver() instanceof Mysql;

        return [
            'empty' => [
                true,
                null,
                null,
            ],
            'utf8? no problem!' => [
                true,
                [
                    'field1' => 123,
                    'field2' => 'ASCII string',
                    'field3' => 'Questa è una stringa UTF-8 su 3 bytes!',
                ],
                'utf8',
            ],
            'utf8' => [
                $isMysql ? new BadRequestException('4-byte encoded UTF-8 characters are not supported') : true,
                [
                    'field1' => 123,
                    'field2' => 'ASCII string',
                    'field3' => 'Questa è una stringa UTF-8 su 3 bytes!',
                    'field4' => '🍀-bytes encoded UTF-8️⃣ string',
                ],
                'utf8',
            ],
            'utf8mb4' => [
                true,
                [
                    'field1' => 123,
                    'field2' => 'ASCII string',
                    'field3' => 'Questa è una stringa UTF-8 su 3 bytes!',
                    'field4' => '🍀-bytes encoded UTF-8️⃣ string',
                ],
                'utf8mb4',
            ],
        ];
    }

    /**
     * Test encoding check.
     *
     * @param \Exception|true $expected Expected result.
     * @param mixed $data Request data.
     * @param string $encoding Database encoding.
     * @return void
     *
     * @dataProvider checkEncodingProvider()
     * @covers ::checkEncoding()
     */
    public function testCheckEncoding($expected, $data, $encoding)
    {
        if ($expected instanceof \Exception) {
            static::expectException(get_class($expected));
            static::expectExceptionMessage($expected->getMessage());
            static::expectExceptionCode($expected->getCode());
        }

        // Set up fake connection.
        ConnectionManager::setConfig(
            static::CONNECTION_NAME,
            compact('encoding') + ConnectionManager::getConfig('test')
        );
        ConnectionManager::alias(static::CONNECTION_NAME, 'default');

        // Set up event.
        $event = new Event(
            'Controller.startup',
            new Controller(new ServerRequest(['post' => $data]))
        );

        EventManager::instance()->on(new CommonEventHandler());
        EventManager::instance()->dispatch($event);

        static::assertTrue($expected);
    }
}

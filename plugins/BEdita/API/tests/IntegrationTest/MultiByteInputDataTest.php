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

namespace BEdita\API\Test\IntegrationTest;

use BEdita\API\TestSuite\IntegrationTestCase;
use Cake\Database\Driver\Mysql;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;

/**
 * Test against requests containing multi-byte characters.
 */
class MultiByteInputDataTest extends IntegrationTestCase
{

    /**
     * Connection name to be used for encoding check tests.
     */
    const CONNECTION_NAME = 'encoding_test';

    /**
     * {@inheritDoc}
     */
    public $fixtures = [
        'plugin.BEdita/Core.roles',
    ];

    /**
     * Roles table instance.
     *
     * @var \BEdita\Core\Model\Table\RolesTable
     */
    protected $Roles;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->Roles = TableRegistry::get('Roles');
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        parent::tearDown();

        unset($this->Roles);

        if (in_array(static::CONNECTION_NAME, ConnectionManager::configured())) {
            ConnectionManager::drop(static::CONNECTION_NAME);
        }
        ConnectionManager::alias('test', 'default');
    }

    /**
     * Data provider for `testCheckEncoding` test case.
     *
     * @return array
     */
    public function checkEncodingProvider()
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test', false);
        $isMysql = $connection->getDriver() instanceof Mysql;

        $data = [
            'utf8? no problem!' => [
                true,
                [
                    'name' => 'Role with plain ASCII name',
                ],
                'utf8',
            ],
            'utf8' => [
                !$isMysql,
                [
                    'name' => 'Monkey Role 🙉',
                ],
                'utf8',
            ],
        ];

        if ($isMysql) {
            $data['utf8mb4'] = [
                true,
                [
                    'name' => 'Monkey Role 🙉',
                ],
                'utf8mb4',
            ];
        }

        return $data;
    }

    /**
     * Test encoding check.
     *
     * @param bool $expected Expected result.
     * @param mixed $attributes Attributes.
     * @param string $encoding Database encoding.
     * @return void
     *
     * @dataProvider checkEncodingProvider()
     * @covers ::checkEncoding()
     */
    public function testCheckEncoding($expected, $attributes, $encoding)
    {
        // Set up fake connection.
        ConnectionManager::setConfig(
            static::CONNECTION_NAME,
            compact('encoding') + ConnectionManager::getConfig('test')
        );
        ConnectionManager::alias(static::CONNECTION_NAME, 'default');

        // Find last inserted role.
        $lastId = $this->Roles->find()
            ->order(['id' => 'DESC'])
            ->firstOrFail()
            ->id;

        // Configure request.
        $data = compact('attributes') + ['type' => 'roles'];
        $this->configRequestHeaders('POST', $this->getUserAuthHeader());
        $this->post('/roles', json_encode(compact('data')));

        if ($expected === true) {
            $this->assertResponseCode(201);
            $this->assertContentType('application/vnd.api+json');
            $this->assertResponseNotEmpty();
            $body = json_decode((string)$this->_response->getBody(), true);
            static::assertArrayHasKey('data', $body);
            static::assertArrayHasKey('attributes', $body['data']);
            static::assertArrayHasKey('name', $body['data']['attributes']);
            static::assertArraySubset($attributes, $body['data']['attributes']);

            $role = $this->Roles->get($lastId + 1);
            $properties = $role->extract(array_keys($attributes));
            static::assertEquals($attributes, $properties);
        } else {
            $this->assertResponseCode(400);
            $this->assertContentType('application/vnd.api+json');
            $this->assertResponseNotEmpty();
            $body = json_decode((string)$this->_response->getBody(), true);
            static::assertArrayHasKey('error', $body);
            static::assertArrayHasKey('status', $body['error']);
            static::assertSame('400', $body['error']['status']);
            static::assertArrayHasKey('title', $body['error']);
            static::assertSame('4-byte encoded UTF-8 characters are not supported', $body['error']['title']);
        }
    }
}

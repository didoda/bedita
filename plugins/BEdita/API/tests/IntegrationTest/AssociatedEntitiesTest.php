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
use BEdita\Core\Filesystem\FilesystemRegistry;
use BEdita\Core\Model\Action\AddRelatedObjectsAction;
use BEdita\Core\Utility\Relations;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

/**
 * Test CRUD operations on objects with associated entities
 */
class AssociatedEntitiesTest extends IntegrationTestCase
{
    /**
     * @inheritDoc
     */
    public $fixtures = [
        'plugin.BEdita/Core.DateRanges',
        'plugin.BEdita/Core.Locations',
        'plugin.BEdita/Core.Streams',
    ];

    /**
     * Data provider for `testEventAssoc`
     */
    public function eventAssocProvider()
    {
        return [
            'moreDates' => [
                [
                    'title' => 'My Event',
                    'date_ranges' => [
                        [
                            'start_date' => '2017-04-01T00:00:00+00:00',
                        ],
                        [
                            'start_date' => '2017-03-01 12:12:12',
                            'end_date' => '2017-04-01 12:12:12',
                        ],
                    ],
                ],
                [],
            ],
            'noDates' => [
                [
                    'title' => 'My Event',
                    'date_ranges' => [],
                ],
                [
                    'title' => 'Same Event',
                ],
            ],
            'otherDates' => [
                [
                    'title' => 'New years eve',
                    'date_ranges' => [
                        [
                            'start_date' => '2017-12-31T23:59:59Z',
                            'end_date' => '2018-01-01',
                        ],
                    ],
                ],
                [
                    'title' => 'Happy new year!',
                    'date_ranges' => [
                        [
                            'start_date' => '2017-03-08T00:00:00+00:00',
                            'end_date' => '2018-01-02 10:30',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Test CRUD on Events with associated DateRanges entities
     *
     * @param $attributes array Event data to insert
     * @param $modified array Attributes to modify
     * @dataProvider eventAssocProvider
     * @coversNothing
     */
    public function testEventAssoc($attributes, $modified)
    {
        $type = 'events';
        $lastId = $this->lastObjectId();

        // ADD
        $data = [
            'type' => $type,
            'attributes' => $attributes,
        ];

        $authHeader = $this->getUserAuthHeader();

        $this->configRequestHeaders('POST', $authHeader);
        $endpoint = '/' . $type;
        $this->post($endpoint, json_encode(compact('data')));
        $this->assertResponseCode(201);
        $this->assertContentType('application/vnd.api+json');

        // VIEW
        $this->configRequestHeaders();
        $lastId++;
        $this->get("/$type/$lastId");
        $result = json_decode((string)$this->_response->getBody(), true);
        $this->assertResponseCode(200);
        $this->assertContentType('application/vnd.api+json');

        $resultDates = $result['data']['attributes']['date_ranges'];
        $expectedDates = Hash::sort($attributes['date_ranges'], '{n}.start_date', 'asc');
        static::assertEquals(count($resultDates), count($expectedDates));
        $count = count($expectedDates);
        for ($i = 0; $i < $count; $i++) {
            foreach ($expectedDates[$i] as $k => $d) {
                $found = $resultDates[$i][$k];
                $exp = new \DateTime($d);
                $exp = $exp->format('Y-m-d\TH:i:s+00:00');
                static::assertEquals($found, $exp);
            }
        }

        // EDIT
        $data = [
            'id' => "$lastId",
            'type' => $type,
            'attributes' => $modified,
        ];
        $this->configRequestHeaders('PATCH', $authHeader);
        $this->patch("/$type/$lastId", json_encode(compact('data')));
        $this->assertResponseCode(200);
        $this->assertContentType('application/vnd.api+json');

        // DELETE
        $this->configRequestHeaders('DELETE', $authHeader);
        $this->delete("/$type/$lastId");
        $this->assertResponseCode(204);
        $this->assertResponseEmpty();

        // EMPTY TRASH
        $this->configRequestHeaders('DELETE', $authHeader);
        $this->delete("/trash/$lastId");
        $this->assertResponseCode(204);
        $this->assertResponseEmpty();
    }

    /**
     * Test that deleted entities are never returned as related objects.
     *
     * @return void
     */
    public function testRelatedDeleted()
    {
        $table = TableRegistry::getTableLocator()->get('Documents');
        $entity = $table->get(3);
        $entity->set('deleted', true);
        $table->save($entity);

        $this->configRequestHeaders();
        $this->get('/documents/2/test');
        $result = json_decode((string)$this->_response->getBody(), true);
        static::assertCount(1, $result['data']);
    }

    /**
     * Test that deleted entities are never returned as included objects.
     *
     * @return void
     */
    public function testIncludedDeleted()
    {
        $table = TableRegistry::getTableLocator()->get('Documents');
        $entity = $table->get(3);
        $entity->set('deleted', true);
        $table->save($entity);

        $this->configRequestHeaders();
        $this->get('/documents/2?include=test');
        $result = json_decode((string)$this->_response->getBody(), true);
        static::assertCount(1, $result['included']);
    }

    /**
     * Test that `?include` query parameter works for `/:objectType/:id/:relationName` endpoints.
     *
     * @return void
     */
    public function testIncludedRelated()
    {
        // Create temporary relation between documents and locations, link location #8 to document #2.
        Relations::create([
            [
                'name' => 'foos',
                'inverse_name' => 'oofs',
                'left' => ['documents'],
                'right' => ['locations'],
            ],
        ]);
        $table = $this->getTableLocator()->get('Documents');
        $association = $table->getAssociation('Foos');
        $location = $association->get(8);

        $action = new AddRelatedObjectsAction(compact('association'));
        $action->execute([
            'entity' => $table->get(2),
            'relatedEntities' => [$location],
        ]);

        // Send request.
        $this->configRequestHeaders();
        $this->get('/documents/3/inverse_test?include=foos');
        $result = json_decode((string)$this->_response->getBody(), true);

        $this->assertResponseCode(200);
        static::assertCount(1, $result['included']);
        static::assertSame('8', $result['included'][0]['id']);
        static::assertArrayHasKey('coords', $result['included'][0]['attributes']);
    }

    /**
     * Test that related objects correspond to the pagination count.
     *
     * @return void
     */
    public function testRelated(): void
    {
        $this->configRequestHeaders();
        $this->get('/profiles/4/inverse_test');
        $result = json_decode((string)$this->_response->getBody(), true);
        static::assertCount(2, $result['data']);
        static::assertEquals(2, $result['meta']['pagination']['count']);
    }

    /**
     * Test that `?include` query parameter for `/events/:id` will contain all relevan media data.
     *
     * @return void
     */
    public function testIncludedMedia()
    {
        FilesystemRegistry::setConfig(Configure::read('Filesystem'));
        $data = [
            [
                'id' => '14',
                'type' => 'files',
            ],
        ];
        $this->configRequestHeaders('POST', $this->getUserAuthHeader());
        $this->post('/events/9/relationships/test_abstract', json_encode(compact('data')));
        $result = json_decode((string)$this->_response->getBody(), true);
        $this->assertResponseCode(200);

        $this->configRequestHeaders();
        $this->get('/events/9?include=test_abstract');
        $result = json_decode((string)$this->_response->getBody(), true);
        static::assertCount(1, $result['included']);

        static::assertEquals('My other media name', $result['included'][0]['attributes']['name']);
        $expect = 'https://static.example.org/files/6aceb0eb-bd30-4f60-ac74-273083b921b6-bedita-logo-gray.gif';
        static::assertEquals($expect, $result['included'][0]['meta']['media_url']);

        FilesystemRegistry::dropAll();
    }
}

<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2016 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\API\Controller;

use BEdita\API\Model\Action\UpdateAssociatedAction;
use BEdita\Core\Model\Action\AddRelatedObjectsAction;
use BEdita\Core\Model\Action\DeleteObjectAction;
use BEdita\Core\Model\Action\GetObjectAction;
use BEdita\Core\Model\Action\ListObjectsAction;
use BEdita\Core\Model\Action\ListRelatedObjectsAction;
use BEdita\Core\Model\Action\RemoveAssociatedAction;
use BEdita\Core\Model\Action\SaveEntityAction;
use BEdita\Core\Model\Action\SetRelatedObjectsAction;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Network\Exception\ConflictException;
use Cake\Network\Exception\InternalErrorException;
use Cake\ORM\Query;
use Cake\ORM\TableRegistry;
use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\Router;

/**
 * Controller for `/objects` endpoint.
 *
 * @since 4.0.0
 */
class ObjectsController extends ResourcesController
{

    /**
     * {@inheritDoc}
     */
    public $modelClass = 'Objects';

    /**
     * The referred object type entity filled when `object_type` request param is set and valid
     *
     * @var \BEdita\Core\Model\Entity\ObjectType
     */
    protected $objectType = null;

    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
        if (in_array($this->request->getParam('action'), ['related', 'relationships'])) {
            $name = $this->request->getParam('relationship');
            $allowedTypes = TableRegistry::get('ObjectTypes')
                ->find('list')
                ->find('byRelation', compact('name'))
                ->toArray();

            $this->setConfig(sprintf('allowedAssociations.%s', $name), $allowedTypes);
        }

        parent::initialize();

        $type = $this->request->getParam('object_type', $this->request->getParam('controller'));
        if ($type !== false && $type !== 'objects') {
            try {
                $this->objectType = TableRegistry::get('ObjectTypes')->get($type);
                $this->modelClass = $this->objectType->alias;
                $this->Table = TableRegistry::get($this->modelClass);
            } catch (RecordNotFoundException $e) {
                $this->log(sprintf('Object type "%s" does not exist', $type), 'error', ['request' => $this->request]);

                throw new MissingRouteException(['url' => $this->request->getRequestTarget()]);
            }

            $behaviorRegistry = $this->Table->behaviors();
            if ($behaviorRegistry->hasMethod('getRelations')) {
                $relations = array_keys($behaviorRegistry->call('getRelations'));
                $this->setConfig('allowedAssociations', array_fill_keys($relations, []));
            }

            if (isset($this->JsonApi)) {
                $this->JsonApi->setConfig('resourceTypes', [$this->objectType->name]);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function index()
    {
        $this->request->allowMethod(['get', 'post']);

        if ($this->request->is('post')) {
            // Add a new entity.
            $entity = $this->Table->newEntity();
            $entity->set('type', $this->request->getData('type'));
            $action = new SaveEntityAction(['table' => $this->Table, 'objectType' => $this->objectType]);

            $data = $this->request->getData();
            $data = $action(compact('entity', 'data'));

            $action = new GetObjectAction(['table' => $this->Table]);
            $data = $action(['primaryKey' => $data->id]);

            $this->response = $this->response
                ->withStatus(201)
                ->withHeader(
                    'Location',
                    Router::url(
                        [
                            '_name' => 'api:objects:resource',
                            'object_type' => $this->objectType->name,
                            'id' => $data->id,
                        ],
                        true
                    )
                );
        } else {
            // List existing entities.
            $filter = $this->request->getQuery('filter');
            $include = $this->request->getQuery('include');
            $contain = $include ? $this->prepareInclude($include) : [];

            $action = new ListObjectsAction(['table' => $this->Table, 'objectType' => $this->objectType]);
            $query = $action(compact('filter', 'contain'));

            $data = $this->paginate($query);
        }

        $this->set(compact('data'));
        $this->set('_serialize', ['data']);
    }

    /**
     * {@inheritDoc}
     */
    public function resource($id)
    {
        $this->request->allowMethod(['get', 'patch', 'delete']);

        $include = $this->request->getQuery('include');
        $contain = $include ? $this->prepareInclude($include) : [];

        $action = new GetObjectAction(['table' => $this->Table, 'objectType' => $this->objectType]);
        $entity = $action(['primaryKey' => $id, 'contain' => $contain]);

        if ($this->request->is('delete')) {
            // Delete an entity.
            $action = new DeleteObjectAction(['table' => $this->Table]);

            if (!$action(compact('entity'))) {
                throw new InternalErrorException(__d('bedita', 'Delete failed'));
            }

            return $this->response
                ->withStatus(204);
        }

        if ($this->request->is('patch')) {
            // Patch an existing entity.
            if ($this->request->getData('id') !== $id) {
                throw new ConflictException(__d('bedita', 'IDs don\'t match'));
            }

            $action = new SaveEntityAction(['table' => $this->Table, 'objectType' => $this->objectType]);

            $data = $this->request->getData();
            $entity = $action(compact('entity', 'data'));
        }

        $this->set(compact('entity'));
        $this->set('_serialize', ['entity']);

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function related()
    {
        $this->request->allowMethod(['get']);

        $relationship = $this->request->getParam('relationship');
        $relatedId = $this->request->getParam('related_id');

        $association = $this->findAssociation($relationship);
        $filter = $this->request->getQuery('filter');

        $action = new ListRelatedObjectsAction(compact('association'));
        $query = $action(['primaryKey' => $relatedId, 'filter' => $filter]);

        $objects = $this->paginate($query);

        $this->set(compact('objects'));
        $this->set('_serialize', ['objects']);
    }

    /**
     * {@inheritDoc}
     */
    public function relationships()
    {
        $this->request->allowMethod(['get', 'post', 'patch', 'delete']);

        $id = $this->request->getParam('id');
        $relationship = $this->request->getParam('relationship');

        $association = $this->findAssociation($relationship);

        switch ($this->request->getMethod()) {
            case 'PATCH':
                $action = new SetRelatedObjectsAction(compact('association'));
                break;

            case 'POST':
                $action = new AddRelatedObjectsAction(compact('association'));
                break;

            case 'DELETE':
                $action = new RemoveAssociatedAction(compact('association'));
                break;

            case 'GET':
            default:
                $filter = $this->request->getQuery('filter');

                $action = new ListRelatedObjectsAction(compact('association'));
                $data = $action(['primaryKey' => $id, 'list' => true, 'filter' => $filter]);

                if ($data instanceof Query) {
                    $data = $this->paginate($data);
                }

                $this->set(compact('data'));
                $this->set([
                    '_serialize' => ['data'],
                ]);

                return null;
        }

        $action = new UpdateAssociatedAction(compact('action') + ['request' => $this->request]);
        $count = $action(['primaryKey' => $id]);

        if ($count === false) {
            throw new InternalErrorException(__d('bedita', 'Could not update relationship "{0}"', $relationship));
        }

        if (is_array($count)) {
            $action = new ListRelatedObjectsAction(compact('association'));
            $data = $action(['primaryKey' => $id, 'list' => true, 'only' => $count]);

            $count = count($count);
        }

        if ($count === 0) {
            return $this->response
                ->withStatus(204);
        }

        $this->set(compact('data'));
        $this->set([
            '_serialize' => isset($data) ? ['data'] : [],
        ]);

        return null;
    }
}

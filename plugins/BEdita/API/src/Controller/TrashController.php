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
namespace BEdita\API\Controller;

use BEdita\Core\Model\Action\DeleteObjectAction;
use BEdita\Core\Model\Action\SaveEntityAction;
use Cake\Network\Exception\BadRequestException;
use Cake\Network\Exception\ConflictException;
use Cake\Network\Exception\InternalErrorException;

/**
 * Controller for `/trash` endpoint.
 *
 * @since 4.0.0
 *
 * @property \BEdita\Core\Model\Table\ObjectsTable $Objects
 */
class TrashController extends AppController
{
    /**
     * {@inheritDoc}
     */
    public $modelClass = 'Objects';

    /**
     * Paginated objects list.
     *
     * @return void
     */
    public function index()
    {
        $query = $this->Objects->find('all')
            ->where(['deleted' => 1])
            ->contain(['ObjectTypes']);

        $trash = $this->paginate($query);

        $this->set(compact('trash'));
        $this->set('_serialize', ['trash']);
    }

    /**
     * Get single object data from trashcan.
     *
     * @param int $id Object ID.
     * @return void
     */
    public function view($id)
    {
        $trash = $this->Objects->get($id, [
            'contain' => ['ObjectTypes'],
            'conditions' => ['deleted' => 1]
        ]);

        $this->set(compact('trash'));
        $this->set('_serialize', ['trash']);
    }

    /**
     * Restore object, remove from trashcan
     *
     * @param int $id Object ID.
     * @return \Cake\Http\Response
     * @throws \Cake\Network\Exception\ConflictException Throws an exception if object ID in the payload doesn't match
     *      the object ID in the URL.
     * @throws \Cake\Network\Exception\InternalErrorException Throws an exception if an error occurs during restore.
     */
    public function restore($id)
    {
        $this->request->allowMethod('patch');

        if ($this->request->getData('id') != $id) {
            throw new ConflictException('IDs don\'t match');
        }

        $trash = $this->Objects->get($id, [
            'conditions' => ['deleted' => 1]
        ]);

        $trash->deleted = false;
        $action = new SaveEntityAction(['table' => $this->Objects]);
        if (!$action(['entity' => $trash, 'data' => []])) {
            throw new InternalErrorException('Unable to restore object');
        }

        return $this->response
            ->withStatus(204);
    }

    /**
     * Delete object permanently, remove from trashcan
     *
     * @param int $id Object ID.
     * @return \Cake\Http\Response
     * @throws \Cake\Network\Exception\InternalErrorException Throws an exception if an error occurs during deletion.
     */
    public function delete($id)
    {
        $this->request->allowMethod('delete');

        $trash = $this->Objects->get($id, [
            'conditions' => ['deleted' => 1]
        ]);

        $action = new DeleteObjectAction(['table' => $this->Objects]);
        if (!$action(['entity' => $trash, 'hard' => true])) {
            throw new InternalErrorException('Unable to remove object');
        }

        return $this->response
            ->withStatus(204);
    }
}

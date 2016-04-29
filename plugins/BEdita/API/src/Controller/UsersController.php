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

use Cake\ORM\TableRegistry;

/**
 * Controller for /users endpoint
 *
 * @property \BEdita\Core\Model\Table\UsersTable $Users
 */
class UsersController extends AppController
{
    /**
     * {@inheritDoc}
     */
    public function initialize()
    {
        parent::initialize();
        $this->Users = TableRegistry::get(
            'Users',
            TableRegistry::exists('Users') ? [] : ['className' => 'BEdita\Core\Model\Table\UsersTable']
        );
    }

    /**
     * Paginated users index
     *
     * @param int $id User id
     * @return void
     */
    public function index($id = null)
    {
        $multi = false;
        if (!$id) {
            $users = $this->Users->find('all');
            $multi = true;
        } else {
            $users = $this->Users->get($id);
        }
        $this->prepareResponseData($users, $multi, 'users');
    }
}
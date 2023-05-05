<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2023 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\API\Policy;

use Authorization\IdentityInterface;
use Authorization\Policy\BeforePolicyInterface;
use BEdita\Core\Model\Entity\ObjectEntity;
use BEdita\Core\Model\Table\RolesTable;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Hash;

/**
 * Object policy.
 *
 * @since 5.10.0
 */
class ObjectPolicy implements BeforePolicyInterface
{
    use LocatorAwareTrait;

    /**
     * @inheritDoc
     */
    public function before(?IdentityInterface $identity, $resource, $action)
    {
        if ($identity === null) {
            return null;
        }

        $roleIds = Hash::extract($identity->getOriginalData(), 'roles.{n}.id');
        if (in_array(RolesTable::ADMIN_ROLE, $roleIds)) {
            return true;
        }

        return null;
    }

    /**
     * Check if $user can update an object.
     *
     * @param \Authorization\IdentityInterface $user The user.
     * @param \BEdita\Core\Model\Entity\ObjectEntity $object The object entity
     * @return bool
     */
    public function canUpdate(IdentityInterface $user, ObjectEntity $object): bool
    {
        $permsRoles = Hash::extract((array)$object->perms, 'roles');
        if (empty($permsRoles)) { // no permission set
            return true;
        }

        $userRolesNames = Hash::extract($user->getOriginalData(), 'roles.{n}.name');
        if (empty($userRolesNames) && !empty($roleIds)) {
            $userRolesNames = $this->fetchTable('Roles')
                ->find('list')
                ->where(['id IN' => $roleIds])
                ->toArray();
        }

        return !empty(array_intersect($permsRoles, $userRolesNames));
    }
}

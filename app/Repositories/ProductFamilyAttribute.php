<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;
use Pim\Core\Exceptions\ProductFamilyAttributeAlreadyExists;

class ProductFamilyAttribute extends Base
{
    public function getInheritedPavsIds(string $id): array
    {
        $pfa = $this->get($id);
        if (empty($pfa) || empty($pf = $pfa->get('productFamily'))) {
            return [];
        }

        $productsIds = $pf->getLinkMultipleIdList('products');
        if (empty($productsIds)) {
            return [];
        }

        $query = "SELECT id 
                  FROM `product_attribute_value` 
                  WHERE deleted=0
                    AND product_id IN ('" . implode("','", $productsIds) . "')
                    AND attribute_id='{$pfa->get('attributeId')}'
                    AND scope='{$pfa->get('scope')}'";
        if ($pfa->get('scope') === 'Channel') {
            $query .= " AND channel_id='{$pfa->get('channelId')}'";
        }

        $attribute = $this->getEntityManager()->getEntity('Attribute', $pfa->get('attributeId'));
        if ($attribute->get('isMultilang')) {
            $query .= " AND language='{$pfa->get('language')}'";
        }

        return $this->getPDO()->query($query)->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('scope') === 'Global' || $entity->get('channelId') === null) {
            $entity->set('channelId', '');
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @param string $productFamilyId
     *
     * @return array
     */
    public function getAvailableChannelsForPavs(string $productFamilyId): array
    {
        $productFamilyId = $this->getPDO()->quote($productFamilyId);

        $sql = "SELECT p.id, pc.channel_id 
                FROM product p 
                    LEFT JOIN product_channel pc on p.id = pc.product_id AND pc.deleted = 0 
                WHERE p.product_family_id = $productFamilyId AND p.deleted = 0";

        return $this->getPDO()->query($sql)->fetchAll(\PDO::FETCH_ASSOC | \PDO::FETCH_GROUP | \PDO::FETCH_COLUMN);
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        $this->addDependency('language');
    }

    /**
     * @param string $key
     * @param string $label
     * @param string $scope
     *
     * @return string
     */
    protected function translate(string $key, string $label, string $scope = 'ProductFamilyAttribute'): string
    {
        return $this->getInjection('language')->translate($key, $label, $scope);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions');
    }
}

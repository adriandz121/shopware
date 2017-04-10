<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Tests\Unit\Bundle\StoreFrontBundle\Struct;

use PHPUnit\Framework\TestCase;
use Shopware\Bundle\StoreFrontBundle\Struct\Category;
use Shopware\Bundle\StoreFrontBundle\Struct\CategoryCollection;

class CategoryCollectionTest extends TestCase
{
    public function testGetTreeWithEmptyCategories()
    {
        $collection = new CategoryCollection([]);

        $this->assertSame([], $collection->getTree(null));
    }

    public function testGetTreeWithOneLevel()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [1], 'First level 01'),
            Category::create(2, null, [1], 'First level 02'),
            Category::create(3, null, [1], 'First level 03'),
        ]);
        $this->assertEquals(
            [
                Category::create(1, null, [1], 'First level 01'),
                Category::create(2, null, [1], 'First level 02'),
                Category::create(3, null, [1], 'First level 03'),
            ],
            $collection->getTree(null)
        );
    }

    public function testGetTreeWithNoneExistingParentId()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [1], 'First level 01'),
            Category::create(2, null, [1], 'First level 02'),
            Category::create(3, null, [1], 'First level 03'),
        ]);

        $this->assertSame([], $collection->getTree(100));
    }

    public function testGetNestedTree()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [], 'First level 01'),
            Category::create(2, 1, [1], 'Second level 01'),
            Category::create(3, 2, [2, 1], 'Third level 01'),
            Category::create(4, 1, [1], 'Second level 02'),
            Category::create(5, 4, [4, 1], 'Third level 02'),
        ]);

        $this->assertEquals(
            [
                Category::create(1, null, [], 'First level 01', ['children' => [
                    Category::create(2, 1, [1], 'Second level 01', ['children' => [
                        Category::create(3, 2, [2, 1], 'Third level 01'),
                    ]]),
                    Category::create(4, 1, [1], 'Second level 02', ['children' => [
                        Category::create(5, 4, [4, 1], 'Third level 02'),
                    ]]),
                ]]),
            ],
            $collection->getTree(null)
        );
    }

    public function testGetTreeRemovesElementsWithoutParent()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [], 'First level 01'),
            Category::create(2, 1, [1], 'Second level 01'),
            Category::create(3, 2, [2, 1], 'Third level 01'),
            Category::create(4, 1, [1], 'Second level 02'),
            Category::create(5, 6, [6, 1], 'Third level 02'),
        ]);

        $this->assertEquals(
            [
                Category::create(1, null, [], 'First level 01', ['children' => [
                    Category::create(2, 1, [1], 'Second level 01', ['children' => [
                        Category::create(3, 2, [2, 1], 'Third level 01'),
                    ]]),
                    Category::create(4, 1, [1], 'Second level 02'),
                ]]),
            ],
            $collection->getTree(null)
        );
    }

    public function testGetNestedTreeWithSubParent()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [], 'First level 01'),
            Category::create(2, 1, [1], 'Second level 01'),
            Category::create(3, 2, [2, 1], 'Third level 01'),

            Category::create(4, 1, [1], 'Second level 02'),
            Category::create(5, 4, [4, 1], 'Third level 02'),
        ]);

        $this->assertEquals(
            [
                Category::create(2, 1, [1], 'Second level 01', ['children' => [
                    Category::create(3, 2, [2, 1], 'Third level 01'),
                ]]),
                Category::create(4, 1, [1], 'Second level 02', ['children' => [
                    Category::create(5, 4, [4, 1], 'Third level 02'),
                ]]),
            ],
            $collection->getTree(1)
        );
    }

    public function testGetIds()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [], 'First level 01'),
            Category::create(2, 1, [1], 'Second level 01'),
            Category::create(3, 2, [2, 1], 'Third level 01'),

            Category::create(4, 1, [1], 'Second level 02'),
            Category::create(5, 4, [4, 1], 'Third level 02'),
        ]);

        $this->assertSame(
            [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5],
            $collection->getIds()
        );
    }

    public function testGetPaths()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [], 'First level 01'),
            Category::create(2, 1, [1], 'Second level 01'),
            Category::create(3, 2, [2, 1], 'Third level 01'),
            Category::create(4, 1, [1], 'Second level 02'),
            Category::create(5, 4, [4, 1], 'Third level 02'),
        ]);

        $this->assertSame(
            [
                1 => [],
                2 => [1],
                3 => [2, 1],
                4 => [1],
                5 => [4, 1],
            ],
            $collection->getPaths()
        );
    }

    public function testGetIdsWithPath()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [], 'First level 01'),
            Category::create(2, 1, [1], 'Second level 01'),
            Category::create(3, 2, [2, 1], 'Third level 01'),
            Category::create(4, 1, [1], 'Second level 02'),
            Category::create(5, 50, [50, 1], 'Third level 02'),
        ]);

        $this->assertSame(
            [1, 2, 3, 4, 5, 50],
            $collection->getIdsIncludingPaths()
        );
    }

    public function testGetByKey()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [], 'First level 01'),
            Category::create(2, 1, [1], 'Second level 01'),
        ]);

        $this->assertEquals(
            Category::create(1, null, [], 'First level 01'),
            $collection->get(1)
        );
    }

    public function testGetWithNoneExistingKey()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [], 'First level 01'),
            Category::create(2, 1, [1], 'Second level 01'),
        ]);

        $this->assertSame(
            null,
            $collection->get(10)
        );
    }

    public function testGetById()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [], 'First level 01'),
            Category::create(2, 1, [1], 'Second level 01'),
        ]);

        $this->assertEquals(
            Category::create(2, 1, [1], 'Second level 01'),
            $collection->get(2)
        );
    }

    public function testGetByIdWithNoneExisting()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [], 'First level 01'),
            Category::create(2, 1, [1], 'Second level 01'),
        ]);

        $this->assertEquals(
            null,
            $collection->get(5)
        );
    }

    public function testAddCategory()
    {
        $collection = new CategoryCollection([
            Category::create(1, null, [], 'First level 01'),
        ]);

        $collection->add(Category::create(2, 1, [1], 'Second level 01'));

        $this->assertEquals(
            new CategoryCollection([
                Category::create(1, null, [], 'First level 01'),
                Category::create(2, 1, [1], 'Second level 01'),
            ]),
            $collection
        );
    }
}
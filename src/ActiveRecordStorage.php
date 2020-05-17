<?php

declare(strict_types=1);

namespace Yiisoft\Rbac\Storage\ActiveRecord;

use Yiisoft\Rbac\Assignment;
use Yiisoft\Rbac\Item;
use Yiisoft\Rbac\Permission;
use Yiisoft\Rbac\Role;
use Yiisoft\Rbac\Rule;
use Yiisoft\Rbac\Storage;

/**
 * Class ActiveRecordStorage
 * @package Yiisoft\Rbac\Storage\ActiveRecord
 */
final class ActiveRecordStorage implements Storage
{
    public function clear(): void
    {
        ItemModel::deleteAll();
    }

    public function getItems(): array
    {
        return $this->createItemsFromArray(
            ItemModel::find()->all()
        );
    }

    public function getItemByName(string $name): ?Item
    {
        $item = ItemModel::findOne(['name' => $name]);
        if ($item === null) {
            return null;
        }

        return $this->createItemFromArray($item->getAttributes());
    }

    public function addItem(Item $item): void
    {
        $model = new ItemModel();
        $model->setAttribute('name', $item->getName());
        $model->setAttribute('type', $item->getType());
        $model->setAttribute('description', $item->getDescription());
        $model->save();
    }

    public function updateItem(string $name, Item $item): void
    {
        $model = ItemModel::findOne(['name' => $name]);
        if ($model === null) {
            throw new \InvalidArgumentException(sprintf('Item "%s" no found.', $item->getName()));
        }

        $model->setAttribute('name', $item->getName());
        $model->setAttribute('type', $item->getType());
        $model->setAttribute('description', $item->getDescription());
        $model->save();
    }

    public function removeItem(Item $item): void
    {
        $model = ItemModel::findOne(['name' => $item->getName()]);
        if ($model === null) {
            throw new \InvalidArgumentException(sprintf('Item "%s" no found.', $item->getName()));
        }

        $model->delete();
    }

    public function getChildren(): array
    {
        $result = [];
        foreach (ItemParentModel::find()->all() as $item) {
            $children = ItemModel::findOne($item->getAttribute('item_id'));
            $parent = ItemModel::findOne($item->getAttribute('parent_id'));
            $result[$parent->getAttribute('name')][$children->getAttribute('name')] = $this->createItemFromArray(
                $children->getAttributes()
            );
        }

        return $result;
    }

    public function getRoles(): array
    {
        return $this->createItemsFromArray(
            ItemModel::find()->where(['type' => Item::TYPE_ROLE])->all()
        );
    }

    public function getRoleByName(string $name): ?Role
    {
        $model = ItemModel::findOne(['name' => $name, 'type' => Item::TYPE_ROLE]);

        if ($model === null) {
            return null;
        }

        return $this->createItemFromArray($model->getAttributes());
    }

    public function clearRoles(): void
    {
        ItemModel::deleteAll(
            [
                'type' => Item::TYPE_ROLE
            ]
        );
    }

    public function getPermissions(): array
    {
        return $this->createItemsFromArray(
            ItemModel::find()->where(['type' => Item::TYPE_PERMISSION])->all()
        );
    }

    public function getPermissionByName(string $name): ?Permission
    {
        $model = ItemModel::findOne(['name' => $name, 'type' => Item::TYPE_PERMISSION]);

        if ($model === null) {
            return null;
        }

        return $this->createItemFromArray($model->getAttributes());
    }

    public function clearPermissions(): void
    {
        ItemModel::deleteAll(
            [
                'type' => Item::TYPE_PERMISSION
            ]
        );
    }

    public function getChildrenByName(string $name): array
    {
        $result = [];
        $parent = ItemModel::findOne(['name' => $name]);
        if ($parent === null) {
            return [];
        }

        $children = ItemParentModel::find()->where(['parent_id' => $parent->getAttribute('id')])->all();

        foreach ($children as $child) {
            $childItem = ItemModel::findOne($child->getAttribute('item_id'));
            $result[$childItem->getAttribute('name')] = $this->createItemFromArray($childItem->getAttributes());
        }

        return $result;
    }

    public function hasChildren(string $name): bool
    {
        $parent = ItemModel::findOne(['name' => $name]);
        if ($parent === null) {
            return false;
        }

        return ItemParentModel::find()->where(['parent_id' => $parent->getAttribute('id')])->exists();
    }

    public function addChild(Item $parent, Item $child): void
    {
        $parentModel = ItemModel::findOne(['name' => $parent->getName()]);
        $childModel = ItemModel::findOne(['name' => $child->getName()]);

        $model = new ItemParentModel();
        $model->setAttribute('item_id', $childModel->getAttribute('id'));
        $model->setAttribute('parent_id', $parentModel->getAttribute('id'));
        $model->save();
    }

    public function removeChild(Item $parent, Item $child): void
    {
        $parentModel = ItemModel::findOne(['name' => $parent->getName()]);
        $childModel = ItemModel::findOne(['name' => $child->getName()]);

        ItemParentModel::findOne(
            [
                'item_id' => $childModel->getAttribute('id'),
                'parent_id' => $parentModel->getAttribute('id'),
            ]
        )->delete();
    }

    public function removeChildren(Item $parent): void
    {
        $parentModel = ItemModel::findOne(['name' => $parent->getName()]);
        ItemParentModel::deleteAll(['parent_id' => $parentModel->getAttribute('id')]);
    }

    public function getAssignments(): array
    {
        $result = [];
        $assignments = AssignmentModel::find()->all();
        foreach ($assignments as $assignment) {
            $role = ItemModel::findOne(['id' => $assignment->getAttribute('item_id')]);
            $userId = $assignment->getAttribute('user_id');
            $roleName = $role->getAttribute('name');
            $result[$userId][$roleName] = new Assignment($userId, $roleName, time());
        }

        return $result;
    }

    public function getUserAssignments(string $userId): array
    {
        $result = [];
        $assignments = AssignmentModel::find()->where(['user_id' => $userId])->all();
        foreach ($assignments as $assignment) {
            $roleName = ItemModel::findOne(['id' => $assignment->getAttribute('item_id')])->getAttribute('name');
            $result[$roleName] = new Assignment($userId, $roleName, time());
        }

        return $result;
    }

    public function getUserAssignmentByName(string $userId, string $name): ?Assignment
    {
        return $this->getUserAssignments($userId)[$name] ?? null;
    }

    public function addAssignment(string $userId, Item $item): void
    {
        $itemModel = ItemModel::findOne(['name' => $item->getName()]);
        $model = new AssignmentModel();
        $model->setAttribute('user_id', $userId);
        $model->setAttribute('item_id', $itemModel->getAttribute('id'));
        $model->save();
    }

    public function assignmentExist(string $name): bool
    {
        return AssignmentModel::find()
            ->join('LEFT JOIN', 'item', 'item.id = assignment.item_id')
            ->where(['item.name' => $name])
            ->exists();
    }

    public function removeAssignment(string $userId, Item $item): void
    {
        $itemModel = ItemModel::findOne(['name' => $item->getName()]);
        AssignmentModel::findOne(['user_id' => $userId, 'item_id' => $itemModel->getAttribute('id')])->delete();
    }

    public function removeAllAssignments(string $userId): void
    {
        AssignmentModel::deleteAll(['user_id' => $userId]);
    }

    public function clearAssignments(): void
    {
        AssignmentModel::deleteAll();
    }

    public function getRules(): array
    {
        $result = [];
        foreach (RuleModel::find()->all() as $rule) {
            $result[$rule->getAttribute('name')] = $this->unserializeRule($rule->getAttribute('implementation'));
        }

        return $result;
    }

    public function getRuleByName(string $name): ?Rule
    {
        $rule = RuleModel::findOne(['name' => $name]);

        if ($rule === null) {
            return null;
        }

        return $this->unserializeRule($rule->getAttribute('implementation'));
    }

    public function removeRule(string $name): void
    {
        RuleModel::findOne(['name' => $name])->delete();
    }

    public function addRule(Rule $rule): void
    {
        $model = new RuleModel();
        $model->setAttribute('name', $rule->getName());
        $model->setAttribute('implementation', serialize($rule));
        $model->save();
    }

    public function clearRules(): void
    {
        // TODO: Implement clearRules() method.
    }

    private function createItemsFromArray(array $items): array
    {
        return array_map(
            fn(ItemModel $model): Item => $this->createItemFromArray($model->getAttributes()),
            $items
        );
    }

    /**
     * @param array $attributes
     * @return Item|Role|Permission
     */
    private function createItemFromArray(array $attributes): Item
    {
        $instance = $this->getInstanceByTypeAndName($attributes['type'], $attributes['name']);
        $instance->withDescription($attributes['description'] ?? '');

        return $instance;
    }

    private function getInstanceByTypeAndName(string $type, string $name): Item
    {
        return $type === Item::TYPE_PERMISSION ? new Permission($name) : new Role($name);
    }

    private function unserializeRule(string $data): Rule
    {
        return unserialize($data, ['allowed_classes' => true]);
    }
}

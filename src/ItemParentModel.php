<?php

declare(strict_types=1);

namespace Yiisoft\Rbac\Storage\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;


/**
 * Class ItemParentModel
 * @package Yiisoft\Rbac\Storage\ActiveRecord
 */
final class ItemParentModel extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'item_parent';
    }
}

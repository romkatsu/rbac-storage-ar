<?php

declare(strict_types=1);

namespace Yiisoft\Rbac\Storage\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class AssignmentModel
 * @package Yiisoft\Rbac\Storage\ActiveRecord
 */
final class AssignmentModel extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'assignment';
    }
}

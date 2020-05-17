<?php

declare(strict_types=1);

namespace Yiisoft\Rbac\Storage\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class RuleModel
 * @package Yiisoft\Rbac\Storage\ActiveRecord
 */
final class RuleModel extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return 'rule';
    }
}

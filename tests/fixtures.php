<?php

return [
    'item' => [
        [
            'id' => 1,
            'name' => 'createPost',
            'description' => 'create a post',
            'type' => 'permission',
        ],
        [
            'id' => 2,
            'name' => 'readPost',
            'description' => 'read a post',
            'type' => 'permission',
        ],
        [
            'id' => 3,
            'name' => 'deletePost',
            'description' => 'delete a post',
            'type' => 'permission',
        ],
        [
            'id' => 4,
            'name' => 'updatePost',
            'description' => 'update a post',
            'type' => 'permission',
        ],
        [
            'id' => 5,
            'name' => 'updateAnyPost',
            'description' => 'update any post',
            'type' => 'permission',
        ],
        [
            'id' => 6,
            'name' => 'withoutChildren',
            'type' => 'role',
        ],
        [
            'id' => 7,
            'name' => 'reader',
            'type' => 'role',
        ],
        [
            'id' => 8,
            'name' => 'author',
            'type' => 'role',
        ],
        [
            'id' => 9,
            'name' => 'admin',
            'type' => 'role',
        ],
    ],
    'item_parent' => [
        [
            'id' => 1,
            'item_id' => 2,
            'parent_id' => 7
        ],
        [
            'id' => 2,
            'item_id' => 1,
            'parent_id' => 8
        ],
        [
            'id' => 3,
            'item_id' => 4,
            'parent_id' => 8
        ],
        [
            'id' => 4,
            'item_id' => 7,
            'parent_id' => 8
        ],
        [
            'id' => 5,
            'item_id' => 8,
            'parent_id' => 9
        ],
        [
            'id' => 6,
            'item_id' => 5,
            'parent_id' => 9
        ]
    ],
    'assignment' => [
        [
            'id' => 1,
            'user_id' => 'reader A',
            'item_id' => 7
        ],
        [
            'id' => 2,
            'user_id' => 'author B',
            'item_id' => 8
        ],
        [
            'id' => 3,
            'user_id' => 'author B',
            'item_id' => 3
        ],
        [
            'id' => 4,
            'user_id' => 'admin C',
            'item_id' => 9
        ]
    ],
    'rule' => [
        [
            'id' => 1,
            'name' => 'isAuthor',
            'implementation' => 'O:50:"Yiisoft\Rbac\Storage\ActiveRecord\Tests\AuthorRule":2:{s:64:" Yiisoft\Rbac\Storage\ActiveRecord\Tests\AuthorRule reallyReally";b:0;s:23:" Yiisoft\Rbac\Rule name";s:8:"isAuthor";}'
        ]
    ]
];

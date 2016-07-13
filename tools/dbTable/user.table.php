<?php
/*===============================================================
*   Copyright (C) 2016 All rights reserved.
*   
*   file     : user.table.php
*   author   : clwu
*   date     : 2016-07-06
*   descripe : 
*
*   modify   : 需求文档 v2.5.1 xxx功能  【增加】xxx字段
*              需求文档 v2.5.7 xxx功能  【修改】xxx字段 数据类型 从 enum 改为 int
*              需求文档 v2.6.0 xxx功能  【删除】xxx字段
*
================================================================*/

// 参考：可用chrome 控制台抓一下phpMyAdmin 创建表页面的元素，以下字段使用和phpMyAdmin 创建表页面同名的字段
$_table = [
    //'db' => 'mingpro',
    'table'              => 'user',             // 数据表名
    'comment'            => '用户信',           // 表注释
    'tbl_storage_engine' => 'MyISAM',           // 存储引擎 可选的值为[InnoDB, MyISAM, ...] @link http://dev.mysql.com/doc/refman/5.5/en/storage-engines.html
    'tbl_collation'      => 'utf8_general_ci',  // Collation
];

$_keies = [
    'primary_indexes' => [      // 主键
        [
            // "Key_name"      => "PRIMARY",        // 主键的名字不需要设置
            // "Index_comment" => "primary注释",    // 注释 为可选
            "columns" => [
                [
                    "col_name" =>"id",
                ],
            ]
        ]
    ],
    'indexes' => [              // 索引
        [
            "Key_name"      => "index索引名称",
            "Index_comment" => "index注释",
            "columns"       => [
                [
                    "col_name" => "tel",
                ],
            ]
        ]
    ],
    'unique_indexes' => [     // 唯一
        [
            "Key_name"      => "unique索引名称",
            "Index_comment" => "unique注释",
            "columns"       => [
                [
                    "col_name" => "open_id",
                ],
                [
                    "col_name" => "tel",
                ],
            ]
        ]
    ],
    //'fulltext_indexes' => '[]',   // TODO: 暂时不支持 全文搜索 类型
];

$_columns = [
    [
        'field_name'            =>  'id',                   // 名字
        'field_type'            =>  'INT',                  // 类型 @link http://dev.mysql.com/doc/refman/5.5/en/data-types.html
        //'field_length'        =>  '',                     // 长度/值  是“enum”或“set”，请使用以下格式输入：'a','b','c'… 如果需要输入反斜杠(“\”)或单引号(“'”)，请在前面加上反斜杠(如 '\\xyz' 或 'a\'b')。
        //'field_default_type'  =>  'NONE',                 // 默认：可选的值为 USER_DEFINED, NULL, CURRENT_TIMESTAMP
        'field_default_value'   =>  'NONE',                 // 当 field_default_type 为 USER_DEFINED 时，需要填写 field_default_value，对于默认值，请只输入单个值，不要加反斜杠或引号，请用此格式：a
        'field_attribute'       =>  'UNSIGNED ZEROFILL',    // 属性：可选的值为
        'field_extra'           =>  'AUTO_INCREMENT',       // 额外：目前只有一个值AUTO_INCREMENT
        //'field_comments'      =>  '',                     // 注释
        'field_null'            =>  'NULL',                 // 空：allow_null
    ],
    [
        'field_name'            =>  'tel',
        'field_type'            =>  'INT',
        //'field_length'        =>  '',
        //'field_default_type'  =>  'NONE',
        'field_default_value'   =>  'NONE',
        //'field_attribute'     =>  '',
        //'field_extra'         =>  '',
        'field_comments'        =>  '用户的电话号码',
        'field_null'            =>  'NULL',
    ],
    [
        'field_name'            =>  'city',
        'field_type'            =>  'INT',
        //'field_length'        =>  '',
        //'field_default_type'  =>  'NONE',
        'field_default_value'   =>  'NONE',
        //'field_attribute'     =>  '',
        //'field_extra'         =>  '',
        'field_comments'        =>  '城市编码',
        'field_null'            =>  'NULL',
    ],
    [
        'field_name'            =>  'open_id',
        'field_type'            =>  'CHAR',
        'field_length'          =>  '33',
        'field_default_type'    =>  'USER_DEFINED', // 定义：
        'field_default_value'   =>  '这儿是默认值~',
        'field_attribute'       =>  '',
        //'field_extra'         =>  '',
        'field_comments'        =>  '微信公众号ID',
        'field_null'            =>  'NULL',
    ],
    [
        'field_name'            =>  'addr',
        'field_type'            =>  'CHAR',
        'field_length'          =>  '50',
        'field_default_type'    =>  'USER_DEFINED', // 定义：
        'field_default_value'   =>  '',
        'field_attribute'       =>  '',
        //'field_extra'         =>  '',
        'field_comments'        =>  '用户的收货地址',
        'field_null'            =>  'NULL',
    ],
];

$user_conf = [
    'table'   => $_table,
    'columns' => $_columns,
    'keies'   => $_keies,
];

return $user_conf;

#!/bin/env php
<?php
/*===============================================================
*   Copyright (C) 2016 All rights reserved.
*   
*   file     : dbTable.helper.php
*   author   : clwu
*   date     : 2016-06-08
*   descripe : 【工具类】数据库表结构修改
*
*   modify   : 
*
================================================================*/
require_once(__DIR__.'/../conf.php');
require_once(\DDD_ROOT.'/tools/vendor/phpMyAdmin/Table.class.php');

function help()
{
    $scriptName = __FILE__;
    echo <<<EOL

Usage: php {$scriptName}
            [ -C 关闭颜色显示 ] [ -n 关闭在 CREATE TABEL 语句中比较异同 ]
            [ -d[tableName, ...] 把DB上的表 转存成 本地的表配置文件，【注意】-d和tableName之间不能有空格，tableName与tableName之间用,分隔 ] [ -y 总是同意 ]
            [ -h --help ]

Q: 不知道表的配置文件怎么写？
A: 可以用 -d 选项把 DB 的表 转存成本地的表配置文件，
   打开这个本地的表配置文件 学习 表配置文件 应该怎么写

Q: 本地的表配置文件 存放在哪？
A: tools/dbTable/ 目录，文件名为 {\$tableName}.table.php


EOL;
}

$shortopts  = "";
$shortopts .= "h";
$shortopts .= "cn";
$shortopts .= "d::y";

$longopts  = array(
    "help",
);

$options = getopt($shortopts, $longopts);

if (isset($options['h']) || isset($options['help'])) {
    help();
    return;
}

$table = new tools\vendor\phpMyAdmin\Table();

if (isset($options['d'])) {
    if (isset($options['y'])) {
        $table->opt_assume_yes = TRUE;
    }

    $tableNames = [];
    if ( $options['d'] ) {
        $tableNames = explode(',', $options['d']);
    }

    $table->dumpDb($tableNames);
} else {
    if (isset($options['c'])) {
        $table->opt_output_color = FALSE;
        $table->initOptColor();
    }

    if (isset($options['n'])) {
        $table->opt_show_create_when_diff = FALSE;
    }

    $table->showDiff();
}


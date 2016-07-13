<?php
/*===============================================================
*   Copyright (C) 2016 All rights reserved.
*   
*   file     : Table.class.php
*   author   : clwu
*   date     : 2016-06-24
*   descripe : 从 phpMyAdmin 修改过来的 表 操作类，
*              重构时为了最小改动其代码使用到了全局变量 $_REQUEST
*
*              对比本地table结构与DB中table结构的不同，
*              【注意】以下代码中 的左表(left, L) 指本地table，
*              右表(right, R) 指DB中table
*
*   modify   : 
*
================================================================*/
namespace tools\vendor\phpMyAdmin;

require_once(__DIR__.'/../../../conf.php');
require_once(\DDD_ROOT.'/Infrastructure/Db.class.php');


/* {{{ 重构后，使用 phpMyAdmin 所必须的全局设置 */
//require_once(__DIR__.'/libraries/core.lib.php');
require_once(__DIR__.'/libraries/Util.class.php');
require_once(__DIR__.'/libraries/Table.class.php');
require_once(__DIR__.'/libraries/Index.class.php');
require_once(__DIR__.'/libraries/structure.lib.php');
require_once(__DIR__.'/libraries/create_addfield.lib.php');
require_once(__DIR__.'/libraries/sqlparser.lib.php');
require_once(__DIR__.'/libraries/operations.lib.php');

require_once(__DIR__.'/libraries/mysql_charsets.lib.php');
require_once(__DIR__.'/libraries/stringNative.lib.php');
require_once(__DIR__.'/libraries/String.class.php');

require_once(__DIR__.'/libraries/tbl_indexes.lib.php');

$GLOBALS['PMA_String'] = new PMA_String();
$GLOBALS['cfg']['LimitChars'] = 50;
define("PMA_DRIZZLE", false);   // Drizzle 是 MySQL 的精简分支，似乎和 MySQL 有点不一样
/* }}} */

class Table
{
    private $db;
    private $user_conf_path;
    private $support_type;
    private $support_column_field;

    private $color_add;
    private $color_drop;
    private $color_diff;    // diff alter
    private $color_end ;

    private $id_keep_by_app = 10000;    // 10000以下自增id为系统保留
    public $opt_output_color = TRUE;            // 颜色显示
    public $opt_show_create_when_diff = TRUE;   // 在 CREATE TABEL 语句中比较异同
    public $opt_assume_yes = FALSE;             // assume that the answer to any question which would be asked is yes

    public function __construct()
    {
        $this->db = new \Infrastructure\Db();
        $this->user_conf_path = \DDD_ROOT . '/tools/dbTable';

        $this->support_type = [
            // 数字
            'TINYINT',            // 1 字节整数，有符号范围从 -128 到 127，无符号范围从 0 到 255
            'SMALLINT',           // 2 字节整数，有符号范围从 -32768 到 32767，无符号范围从 0 到 65535
            'MEDIUMINT',          // 3 字节整数，有符号范围从 -8388608 到 8388607，无符号范围从 0 到 16777215
            'INT',                // 4 字节整数，有符号范围从 -2147483648 到 2147483647，无符号范围从 0 到 4294967295
            'BIGINT',             // 8 字节整数，有符号范围从 -9223372036854775808 到 9223372036854775807，无符号范围从 0 到 18446744073709551615
            'DECIMAL',            // 定点数（M，D）- 整数部分（M）最大为 65（默认 10），小数部分（D）最大为 30（默认 0）
            'FLOAT',              // 单精度浮点数，取值范围从 -3.402823466E+38 到 -1.175494351E-38、0 以及从 1.175494351E-38 到 3.402823466E+38
            'DOUBLE',             // 双精度浮点数，取值范围从 -1.7976931348623157E+308 到 -2.2250738585072014E-308、0 以及从 2.2250738585072014E-308 到 1.7976931348623157E+308
            'REAL',               // DOUBLE 的别名（例外：REAL_AS_FLOAT SQL 模式时它是 FLOAT 的别名）
            'BIT',                // 位类型（M），每个值存储 M 位（默认为 1，最大为 64）
            'BOOLEAN',            // TINYINT(1) 的别名，零值表示假，非零值表示真
            'SERIAL',             // BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE 的别名
            // 日期与时间
            'DATE',               // 日期，支持的范围从 1000-01-01 到 9999-12-31
            'DATETIME',           // 日期与时间，支持的范围从 1000-01-01 00:00:00 到 9999-12-31 23:59:59
            'TIMESTAMP',          // 时间戳，范围从 1970-01-01 00:00:01 UTC 到 2038-01-09 03:14:07 UTC，存储为自纪元（1970-01-01 00:00:00 UTC）起的秒数
            'TIME',               // 时间，范围从 -838:59:59 到 838:59:59
            'YEAR',               // 四位数（4，默认）或两位数（2）的年份，取值范围从 70（1970）到 69（2069）或从 1901 到 2155 以及 0000
            // 文本
            'CHAR',               // 定长（0-255，默认 1）字符串，存储时会向右边补足空格到指定长度
            'VARCHAR',            // 变长（0-65,535）字符串，最大有效长度取决于最大行大小
            'TINYTEXT',           // 最多存储 255（2^8 - 1）字节的文本字段，存储时在内容前使用 1 字节表示内容的字节数
            'TEXT',               // 最多存储 65535（2^16 - 1）字节的文本字段，存储时在内容前使用 2 字节表示内容的字节数
            'MEDIUMTEXT',         // 最多存储 16777215（2^24 - 1）字节的文本字段，存储时在内容前使用 3 字节表示内容的字节数
            'LONGTEXT',           // 最多存储 4294967295 字节即 4GB（2^32 - 1）的文本字段，存储时在内容前使用 4 字节表示内容的字节数
            'BINARY',             // 类似于 CHAR 类型，但其存储的是二进制字节串而不是非二进制字符串
            'VARBINARY',          // 类似于 VARCHAR 类型，但其存储的是二进制字节串而不是非二进制字符串
            'TINYBLOB',           // 最多存储 255（2^8 - 1）字节的 BLOB 字段，存储时在内容前使用 1 字节表示内容的字节数
            'MEDIUMBLOB',         // 最多存储 16777215（2^24 - 1）字节的 BLOB 字段，存储时在内容前使用 3 字节表示内容的字节数
            'BLOB',               // 最多存储 65535（2^16 - 1）字节的 BLOB 字段，存储时在内容前使用 2 字节表示内容的字节数
            'LONGBLOB',           // 最多存储 4294967295 字节即 4GB（2^32 - 1）的 BLOB 字段，存储时在内容前使用 4 字节表示内容的字节数
            'ENUM',               // 枚举，可从最多 65535 个值的列表中选择或特殊的错误值 ''
            'SET',                // 可从最多 64 个成员中选择集合为一个值
            // 空间
            'GEOMETRY',           // 一种能存储任意类型几何体的类型
            'POINT',              // 二维空间中的点
            'LINESTRING',         // 点之间的线性插值曲线
            'POLYGON',            // 多边形
            'MULTIPOINT',         // 点的集合
            'MULTILINESTRING',    // 点之间的线性插值曲线的集合
            'MULTIPOLYGON',       // 多边形的集合
            'GEOMETRYCOLLECTION', // 任意类型几何体对象的集合
        ];
        $this->support_column_field = [
            'field_name',
            'field_type',
            'field_length',
            'field_default_type',
            'field_default_value',
            'field_collation',
            'field_attribute',
            'field_key',
            'field_extra',
            'field_comments',
            'field_null',
        ];

        $this->initOptColor();
    }

    public function initOptColor()
    {
        $C_cls    = "\e[0m";
        $C_black  = "\e[1;30m";
        $C_red    = "\e[1;31m";
        $C_green  = "\e[1;32m";
        $C_yellow = "\e[1;33m";
        $C_blue   = "\e[1;34m";
        $C_purple = "\e[1;35m";
        $C_cyan   = "\e[1;36m";
        $C_white  = "\e[1;37m";

        if ($this->supportTermColor()) {
            $this->color_add  = $C_blue;
            $this->color_drop = $C_green;
            $this->color_diff = $C_red;
            $this->color_end  = $C_cls;
        } else {
            $this->color_add  = '';
            $this->color_drop = '';
            $this->color_diff = '';
            $this->color_end  = '';
        }
    }

    /**
        * 返回终端是否支持颜色
        *
        * @return   boolean
     */
    private function supportTermColor()
    {
        if ($this->opt_output_color) {
            ob_start();
            system('tput colors');
            $n = intval(ob_get_contents());
            ob_end_clean();

            return $n > 0;
        } else {
            return FALSE;
        }
    }

    private function getCommandLine()
    {
        global $argv;
        return 'php '.implode(' ', $argv);
    }

    /**
        * 从本地配置文件$user_conf 中读入 $pma_conf
        *
        * @param    $tableName
        *
        * @return   FALSE: 不存在/不可读
     */
    public function getPmaConf_fromUser($tableName)
    {
        $path = "{$this->user_conf_path}/{$tableName}.table.php";
        if (is_readable($path)) {
            $user_conf = require($path);

            $pma_conf = $this->user2pma($user_conf);
            return $pma_conf;
        }

        return FALSE;
    }

    public function getMysqlConf($tableName)
    {
        $dbname = $this->db->dbname;

        $sql = "SHOW TABLE STATUS FROM `{$dbname}` where Name in ('{$tableName}');";
        $row = $this->db->queryOne($sql);

        if ($row) {
            $Name = $row['Name'];
            $tbl_collation = $row['Collation'];
            $table = [
                'Name'              => $row['Name'],
                'Engine'            => $row['Engine'],
                'Auto_increment'    => $row['Auto_increment'],
                'Create_time'       => $row['Create_time'],
                'Update_time'       => $row['Update_time'],
                'Collation'         => $row['Collation'],
                'Comment'           => $row['Comment'],
                //'Version'         => $row['Version'],
                //'Row_format'      => $row['Row_format'],
                //'Rows'            => $row['Rows'],
                //'Avg_row_length'  => $row['Avg_row_length'],
                //'Data_length'     => $row['Data_length'],
                //'Max_data_length' => $row['Max_data_length'],
                //'Index_length'    => $row['Index_length'],
                //'Data_free'       => $row['Data_free'],
                //'Check_time'      => $row['Check_time'],
                //'Checksum'        => $row['Checksum'],
                //'Create_options'  => $row['Create_options'],
            ];

            $sql = "SHOW FULL COLUMNS FROM `{$dbname}`.`{$Name}`;";
            $rows = $this->db->query($sql);
            $columns = [];
            foreach ($rows as $row) {
                $Field = $row['Field'];
                $col_collation = $row['Collation'];
                $column = [
                    'Field'       => $row['Field'],
                    'Type'        => $row['Type'],
                    'Null'        => $row['Null'],
                    'Default'     => $row['Default'],
                    'Extra'       => $row['Extra'],
                    'Comment'     => $row['Comment'],
                    //'Key'       => $row['Key'],
                    //'Collation' => $row['Collation'],
                    //'Privileges'=> $row['Privileges'],
                ];
                if ($col_collation && $col_collation != $tbl_collation) {
                    $column['Collation'] = $row['Collation'];
                }

                $columns[$Field] = $column;
            }

            $sql = "SHOW INDEX FROM `{$dbname}`.`{$Name}`;";
            $rows = $this->db->query($sql);
            $keies = [
                'primary_indexes'  => [],
                'indexes'          => [],
                'unique_indexes'   => [],
                'fulltext_indexes' => [], // 目前不支持这种类型
            ];
            foreach ($rows as $row) {
                $Key_name = $row['Key_name'];
                if ('PRIMARY' == $row['Key_name']) {
                    $keyKind = 'primary_indexes';
                } else {
                    if (0 == $row['Non_unique']) {
                        $keyKind = 'unique_indexes';
                    } else {    // TODO: 其它都当作 index 类型，可能会有问题
                        $keyKind = 'indexes';
                    }
                }

                $key = [
                    'Key_name'      => $row['Key_name'],
                    'Index_comment' => $row['Index_comment'],
                    'Column_name'   => [ $row['Column_name'] ],
                    //'Seq_in_index'  => intval($row['Seq_in_index']) - 1,   // mysql 从1数起，改为从0数起
                    //'Table'       => $row['Table'],
                    //'Non_unique'  => $row['Non_unique'],
                    //'Collation'   => $row['Collation'],
                    //'Cardinality' => $row['Cardinality'],
                    //'Sub_part'    => $row['Sub_part'],
                    //'Packed'      => $row['Packed'],
                    //'Null'        => $row['Null'],
                    //'Index_type'  => $row['Index_type'],
                    //'Comment'     => $row['Comment'],
                ];
                if (isset($keies[$keyKind][$Key_name])) {
                    $keies[$keyKind][$Key_name]['Column_name'] = array_merge($keies[$keyKind][$Key_name]['Column_name'], $key['Column_name']);
                } else {
                    $keies[$keyKind][$Key_name] = $key;
                }
            }

            $mysql_conf = [
                'table'   => $table,
                'columns' => $columns,
                'keies'   => $keies,
            ];

            return $mysql_conf;
        }

        return FALSE;
    }

    /**
        * 从DB $mysql_conf 中读入 $pma_conf
        *
        * @param    $tableName
        *
        * @return   FALSE: 不存在
     */
    public function getPmaConf_fromMysql($tableName)
    {
        $mysql_conf = $this->getMysqlConf($tableName);
        if ($mysql_conf) {
            $pma_conf = $this->mysql2pma($mysql_conf);
            return $pma_conf;
        } else {
            return FALSE;
        }
    }

    public function buildCreateSql(array $pma_conf)
    {
        $db    = $pma_conf['db'];
        $table = $pma_conf['table'];
        if ($this->hasAutoIncrement($pma_conf)) {
            $pma_conf['auto_increment']        =  $this->id_keep_by_app;
            $pma_conf['color_auto_increment']  =  $this->color_diff;
            $pma_conf['C_cls']                 =  $this->color_end;
        } 

        $old_REQUEST = $_REQUEST;
        $_REQUEST = $pma_conf;
        {
            $sql_query = PMA_getTableCreationQuery($db, $table, TRUE);
        }
        $_REQUEST = $old_REQUEST;   // 恢复

        return $sql_query;
    }

    public function buildAlterSql(array $pma_conf)
    {
        $db    = $pma_conf['db'];
        $table = $pma_conf['table'];

        $old_REQUEST = $_REQUEST;
        $_REQUEST = $pma_conf;
        {
            $sql_query = PMA_tryColumnCreationQuery($db, $table);
        }
        $_REQUEST = $old_REQUEST;   // 恢复

        return $sql_query;
    }

    public function buildRenameSql($oldTableName, $newTableName)
    {
        return 'RENAME TABLE ' . $oldTableName . ' TO ' . $newTableName . ';';
    }

    /**
     * 返回 CREATE TABLE sql语句
     *
     * @param    $user_conf
     * <code>
     * // 参考：可用chrome 控制台抓一下phpMyAdmin 创建表页面的元素，以下字段使用和phpMyAdmin 创建表页面同名的字段
     * $_table = [
     *     //'db' => 'test',
     *     'table'              => 'user',            // 数据表名
     *     'comment'            => '用户信息',        // 表注释
     *     'tbl_storage_engine' => 'InnoDB',          // 存储引擎 可选的值为[InnoDB, MyISAM, ...] @link http://dev.mysql.com/doc/refman/5.5/en/storage-engines.html
     *     'tbl_collation'      => 'utf8_general_ci', // Collation
     * ];
     * $_keies = [
     *     'primary_indexes' => [      // 主键
     *         [
     *             "columns" => [
     *                 ["col_name" =>"id"],
     *             ]
     *         ]
     *     ],
     *     'indexes' => [              // 索引
     *         [
     *             "Key_name"      => "index索引名称",
     *             "Index_comment" => "index注释",
     *             "columns"       => [
     *                 [
     *                     "col_name" => "tel",
     *                 ],
     *             ]
     *         ]
     *     ],
     *     'unique_indexes' => [     // 唯一
     *         [
     *             "Key_name"      => "unique索引名称",
     *             "Index_comment" => "unique注释",
     *             "columns"       => [
     *                 [
     *                     "col_name" => "open_id",
     *                 ]
     *             ]
     *         ]
     *     ],
     *     //'fulltext_indexes' => '[]',   // TODO: 暂时不支持 全文搜索 类型
     * ];
     * $_columns = [
     *     [
     *         'field_name'            =>  'id',                   // 名字
     *         'field_type'            =>  'INT',                  // 类型 @link http://dev.mysql.com/doc/refman/5.5/en/data-types.html
     *         //'field_length'        =>  '',                     // 长度/值  是“enum”或“set”，请使用以下格式输入：'a','b','c'… 如果需要输入反斜杠(“\”)或单引号(“'”)，请在前面加上反斜杠(如 '\\xyz' 或 'a\'b')。
     *         //'field_default_type'  =>  'NONE',                 // 默认：可选的值为 USER_DEFINED, NULL, CURRENT_TIMESTAMP
     *         'field_default_value'   =>  'NONE',                 // 当 field_default_type 为 USER_DEFINED 时，需要填写 field_default_value，对于默认值，请只输入单个值，不要加反斜杠或引号，请用此格式：a
     *         'field_attribute'       =>  'UNSIGNED ZEROFILL',    // 属性：可选的值为
     *         'field_extra'           =>  'AUTO_INCREMENT',       // 额外：目前只有一个值AUTO_INCREMENT
     *         //'field_comments'      =>  '',                     // 注释
     *         'field_null'            =>  'NULL',                 // 空：allow_null
     *     ],
     *     [
     *         'field_name'            =>  'tel',
     *         'field_type'            =>  'INT',
     *         //'field_length'        =>  '',
     *         //'field_default_type'  =>  'NONE',
     *         'field_default_value'   =>  'NONE',
     *         //'field_attribute'     =>  '',
     *         //'field_extra'         =>  '',
     *         'field_comments'        =>  '用户的电话号码',
     *         'field_null'            =>  'NULL',
     *     ],
     *     [
     *         'field_name'            =>  'open_id',
     *         'field_type'            =>  'CHAR',
     *         'field_length'          =>  '33',
     *         'field_default_type'    =>  'USER_DEFINED', // 定义：
     *         'field_default_value'   =>  '这儿是默认值~',
     *         'field_attribute'       =>  '',
     *         //'field_extra'         =>  '',
     *         'field_comments'        =>  '微信公众号ID',
     *         'field_null'            =>  'NULL',
     *     ],
     * ];
     * $user_conf = [
     *     'table'   => $_table,
     *     'columns' => $_columns,
     *     'keies'   => $_keies,
     * ];
     * 
     * $table = new Table();
     * $table->createSql($user_conf);
     *
     * // ==================== return ===========================
     * CREATE TABLE `test`.`user` (
     *   `id` INT UNSIGNED ZEROFILL NOT NULL AUTO_INCREMENT,
     *   `tel` INT NULL COMMENT '用户的电话号码',
     *   `open_id` CHAR(33) NULL DEFAULT '这儿是默认值~' COMMENT '微信公众号ID',
     *   PRIMARY KEY  (`id`),
     *   INDEX `index索引名称` (`tel`) COMMENT 'index注释',
     *   UNIQUE `unique索引名称` (`open_id`) COMMENT 'unique注释'
     * ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT = '用户信息';
     * </code>
     *
     * @link     tbl_create.php
     * @link     http://dev.mysql.com/doc/refman/5.5/en/create-table.html
     * @return   
     */
    public function createSql(array $user_conf)
    {
        $pma_conf = $this->checkUserConf($user_conf);

        $_REQUEST = $pma_conf;

        $db    = $pma_conf['db'];
        $table = $pma_conf['table'];

        $sql_query = PMA_getTableCreationQuery($db, $table, TRUE);
        echo $sql_query, PHP_EOL;

        return $sql_query;
    }

    /**
        * 返回格式化后的 sql 语句
        *
        * @param    string $sql
        *
        * @return   string
     */
    public function formatSql($sql)
    {
        $parsed_sql = PMA_SQP_parse($sql);
        $string = PMA_SQP_format($parsed_sql, "query_only");

        return $string;
    }

    /**
        * 比较 左右 表的区别，并且 把 右 表更新为 左 表
        *
        * @param    $pma_conf_L
        * @param    $pma_conf_R
        *
        * @todo     没支持 ROW_FORMAT   
        * @return   
     */
    public function diffPma(&$pma_conf_L, &$pma_conf_R)
    {
        $pma_conf_L['C_cls'] = $this->color_end;
        $pma_conf_R['C_cls'] = $this->color_end;
        $changeSql = []; // 把 右 表更新为 左 表

        $changeSql['table']   = $this->diffPma_table_both($pma_conf_L, $pma_conf_R);
        $changeSql['columns'] = $this->diffPma_columns($pma_conf_L, $pma_conf_R);
        $changeSql['keies']   = $this->diffPma_keies($pma_conf_L, $pma_conf_R);

        return $changeSql;
    }

    private function diffPma_table_both(&$pma_conf_L, &$pma_conf_R)
    {
        $pma_arg = [];
        $support_field = [
            'table',
            'comment',
            'tbl_storage_engine',
            'tbl_collation',
        ];
        foreach ($support_field as $k) {
            if ($pma_conf_L[$k] != $pma_conf_R[$k]) {
                $old_k = "old_{$k}";
                $pma_arg[$k] = $pma_conf_L[$k];
                $pma_arg[$old_k] = $pma_conf_R[$k];

                $color_k = "color_{$k}";
                $pma_conf_L[$color_k] = $this->color_diff;
                $pma_conf_R[$color_k] = $this->color_diff;
            }
        }

        /*
        $pma_arg = [
            'old_comment'	=> 'old_comment',
            'old_tbl_storage_engine'	=> 'old_tbl_storage_engine',
            'old_tbl_collation'	=> 'old_tbl_collation',

            'comment'	=> 'comment',
            'tbl_storage_engine'	=> 'tbl_storage_engine',
            'tbl_collation'	=> 'tbl_collation',
        ];
         */
        if ($pma_arg) {
            $tableName               =  $pma_conf_R['table'];
            $old_tbl_storage_engine  =  $pma_conf_R['table'];
            $sql_query = $this->alterTable($tableName, $old_tbl_storage_engine, $pma_arg);
        } else {
            $sql_query = '';
        }

        return $sql_query;
    }
    /**
        * 只存在左边（本地），需要创建表
        *
        * @param    $pma_conf
        *
        * @return   
     */
    private function diffPma_table_left($pma_conf)
    {
        return $this->buildCreateSql($pma_conf);
    }
    /**
        * 只存在右边（DB），需要删除表
        *
        * @param    $pma_conf
        *
        * @return   
     */
    private function diffPma_table_right($pma_conf)
    {
        $db    = $pma_conf['db'];
        $table = $pma_conf['table'];

        return "DROP TABLE `{$db}`.`{$table}`;";
    }

    private function diffPma_columns(&$pma_conf_L, &$pma_conf_R)
    {
        $changeSql = [];
        $support_field = $this->support_column_field;
        foreach ($support_field as $k) {
            $color_k = "color_{$k}";
            $pma_conf_L[$color_k] = [];
            $pma_conf_R[$color_k] = [];
        }

        $changeSql['move']  = $this->diffPma_columns_move($pma_conf_L, $pma_conf_R);

        $changeSql['add']   = $this->diffPma_columns_left($support_field, $pma_conf_L, $pma_conf_R);
        $changeSql['drop']  = $this->diffPma_columns_right($support_field, $pma_conf_L, $pma_conf_R);
        $changeSql['alter'] = $this->diffPma_columns_both($support_field, $pma_conf_L, $pma_conf_R);

        return $changeSql;
    }
    /**
        * 移动字段
        *
        * @param    $pma_conf_L
        * @param    $pma_conf_R
        *
        * @return   
     */
    private function diffPma_columns_move(&$pma_conf_L, &$pma_conf_R)
    {
        $dbName    = $pma_conf_L['db'];
        $tableName = $pma_conf_L['table'];
        $field_name_L = $pma_conf_L['field_name'];
        $field_name_R = $pma_conf_R['field_name'];
        $both = function ($field_name) use (&$field_name_R) {
            return in_array($field_name, $field_name_R);
        };
        $right = function ($field_name) use (&$field_name_L) {
            return !in_array($field_name, $field_name_L);
        };
        $move_columns = [];

        foreach ($field_name_L as $field_name) {    // 公有部分，需要调整的顺序
            if ($both($field_name)) {
                $move_columns[] = $field_name;
            }
        }
        foreach ($field_name_R as $field_name) {    // 右表才有，等待删除
            if ($right($field_name)) {
                $move_columns[] = $field_name;
            }
        }

        $sql = "
            SELECT *,
            `COLUMN_NAME`       AS `Field`,
            `COLUMN_TYPE`       AS `Type`,
            `COLLATION_NAME`    AS `Collation`,
            `IS_NULLABLE`       AS `Null`,
            `COLUMN_KEY`        AS `Key`,
            `COLUMN_DEFAULT`    AS `Default`,
            `EXTRA`             AS `Extra`,
            `PRIVILEGES`        AS `Privileges`,
            `COLUMN_COMMENT`    AS `Comment`
            FROM `information_schema`.`COLUMNS`
            WHERE `TABLE_SCHEMA` = '{$dbName}'  AND `TABLE_NAME` = '{$tableName}'";
        $columnsFull = $this->db->query($sql);
        $_columnsFull = [];
        foreach ($columnsFull as $c) {
            $_columnsFull[$c['COLUMN_NAME']] = $c;
        }

        $pma_conf = [
            'db' => $dbName,
            'table' => $tableName,
            'move_columns' => $move_columns,
        ];

        $old_REQUEST = $_REQUEST;
        $_REQUEST = $pma_conf;
        {
            $move_query = PMA_moveColumns($dbName, $tableName, $_columnsFull);
        }
        $_REQUEST = $old_REQUEST;   // 恢复

        return $move_query;
    }
    /**
        * 只存在 左 边表的区别
        *
        * @param    $support_field
        * @param    $pma_conf_L
        * @param    $pma_conf_R
        *
        * @return   
     */
    private function diffPma_columns_left($support_field, &$pma_conf_L, &$pma_conf_R)
    {
        $changeSql = [];
        $field_name_L = $pma_conf_L['field_name'];
        $field_name_R = $pma_conf_R['field_name'];
        $left = function ($field_name) use (&$field_name_R) {
            return !in_array($field_name, $field_name_R);
        };
        $tableName = $pma_conf_L['table'];

        $i = 0;
        foreach ($field_name_L as $field_name) {
            if ($left($field_name)) {
                $changeSql[] = $this->alterColumnAdd($i, $pma_conf_L);

                foreach ($support_field as $k) {
                    $color_k = "color_{$k}";
                    $pma_conf_L[$color_k][$i] = $this->color_add;
                }
            }
            ++$i;
        }

        return $changeSql;
    }
    /**
        * 只存在 右 边表的区别
        *
        * @param    $support_field
        * @param    $pma_conf_L
        * @param    $pma_conf_R
        *
        * @return   
     */
    private function diffPma_columns_right($support_field, &$pma_conf_L, &$pma_conf_R)
    {
        $changeSql = [];
        $field_name_L = $pma_conf_L['field_name'];
        $field_name_R = $pma_conf_R['field_name'];
        $right = function ($field_name) use (&$field_name_L) {
            return !in_array($field_name, $field_name_L);
        };
        $tableName = $pma_conf_L['table'];

        $i = 0;
        foreach ($field_name_R as $field_name) {
            if ($right($field_name)) {
                $changeSql[] = $this->alterColumnDrop($tableName, $field_name);

                foreach ($support_field as $k) {
                    $color_k = "color_{$k}";
                    $pma_conf_R[$color_k][$i] = $this->color_drop;
                }
            }
            ++$i;
        }

        return $changeSql;
    }
    /**
        * 同时存在 两 边表的区别
        *
        * @param    $support_field
        * @param    $pma_conf_L
        * @param    $pma_conf_R
        *
        * @return   
     */
    private function diffPma_columns_both($support_field, &$pma_conf_L, &$pma_conf_R)
    {
        $changeSql = [];
        $field_name_L = $pma_conf_L['field_name'];
        $field_name_R = $pma_conf_R['field_name'];
        $both = function ($field_name) use (&$field_name_R) {
            return array_search($field_name, $field_name_R);
        };
        $diff = function ($i_L, $i_R) use (&$support_field, &$pma_conf_L, &$pma_conf_R) {
            foreach ($support_field as $k) {
                if ('field_key' == $k) {
                    // 交给 diffPma_keies 处理
                } else {
                    if ($pma_conf_L[$k][$i_L] != $pma_conf_R[$k][$i_R]) {
                    echo $i_L.': '. $k.': '. $pma_conf_L[$k][$i_L] .': '.$pma_conf_R[$k][$i_R].': '. PHP_EOL;
                        return TRUE;
                    }
                }
            }
            return FALSE;
        };
        $tableName = $pma_conf_L['table'];

        $i = 0;
        foreach ($field_name_L as $field_name) {
            $i_R = $both($field_name);
            if (FALSE !== $i_R && $diff($i, $i_R)) {
                $changeSql[] = $this->alterColumn($pma_conf_L, $i, $pma_conf_R, $i_R);

                foreach ($support_field as $k) {
                    $color_k = "color_{$k}";
                    $pma_conf_L[$color_k][$i]   = $this->color_diff;
                    $pma_conf_R[$color_k][$i_R] = $this->color_diff;
                }
            }
            ++$i;
        }

        return $changeSql;
    }

    private function diffPma_keies(&$pma_conf_L, &$pma_conf_R)
    {
        $changeSql = [
            'add'    =>  [],
            'drop'   =>  [],
            'alter'  =>  [],
        ];
        $keyKinds = [
            'primary_indexes'  =>  'PRIMARY',
            'indexes'          =>  'INDEX',
            'unique_indexes'   =>  'UNIQUE',
        ];
        foreach ($keyKinds as $keyKind => $Index_type) {
            $pma_conf_L[$keyKind] = json_decode($pma_conf_L[$keyKind], TRUE);
            $pma_conf_R[$keyKind] = json_decode($pma_conf_R[$keyKind], TRUE);

            $changeSql['add']    =  array_merge($changeSql['add'],    $this->diffPma_keies_left($keyKind,   $Index_type,   $pma_conf_L,  $pma_conf_R));
            $changeSql['drop']   =  array_merge($changeSql['drop'],   $this->diffPma_keies_right($keyKind,  $Index_type,   $pma_conf_L,  $pma_conf_R));
            $changeSql['alter']  =  array_merge($changeSql['alter'],  $this->diffPma_keies_both($keyKind,   $Index_type,   $pma_conf_L,  $pma_conf_R));

            $pma_conf_L[$keyKind] = json_encode($pma_conf_L[$keyKind], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $pma_conf_R[$keyKind] = json_encode($pma_conf_R[$keyKind], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        }

        return $changeSql;
    }
    private function diffPma_keies_left($keyKind, $Index_type, &$pma_conf_L, &$pma_conf_R)
    {
        $changeSql = [];
        $keies_L = &$pma_conf_L[$keyKind];
        $keies_R = &$pma_conf_R[$keyKind];
        $left = function ($Key_name) use (&$keies_R) {
            foreach ($keies_R as $key_R) {
                if ($Key_name == $key_R['Key_name']) {
                    return FALSE;
                }
            }
            return TRUE;
        };

        $i = 0;
        foreach ($keies_L as &$key_L) {
            $Key_name = $key_L['Key_name'];
            if ($left($Key_name)) {
                $changeSql[] = $this->alterKeyAdd($pma_conf_L, $i, $keyKind, $Index_type);

                foreach (array_keys($key_L) as $k) {
                    $color_k = "color_{$k}";
                    $key_L[$color_k] = $this->color_add;
                }
            }
            ++$i;
        }

        return $changeSql;
    }
    private function diffPma_keies_right($keyKind, $Index_type, &$pma_conf_L, &$pma_conf_R)
    {
        $changeSql = [];
        $keies_L = &$pma_conf_L[$keyKind];
        $keies_R = &$pma_conf_R[$keyKind];
        $right = function ($Key_name) use (&$keies_L) {
            $i = 0;
            foreach ($keies_L as $key_L) {
                if ($Key_name == $key_L['Key_name']) {
                    return FALSE;
                }
                ++$i;
            }
            return TRUE;
        };

        $i = 0;
        foreach ($keies_R as &$key_R) {
            $Key_name = $key_R['Key_name'];
            if ($right($Key_name)) {
                $tableName = $pma_conf_R['table'];
                $changeSql[] = $this->alterKeyDrop($tableName, $Key_name, $Index_type);

                foreach (array_keys($key_R) as $k) {
                    $color_k = "color_{$k}";
                    $key_R[$color_k] = $this->color_drop;
                }
            }
            ++$i;
        }

        return $changeSql;
    }
    private function diffPma_keies_both($keyKind, $Index_type, &$pma_conf_L, &$pma_conf_R)
    {
        $changeSql = [];
        $keies_L = &$pma_conf_L[$keyKind];
        $keies_R = &$pma_conf_R[$keyKind];
        $field_name_L = $pma_conf_L['field_name'];
        $field_name_R = $pma_conf_R['field_name'];
        $both = function ($Key_name) use (&$keies_R) {
            $i = 0;
            foreach ($keies_R as $key_R) {
                if ($Key_name == $key_R['Key_name']) {
                    return $i;
                }
                ++$i;
            }
            return FALSE;
        };
        $diff = function ($i_L, $i_R) use (&$keies_L, &$keies_R, &$field_name_L, &$field_name_R) {
            $key_L = &$keies_L[$i_L];
            $key_R = &$keies_R[$i_R];
            $columns_L = &$key_L['columns'];
            $columns_R = &$key_R['columns'];
            $r = FALSE;

            if ($key_L['Index_comment'] != $key_R['Index_comment']) {
                $key_L['color_Index_comment'] = $this->color_diff;
                $key_R['color_Index_comment'] = $this->color_diff;
                $r = TRUE;
            }

            if (count($columns_L) != count($columns_R)) {
                $key_L['color_columns'] = $this->color_diff;
                $key_R['color_columns'] = $this->color_diff;
                $r = TRUE;
            } else {
                $len = count($columns_L);
                for ($i = 0; $i < $len; ++$i) {
                    $col_index_L = $columns_L[$i]["col_index"];
                    $col_index_R = $columns_R[$i]["col_index"];
                    if ($field_name_L[$col_index_L] != $field_name_R[$col_index_R]) {
                        $key_L['color_columns'] = $this->color_diff;
                        $key_R['color_columns'] = $this->color_diff;
                        $r = TRUE;
                    }
                }
            }

            return $r;
        };

        $i = 0;
        foreach ($keies_L as &$key_L) {
            $Key_name = $key_L['Key_name'];
            $i_R = $both($Key_name);
            if (FALSE !== $i_R && $diff($i, $i_R)) {
                $changeSql[] = $this->alterKey($pma_conf_L, $i, $keyKind, $Index_type);
            }
            ++$i;
        }

        return $changeSql;
    }

    /**
        * 检查用户的配置数组
        *
        * @param    $user_conf
        *
        * @return   
     */
    private function checkUserConf($user_conf)
    {
        $_user_conf = [];
        $which = "";
        $filterFn = function ($array, $key, $arg_list, $err_str = NULL) use (&$which) {
            if (isset($array[$key])) {
                $arg = trim($array[$key]);
                $value = call_user_func_array('filter_var', array_merge([ $arg ], $arg_list));
                if (FALSE !== $value) {
                    return $value;
                }
            }

            $errStr = "请检查 \$user_conf['{$which}']['{$key}'] 参数配置";
            if ($err_str) {
                $errStr = $errStr . '， ' . $err_str;
            }
            throw new \Exception($errStr);
        };

        try {
            if (isset($user_conf['table']) && is_array($user_conf['table'])) {         // table 必须
                $which = 'table';
                $_user_conf['table'] = $this->checkUserConf_table($user_conf['table'], $filterFn);
            } else {
                throw new \Exception('必须要设置 $user_conf[\'table\']');
            }

            if (isset($user_conf['columns']) && is_array($user_conf['columns'])) {     // columns 必须
                $which = 'columns';
                $_user_conf['columns'] = $this->checkUserConf_columns($user_conf['columns'], $filterFn);
            } else {
                throw new \Exception('必须要设置 $user_conf[\'columns\']');
            }

            if (isset($user_conf['keies']) && is_array($user_conf['keies'])) {         // keies 非必须
                $which = 'keies';
                $_user_conf['keies'] = $this->checkUserConf_keies($_user_conf['columns'], $user_conf['keies'], $filterFn);
            }
        } catch (\Exception $e) {
            if ( !empty($user_conf['table']) && !empty($user_conf['table']['table']) ) {
                $tableName = $user_conf['table']['table'];
            } else {
                $tableName = '';
            }

            var_export($user_conf);
            $errMsg  = $e->getMessage();
            echo "{$tableName}: {$errMsg}", PHP_EOL;
            echo $e->getTraceAsString(), PHP_EOL, PHP_EOL;

            echo <<<EOL
似乎出现问题了，可以是以下两种情况：
    1): dbTable/xxx.table.php 的配置有错误，解决方法，参考下面的文档
    2): 这个工具可能有bug，解决方法，修复它（前提是你非常确定它是一个bug）

EOL;
            //debug_print_backtrace();
            echo PHP_EOL;
            $this->echoCreateSqlHelp();
            exit(200);

            return FALSE;
        }

        return $_user_conf;
    }

    /**
        * 检查用户的配置数组中的 表信息
        *
        * @param    $table
        * @param    $filterFn
        *
        * @return   
     */
    private function checkUserConf_table($table, $filterFn)
    {
        $engineCb = function ($engine) {
            $support = ['InnoDB', 'MyISAM'];
            if ( ! in_array($engine, $support) ) {
                return FALSE;
            }

            return $engine;
        };

        $collationCb = function ($collation) {
            $support = ['utf8_general_ci'];
            if ( ! in_array($collation, $support) ) {
                return FALSE;
            }

            return $collation;
        };

        $_table = [];
        // 必选字段
        $_table['table']              = $filterFn($table, 'table', []);
        // 可选字段，给出默认配置值
        if (empty($table['comment'])) {
            $table['comment'] = '';
        }
        if (empty($table['tbl_storage_engine'])) {
            $table['tbl_storage_engine'] = 'MyISAM';
        }
        if (empty($table['tbl_collation'])) {
            $table['tbl_collation'] = 'utf8_general_ci';
        }
        $_table['comment']            = $filterFn($table, 'comment', []);
        $_table['tbl_storage_engine'] = $filterFn($table, 'tbl_storage_engine', [FILTER_CALLBACK, ['options' => $engineCb]], "目前项目中只允许使用以下引擎：InnoDB、MyISAM");
        $_table['tbl_collation']      = $filterFn($table, 'tbl_collation', [FILTER_CALLBACK, ['options' => $collationCb]], "目前项目中只允许的 collation 为 utf8_general_ci");
        // $filterFn($table, 'Auto_increment', [FILTER_VALIDATE_INT])

        return $_table;
    }

    /**
        * 检查用户的配置数组中的 列信息
        *
        * @param    $columns
        * @param    $filterFn
        *
        * @return   
     */
    private function checkUserConf_columns($columns, $filterFn)
    {
        $support_type = $this->support_type;
        $typeCb = function ($type) use (&$support_type) {
            $type = strtoupper($type);
            if ( ! in_array($type, $support_type) ) {
                return FALSE;
            }

            return $type;
        };

        $extraCb = function ($extra) {
            $extra = strtoupper($extra);
            $support = ['AUTO_INCREMENT'];
            if ( ! in_array($extra, $support) ) {
                return FALSE;
            }

            return $extra;
        };

        $attributeCb = function ($attribute) {
            $attribute = strtoupper($attribute);
            $attribute = preg_replace('/\s+/', ' ', $attribute); // 多个空格替换为一个空格
            $support = ['BINARY', 'UNSIGNED', 'UNSIGNED ZEROFILL', 'ON UPDATE CURRENT_TIMESTAMP'];
            if ( ! in_array($attribute, $support) ) {
                return FALSE;
            }

            return $attribute;
        };

        $nullCb = function ($null) {
            $null = strtoupper($null);
            $support = ['NULL'];
            if ( ! in_array($null, $support) ) {
                return FALSE;
            }

            return $null;
        };

        $_columns = [];
        $_column = [];
        $defaultCb = function ($value) use (&$_column) {
            $u = strtoupper($value);
            if (in_array($u, ['NONE', 'NULL', 'CURRENT_TIMESTAMP'])) {
                $_column['field_default_type'] = $u;
                $value = '';
            } else {
                $_column['field_default_type'] = 'USER_DEFINED';
            }
            return $value;
        };

        $_columns = [];
        $i = 0;
        foreach ($columns as $column) {
            $_column = [];
            $err_str = "第 {$i} 列";

            // 必选字段
            $_column['field_name'] = $filterFn($column, 'field_name', [], $err_str);
            $fieldName = $_column['field_name'];

            $_column['field_type']          = $filterFn($column, 'field_type', [FILTER_CALLBACK, ['options' => $typeCb]], $err_str."，MySQL 数据类型 http://dev.mysql.com/doc/refman/5.5/en/create-table.html");
            // 可选字段，给出默认配置值
            if (empty($column['field_comments'])) {
                $_column['field_comments'] = '';
            } else {
                $_column['field_comments']      = $filterFn($column, 'field_comments', [], $err_str);
            }
            if (empty($column['field_attribute'])) {
                $_column['field_attribute'] = '';
            } else {
                $_column['field_attribute'] = $filterFn($column, 'field_attribute', [FILTER_CALLBACK, ['options' => $attributeCb]], $err_str);
            }

            if (empty($column['field_default_value'])) {
                $_column['field_default_value'] = 'NONE';
            } else {
                $_column['field_default_value'] = $filterFn($column, 'field_default_value', [FILTER_CALLBACK, ['options' => $defaultCb]], $err_str);
            }

            if ( !empty($column['field_length']) ) {
                $_column['field_length']         = $filterFn($column, 'field_length', [FILTER_VALIDATE_INT], $err_str."，目前 field_length 要求 整数");
            }

            if ( !empty($column['field_extra']) ) {
                $_column['field_extra']         = $filterFn($column, 'field_extra', [FILTER_CALLBACK, ['options' => $extraCb]], $err_str."，目前 Extra 支持 auto_increment");
            }

            if ( !empty($column['field_null']) ) {
                $_column['field_null'] = $filterFn($column, 'field_null', [FILTER_CALLBACK, ['options' => $nullCb]], $err_str);
            }
            // 目前不可选字段
            $_column['field_collation'] = '';
            $_column['field_key'] = "none_{$i}";   // 在 checkUserConf_keies 中重新设置
            $_columns[] = $_column;
            ++$i;
        }

        return $_columns;
    }

    /**
        * 检查用户的配置数组中的 索引信息
        *
        * @param    $columns
        * @param    $keies
        * @param    $filterFn
        *
        * @return   
     */
    private function checkUserConf_keies(&$columns, $keies, $filterFn)
    {
        $checkColumnName = function ($col_name, $err_str) use (&$columns) {
            foreach ($columns as $key => $column) {
                if ($col_name == $column['field_name']) {
                    return $key;
                }
            }

            throw new \Exception($err_str . "，不存在 表字段 名：{$col_name}");
        };

        $keyKinds = [
            'primary_indexes' => 'primary_',
            'indexes'         => 'index_',
            'unique_indexes'  => 'unique_',
        ];

        $_keies = [];
        foreach ($keyKinds as $keyKind => $prefix) {
            if ( !empty($keies[$keyKind])) {
                $key = $keies[$keyKind];
                $_key = [];
                foreach ($key as $k) {
                    $_k = [];
                    if ('primary_indexes' == $keyKind) {
                        $_k['Key_name'] = 'PRIMARY';
                    } else {
                        $_k['Key_name'] = $filterFn($k, 'Key_name', [], "{$keyKind} 需要 Key_name 字段");
                    }
                    if (empty($k['Index_comment'])) {
                        $_k['Index_comment'] = '';
                    } else {
                        $_k['Index_comment'] = $filterFn($k, 'Index_comment', []);
                    }
                    if (empty($k['columns'])) {
                        throw new \Exception("{$keyKind} 必须要有 columns 字段");
                    } else {
                        $_columns = [];
                        $i = 0;
                        foreach ($k['columns'] as $column) {
                            $err_str = "{$keyKind} 第 {$i} 列";

                            $filterFn($column, 'col_name', [], $err_str);
                            $col_name = $column['col_name'];
                            $idx = $checkColumnName($col_name, $err_str);
                            $_columns[] = [
                                "col_index" => $idx,
                                "size" => "",
                            ];
                            $columns[$idx]['field_key'] = $prefix . $idx;
                            if ('primary_indexes' == $keyKind) {
                                $columns[$idx]['field_null'] = '';  // 主键 不允许为 NULL
                            }
                            ++$i;
                        }
                        $_k['columns'] = $_columns;
                    }
                    $_key[] = $_k;
                }
                $_keies[$keyKind] = $_key;
            }
        }

        return $_keies;
    }

    /**
        * 把 user_conf(用户配置) 格式 转换为 pma_conf(phpMyAdmin配置) 格式
        *
        *
        * @param    $user_conf
        *
        * @return   $pma_conf
        * <code>
        * // phpMyAdmin 格式如下
        * $pma_conf = [   // 参考：可用chrome 控制台抓一下phpMyAdmin 创建表页面的元素，以下字段使用和phpMyAdmin 创建表页面同名的字段
        *     // table 表信息
        *     'db'                  =>  'test',
        *     'table'               =>  't1',               //  数据表名
        *     'comment'             =>  '',                 //  表注释
        *     'tbl_storage_engine'  =>  'InnoDB',           //  存储引擎   可选的值为[InnoDB,  MyISAM,  ...]  @link  http://dev.mysql.com/doc/refman/5.5/en/storage-engines.html
        *     'tbl_collation'       =>  'utf8_general_ci',  //  Collation
        * 
        *     // keies 索引信息
        *     'primary_indexes'  => '[{"Key_name":"PRIMARY","Index_comment":"","Index_type":"PRIMARY","columns":[{"col_index":"0","size":""}]}]',
        *     'indexes'          => '[{"Key_name":"index索引名称","Index_comment":"注释","Index_type":"INDEX","columns":[{"col_index":"2","size":""}]}]',
        *     'unique_indexes'   => '[{"Key_name":"unique索引名称","Index_comment":"注释","Index_type":"UNIQUE","columns":[{"col_index":"1","size":""}]}]',
        *     'fulltext_indexes' => '[]',   // TODO: 暂时不支持 全文搜索 类型
        * 
        *     // columns 列信息
        *     'field_name' => [             // 名字
        *         0 => 'a',
        *         1 => 'b',
        *         2 => 'c',
        *         3 => '',
        *     ],
        *     'field_type' => [             // 类型 @link http://dev.mysql.com/doc/refman/5.5/en/data-types.html
        *         0 => 'INT',
        *         1 => 'INT',
        *         2 => 'CHAR',
        *         3 => 'INT',
        *     ],
        *     'field_length' => [           // 长度/值  是“enum”或“set”，请使用以下格式输入：'a','b','c'… 如果需要输入反斜杠(“\”)或单引号(“'”)，请在前面加上反斜杠(如 '\\xyz' 或 'a\'b')。
        *         0 => '',
        *         1 => '',
        *         2 => '33',
        *         3 => '',
        *     ],
        *     'field_default_type' => [     // 默认 可选的值为 [NONE:无, USER_DEFINED:定义, NULL:NULL, CURRENT_TIMESTAMP:CURRENT_TIMESTAMP]
        *         0 => 'NONE',
        *         1 => 'NONE',
        *         2 => 'USER_DEFINED',
        *         3 => 'NONE',
        *     ],
        *     'field_default_value' => [    // 当 field_default_type 为 USER_DEFINED 时，需要填写 field_default_value，对于默认值，请只输入单个值，不要加反斜杠或引号，请用此格式：a
        *         0 => '',
        *         1 => '',
        *         2 => '',
        *         3 => '',
        *     ],
        *     'field_collation' => [        // 排序规则 @link   http://blog.sina.com.cn/s/blog_9707fac301016wxm.html
        *         0 => '',
        *         1 => '',
        *         2 => '',
        *         3 => '',
        *     ],
        *     'field_attribute' => [        //  属性    可选的值为 ['BINARY', 'UNSIGNED', 'UNSIGNED ZEROFILL', 'on update CURRENT_TIMESTAMP']
        *         0 => 'UNSIGNED ZEROFILL',
        *         1 => '',
        *         2 => '',
        *         3 => '',
        *     ],
        *     'field_key' => [              // 索引     可选的值为 \d:表示列序号（从0数起） {none_\d:无, primary_\d: 主键, unique_\d: 唯一, index_\d: 索引}
        *         0 => 'primary_0',
        *         1 => 'none_1',
        *         2 => 'none_2',
        *         3 => 'none_3',
        *     ],
        *     'field_extra' => [            // 额外     目前只有一个值 AUTO_INCREMENT
        *         0 => 'AUTO_INCREMENT',
        *     ],
        *     'field_comments' => [         // 注释
        *         0 => '注释a',
        *         1 => '注释b',
        *         2 => '注释c',
        *         3 => '',
        *     ],
        *     'field_null' => [             // 空 allow_null
        *         1 => 'NULL',
        *     ],
        * ];
        * </code>
     */
    private function user2pma($user_conf)
    {
        $user_conf = $this->checkUserConf($user_conf);

        $table   = $user_conf['table'];
        $columns = $user_conf['columns'];
        $keies   = $user_conf['keies'];
        $support_index = ['primary_indexes', 'indexes', 'unique_indexes'];
        $support_field = [
            'field_name'          => '',
            'field_type'          => '',
            'field_length'        => '',
            'field_default_type'  => '',
            'field_default_value' => '',
            'field_collation'     => '',
            'field_attribute'     => '',
            'field_key'           => '',
            'field_extra'         => '',
            'field_comments'      => '',
            'field_null'          => '',
        ];

        $pma_conf = [
            // table 表信息
            'db'                 => $this->db->dbname,            // 数据库名
            'table'              => $table['table'],              // 数据表名
            'comment'            => $table['comment'],            // 表注释
            'tbl_storage_engine' => $table['tbl_storage_engine'], // 存储引擎 可选的值为[InnoDB, MyISAM, ...] @link http://dev.mysql.com/doc/refman/5.5/en/storage-engines.html
            'tbl_collation'      => $table['tbl_collation'],      // Collation

            // keies 索引信息
            'primary_indexes'  => '[]',
            'indexes'          => '[]',
            'unique_indexes'   => '[]',
            'fulltext_indexes' => '[]',   // TODO: 暂时不支持 全文搜索 类型

            // columns 列信息
            'field_name'           => [], // 名字
            'field_type'           => [], // 类型 @link http://dev.mysql.com/doc/refman/5.5/en/data-types.html
            'field_length'         => [], // 长度/值  是“enum”或“set”，请使用以下格式输入：'a','b','c'… 如果需要输入反斜杠(“\”)或单引号(“'”)，请在前面加上反斜杠(如 '\\xyz' 或 'a\'b')。
            'field_default_type'   => [], // 默认：可选的值为 USER_DEFINED, NULL, CURRENT_TIMESTAMP
            'field_default_value'  => [], // 当 field_default_type 为 USER_DEFINED 时，需要填写 field_default_value，对于默认值，请只输入单个值，不要加反斜杠或引号，请用此格式：a
            'field_collation'      => [], // 排序规则  @link http://blog.sina.com.cn/s/blog_9707fac301016wxm.html
            'field_attribute'      => [], // 属性：可选的值为
            'field_key'            => [], // 索引：可选的值为：\d:表示列序号（从0数起）{none_\d:无,primary_\d:主键,unique_\d:唯一,index_\d:索引}
            'field_extra'          => [], // 额外：目前只有一个值AUTO_INCREMENT
            'field_comments'       => [], // 注释
            'field_null'           => [], // 空：allow_null
        ];

        foreach ($support_index as $keyKind) {
            if ( !empty($keies[$keyKind]) ) {
                $pma_conf[$keyKind] = json_encode($keies[$keyKind], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            }
        }

        foreach ($support_field as $field => $default_value) {
            foreach ($columns as $column) {
                $pma_conf[$field][] = empty($column[$field]) ? $default_value : $column[$field];
            }
        }

        return $pma_conf;
    }

    /**
        * 把 mysql_conf(mysql表配置) 格式 转换为 user_conf(user配置) 格式
        *
        * @param    $mysql_conf
        *
        * @return   
     */
    private function mysql2user($mysql_conf)
    {
        $table   = $mysql_conf['table'];
        $keies   = $mysql_conf['keies'];
        $columns = $mysql_conf['columns'];

        $_table = [
            //'db' => 'test',
            'table'              => $table['Name'],      // 数据表名
            'comment'            => $table['Comment'],   // 表注释
            'tbl_storage_engine' => $table['Engine'],    // 存储引擎 可选的值为[InnoDB, MyISAM, ...] @link http://dev.mysql.com/doc/refman/5.5/en/storage-engines.html
            'tbl_collation'      => $table['Collation'], // Collation
        ];

        $supports = implode('|', $this->support_type);
        $pattern = "/(?<field_type>{$supports})(\((?<field_length>\d+)\))?(\s+(?<field_attribute>.*))?/";
        $_columns = [];
        foreach ($columns as $key => $value) {
            $type    = strtoupper($value['Type']);
            $match = [];
            preg_match($pattern, $type, $match);

            $field_type      = empty($match['field_type']) ? '' : $match['field_type'];
            $field_length    = empty($match['field_length']) ? '' : $match['field_length'];
            $field_attribute = empty($match['field_attribute']) ? '' : $match['field_attribute'];
            // 整形的长度只是显示长度，没有意义 http://www.cnblogs.com/lihuobao/p/5620552.html
            if (in_array($field_type, ['TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT'])) {
                $field_length = '';
            }

            $field_default_value = $value['Default'];
            if (empty($field_default_value)) {
                $field_default_type = 'NONE';
                $field_default_value = 'NONE';
            } else if (in_array($field_default_value, ['NONE', 'NULL', 'CURRENT_TIMESTAMP'])) {
                $field_default_type = $field_default_value;
            } else {
                $field_default_type = 'USER_DEFINED';
            }

            $_columns[] = [
                'field_name'          => $value['Field'],
                'field_type'          => $field_type,
                'field_length'        => $field_length,
                'field_default_type'  => $field_default_type,
                'field_default_value' => $field_default_value,
                'field_attribute'     => $field_attribute,
                'field_extra'         => strtoupper($value['Extra']),
                'field_comments'      => $value['Comment'],
                'field_null'          => 'YES'== $value['Null'] ? 'NULL' : '',                 // 空：allow_null
            ];
        }

        $checkColumnName = function ($col_name) use (&$_columns) {
            foreach ($_columns as $key => $column) {
                if ($col_name == $column['field_name']) {
                    return $key;
                }
            }
        };
        $_keies = [];
        foreach ($keies as $keyKind => $array) {
            $_array = [];
            foreach ($array as $key) {
                $cols = $key['Column_name'];
                $_cols = [];
                foreach ($cols as $col_name) {
                    $_col = [
                        'col_name' => $col_name,
                    ];
                    $_cols[] = $_col;
                }
                $_key = [
                    'Key_name'      => $key['Key_name'],
                    'Index_comment' => $key['Index_comment'],
                    'columns'       => $_cols,
                ];

                $_array[] = $_key;
            }
            $_keies[$keyKind] = $_array;
        }

        $user_conf = [
            'table'   => $_table,
            'columns' => $_columns,
            'keies'   => $_keies,
        ];

        return $user_conf;
    }

    /**
        * 把 mysql_conf(mysql表配置) 格式 转换为 pma_conf(phpMyAdmin配置) 格式
        *
        * @param    $mysql_conf
        *
        * @return   
     */
    private function mysql2pma($mysql_conf)
    {
        $user_conf = $this->mysql2user($mysql_conf);
        $pma_conf  = $this->user2pma($user_conf);

        return $pma_conf;
    }

    /**
        * column 添加
        *
        * @param    $idx        $field_name数组的索引
        *
        * @return   string $sql
     */
    private function alterColumnAdd($idx, $pma_conf)
    {
        $support_field = $this->support_column_field;
        $_pma_conf = [
            'db' => $pma_conf['db'],
            'table' => $pma_conf['table'],
            'primary_indexes' => '[]',
            'unique_indexes' => '[]',
            'indexes' => '[]',
            'fulltext_indexes' => '[]',
        ];

        foreach ($support_field as $k) {
            $_pma_conf[$k] = [
                $pma_conf[$k][$idx],
            ];
        }
        if (0 == $idx) {
            $_pma_conf['field_where'] = 'first';
        } else {
            $_pma_conf['field_where'] = 'after';
            $_pma_conf['after_field'] = $pma_conf['field_name'][$idx - 1];
        }
        return $this->buildAlterSql($_pma_conf);
    }

    /**
        * column 删除
        *
        * @param    $tableName
        * @param    $field_name
        *
        * @return   
     */
    private function alterColumnDrop($tableName, $field_name)
    {
        return "ALTER TABLE `{$tableName}` DROP `{$field_name}`;";
    }
    /**
        * column 修改
        *
        * @return   
     */
    private function alterColumn($pma_conf_L, $i_L, $pma_conf_R, $i_R)
    {
        $support_field = $this->support_column_field;
        $field_name = $pma_conf_L['field_name'][$i_L];
        $pma_conf = [
            'db' => $pma_conf_L['db'],
            'table' => $pma_conf_L['table'],
            'primary_indexes' => '[]',
            'unique_indexes' => '[]',
            'indexes' => '[]',
            'fulltext_indexes' => '[]',

            'orig_num_fields' => '1',
            'selected' => [ $field_name ],
            'field_move_to' => [ '' ],
        ];

        foreach ($support_field as $k) {
            if ('field_name' == $k) {
                $orig_k = "field_orig";
            } else {
                $orig_k = "{$k}_orig";
            }
            if ('field_key' == $k) {
                // 交给 diffPma_keies 处理
            } else {
                $pma_conf[$orig_k] = [
                    $pma_conf_R[$k][$i_R],  // 原来的值是 右 表
                ];
                $pma_conf[$k] = [
                    $pma_conf_L[$k][$i_L],  // 目标是修改为 左 表的值
                ];
            }
        }

        $db    = $pma_conf['db'];
        $table = $pma_conf['table'];

        $old_REQUEST = $_REQUEST;
        $_REQUEST = $pma_conf;
        {
            $sql_query = PMA_updateColumns($db, $table);
        }
        $_REQUEST = $old_REQUEST;   // 恢复

        return $sql_query;
    }

    private function alterKeyAdd($pma_conf, $i, $keyKind, $Index_type)
    {
        $key         =  $pma_conf[$keyKind][$i];
        $field_name  =  $pma_conf['field_name'];
        $pma_arg = [
            'db'            =>  $pma_conf['db'],
            'table'         =>  $pma_conf['table'],
            'create_index'  =>  '1',
            'index' => [
                'Key_name'       =>  $key['Key_name'],       //  索引名称
                'Index_comment'  =>  $key['Index_comment'],  //  注释
                'Index_type'     =>  $Index_type,            //  索引类型
                'columns' => [
                    'names' => [        // 字段
                    ],
                    'sub_parts' => [    // 大小
                    ],
                ],
            ],
        ];
        foreach ($key['columns'] as $c) {
            $col_name = $field_name[$c['col_index']];
            $pma_arg['index']['columns']['names'][]      =  $col_name;
            $pma_arg['index']['columns']['sub_parts'][]  =  ''; // 目前不支持自定义 sub_parts
        }

        $db     =  $pma_arg['db'];
        $table  =  $pma_arg['table'];
        $error = false;

        $old_REQUEST = $_REQUEST;
        $_REQUEST = $pma_arg;
        {
            $index = PMA_prepareFormValues($db, $table);
            $sql_query = PMA_getSqlQueryForIndexCreateOrEdit($db, $table, $index, $error);
        }
        $_REQUEST = $old_REQUEST;   // 恢复

        return $sql_query;
    }
    private function alterKeyDrop($tableName, $Key_name, $Index_type)
    {
        if ('PRIMARY' == $Index_type) {
            return "ALTER TABLE `{$tableName}` DROP PRIMARY KEY;";
        } else {
            return "ALTER TABLE `{$tableName}` DROP INDEX `{$Key_name}`;";
        }
    }
    private function alterKey($pma_conf, $i, $keyKind, $Index_type)
    {
        $key         =  $pma_conf[$keyKind][$i];
        $field_name  =  $pma_conf['field_name'];
        $pma_arg = [
            'db'            =>  $pma_conf['db'],
            'table'         =>  $pma_conf['table'],
            'old_index'     =>  $key['Key_name'],
            'index' => [
                'Key_name'       =>  $key['Key_name'],       //  索引名称
                'Index_comment'  =>  $key['Index_comment'],  //  注释
                'Index_type'     =>  $Index_type,            //  索引类型
                'columns' => [
                    'names' => [        // 字段
                    ],
                    'sub_parts' => [    // 大小
                    ],
                ],
            ],
        ];
        foreach ($key['columns'] as $c) {
            $col_name = $field_name[$c['col_index']];
            $pma_arg['index']['columns']['names'][]      =  $col_name;
            $pma_arg['index']['columns']['sub_parts'][]  =  ''; // 目前不支持自定义 sub_parts
        }

        $db     =  $pma_arg['db'];
        $table  =  $pma_arg['table'];
        $error = false;

        $old_REQUEST = $_REQUEST;
        $_REQUEST = $pma_arg;
        {
            $index = PMA_prepareFormValues($db, $table);
            $sql_query = PMA_getSqlQueryForIndexCreateOrEdit($db, $table, $index, $error);
        }
        $_REQUEST = $old_REQUEST;   // 恢复

        return $sql_query;
    }

    private function alterTable($tableName, $old_tbl_storage_engine, $pma_arg)
    {
        $sql_query = '';

        // 右表值，旧值，重构兼容
        global $auto_increment;
        if (! empty($pma_arg['tbl_storage_engine'])) {
            $GLOBALS['tbl_storage_engine'] = $pma_arg['old_tbl_storage_engine'];
        }
        $old_tbl_collation = empty($pma_arg['old_tbl_collation']) ? '' : $pma_arg['old_tbl_collation'];
        if ( ! empty($pma_arg['old_comment'])) {
            $pma_arg['prev_comment'] = $pma_arg['old_comment'];
        }
        $pack_keys = '';
        $row_format = '';
        $old_REQUEST = $_REQUEST;
        $_REQUEST = $pma_arg;

        // define some variables here, for improved syntax in the conditionals
        $is_myisam_or_aria = $is_isam = $is_innodb = $is_berkeleydb = false;
        $is_aria = $is_pbxt = false;
        if (! empty($_REQUEST['tbl_storage_engine'])
            && /*overload*/mb_strtolower($_REQUEST['tbl_storage_engine']) !== /*overload*/mb_strtolower($old_tbl_storage_engine)
        ) {
            $new_tbl_storage_engine = $_REQUEST['tbl_storage_engine'];
            list($is_myisam_or_aria, $is_innodb, $is_isam,
                $is_berkeleydb, $is_aria, $is_pbxt
            ) = PMA_setGlobalVariablesForEngine($new_tbl_storage_engine);
        } else {
            list($is_myisam_or_aria, $is_innodb, $is_isam,
                $is_berkeleydb, $is_aria, $is_pbxt
            ) = PMA_setGlobalVariablesForEngine($tbl_storage_engine);
        }

        if ($is_aria) {
            // the value for transactional can be implicit
            // (no create option found, in this case it means 1)
            // or explicit (option found with a value of 0 or 1)
            // ($transactional may have been set by libraries/tbl_info.inc.php,
            // from the $create_options)
            $transactional = (isset($transactional) && $transactional == '0')
                ? '0'
                : '1';
            $page_checksum = (isset($page_checksum)) ? $page_checksum : '';
        }

        $table_alters = array();

        $table_alters = PMA_getTableAltersArray(
            $is_myisam_or_aria, $is_isam, $pack_keys,
            (empty($checksum) ? '0' : '1'),
            $is_aria,
            ((isset($page_checksum)) ? $page_checksum : ''),
            (empty($delay_key_write) ? '0' : '1'),
            $is_innodb, $is_pbxt, $row_format,
            $new_tbl_storage_engine,
            ((isset($transactional) && $transactional == '0') ? '0' : '1'),
            $old_tbl_collation
        );

        if (count($table_alters) > 0) {
            $sql_query      = 'ALTER TABLE '
                . PMA_Util::backquote($tableName);
            $sql_query     .= " " . implode(" ", $table_alters);
            $sql_query     .= ';';
        }

        $_REQUEST = $old_REQUEST;   // 恢复
        return $sql_query;
    }


    private function echoCreateSqlHelp()
    {
        $helpStr = <<< 'EOF'
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

// 参考如下代码示例
// 参考：可用chrome 控制台抓一下phpMyAdmin 创建表页面的元素，以下字段使用和phpMyAdmin 创建表页面同名的字段
$_table = [
    //'db' => 'test',
    'table'              => 'user',            // 数据表名
    'comment'            => '用户信息',        // 表注释
    'tbl_storage_engine' => 'InnoDB',          // 存储引擎 可选的值为[InnoDB, MyISAM, ...] @link http://dev.mysql.com/doc/refman/5.5/en/storage-engines.html
    'tbl_collation'      => 'utf8_general_ci', // Collation
];
$_keies = [
    'primary_indexes' => [      // 主键
        [
            // "Key_name"      => "PRIMARY",        // 主键的名字不需要设置
            // "Index_comment" => "primary注释",    // 注释 为可选
            "columns" => [
                ["col_name" =>"id"],
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
                ]
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
];

$user_conf = [
    'table'   => $_table,
    'columns' => $_columns,
    'keies'   => $_keies,
];

$table = new Table();
$sql = $table->createSql($user_conf);

echo $sql, PHP_EOL, PHP_EOL;
EOF;
        echo $helpStr, PHP_EOL, PHP_EOL;
    }

    /**
        * 递归检查 change 数组是否为空
        *
        * @param    $change
        *
        * @return   
     */
    private function hasChange(array $change)
    {
        foreach ($change as $c) {
            if ( !empty($c) ) {
                if (is_array($c)) {
                    $r = $this->hasChange($c);
                    if ($r) {
                        return $r;
                    }
                } else {
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    private function tablesOnLocal()
    {
        static $tableNames = NULL;

        if (is_null($tableNames)) {
            $tableNames = [];
            foreach (new \DirectoryIterator($this->user_conf_path) as $fileInfo) {
                $fileName = basename($fileInfo->getPathname());
                if ( !$fileInfo->isDot() && $fileInfo->isFile() && 0 != strncmp('.', $fileName, 1) ) {
                    $tableNames[] = explode('.', $fileName)[0];
                }
            }
        }

        return $tableNames;
    }

    private function tablesOnDb()
    {
        static $tableNames = NULL;

        if (is_null($tableNames)) {
            $tableNames = [];
            $dbname = $this->db->dbname;
            $sql = "SHOW TABLE STATUS FROM `{$dbname}`;";
            $tableNames = $this->db->queryColumn($sql, 0);
        }

        return $tableNames;
    }

    public function showTableDrop()
    {
        $tableNames = array_diff($this->tablesOnDb(), $this->tablesOnLocal());
        if ($tableNames) {
            echo PHP_EOL;
            echo "-- ------------------------------------------------------------------------------", PHP_EOL;
            echo "-- 删除表", PHP_EOL;
            echo "-- ------------------------------------------------------------------------------", PHP_EOL;
            foreach ($tableNames as $tableName) {
                $pma_conf = $this->getPmaConf_fromMysql($tableName);
                $sql = $this->diffPma_table_right($pma_conf);
                echo $sql,PHP_EOL;
            }
        }
    }

    public function showTableCreate()
    {
        $tableNames = array_diff($this->tablesOnLocal(), $this->tablesOnDb());
        if ($tableNames) {
            echo PHP_EOL;
            echo "-- ------------------------------------------------------------------------------", PHP_EOL;
            echo "-- 创建表", PHP_EOL;
            echo "-- ------------------------------------------------------------------------------", PHP_EOL;
            foreach ($tableNames as $tableName) {
                $pma_conf = $this->getPmaConf_fromUser($tableName);
                $sql = $this->diffPma_table_left($pma_conf);
                echo $sql,PHP_EOL;
                echo "-- ------------------------------------------------------------------------------", PHP_EOL;
            }
        }
    }

    public function showTableDiff()
    {
        $tableNames = array_intersect($this->tablesOnLocal(), $this->tablesOnDb());
        if ($tableNames) {
            echo PHP_EOL;
            echo "-- ------------------------------------------------------------------------------", PHP_EOL;
            echo "-- 修改表", PHP_EOL;
            echo "-- ------------------------------------------------------------------------------", PHP_EOL;
            foreach ($tableNames as $tableName) {
                $pma_conf_L = $this->getPmaConf_fromUser($tableName);
                $pma_conf_R = $this->getPmaConf_fromMysql($tableName);
                $change = $this->diffPma($pma_conf_L, $pma_conf_R);
                if ($this->notEmptyChange($change)) {
                    echo "-- TABLE: {$tableName}", PHP_EOL;
                    if ($this->opt_show_create_when_diff) {
                        echo "-- LOCAL <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<", PHP_EOL;
                        $sql = $this->buildCreateSql($pma_conf_L);
                        $sql = preg_replace('/^/m', "-- ", $sql);
                        echo $sql, PHP_EOL;

                        echo "-- DB    >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>", PHP_EOL;
                        $sql = $this->buildCreateSql($pma_conf_R);
                        $sql = preg_replace('/^/m', "-- ", $sql);
                        echo $sql, PHP_EOL;
                        echo "-- ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++", PHP_EOL;
                    }
                    $this->echoChange($change);
                    echo "-- ------------------------------------------------------------------------------", PHP_EOL;
                }
            }
        }
    }

    public function showDiff()
    {
        $time = date("Y-m-d H:i:s", time());
        $book = <<< EOF
--       ___________________   ___________________
--   .-/|  78   ~~**~~      \ /      ~~**~~   79  |\-.
--   ||||                    :                    ||||
--   ||||   SELECT UPDATE    :   GROUP BY         ||||
--   ||||   DROP REPLACE     :   IN EMUN NOW      ||||
--   ||||   RENAME           :   FROM_UNIXTIME    ||||
--   ||||   GRANT ALL TO     :   FROM             ||||
--   ||||   SHOW INDEX       :   InnoDB PRIMARY   ||||
--   ||||   ALTER TABLE      :   utf8_general_ci  ||||
--   ||||   ORDER BY         :   PROCEDURE WITH   ||||
--   ||||   HAVE WHERE       :   "FUNCTION"       ||||
--   ||||                    :                    ||||
--   ||||      SQL           : chaolong.wu@qq.com ||||
--   ||||___________________ : ___________________||||
--   ||/====================\:/====================\||
--   `---------------------~___~---------------------`
-- 
--   Generate @ {$time}
-- 
--   工具目前的功能限制：
--   1): 不支持 TABLE RENAME
--   2): 不支持 TABLE 修改 AUTO_INCREMENT 值
--   3): COLLATE 只支持 utf8_general_ci
--   4): 存储引擎 只支持 InnoDB, MyISAM
--   5): 索引类型 只支持 PRIMARY, UNIQUE, INDEX
--   6): {$this->id_keep_by_app} 以下自增id为系统保留

EOF;
        echo $book, PHP_EOL;

        $this->showTableDrop();
        $this->showTableCreate();
        $this->showTableDiff();
    }

    /**
        * 从DB上的表生成用户的配置文件
        *
        * @param    $_tableNames
        *
        * @return   
     */
    public function dumpDb($_tableNames)
    {
        $opt_assume_yes = $this->opt_assume_yes;

        $format = function($tableName, $user_conf) {
            $comments = [
                'field_name'          => "\t\t\t// 名字",
                'field_type'          => "\t\t\t// 类型 @link http://dev.mysql.com/doc/refman/5.5/en/data-types.html",
                'field_length'        => "\t\t\t// 长度/值  是“enum”或“set”，请使用以下格式输入：'a','b','c'… 如果需要输入反斜杠(“\”)或单引号(“'”)，请在前面加上反斜杠(如 '\\xyz' 或 'a\'b')。",
                'field_default_type'  => "\t\t\t// 默认：可选的值为 USER_DEFINED, NULL, CURRENT_TIMESTAMP",
                'field_default_value' => "\t\t\t// 当 field_default_type 为 USER_DEFINED 时，需要填写 field_default_value，对于默认值，请只输入单个值，不要加反斜杠或引号，请用此格式：a",
                'field_attribute'     => "\t\t\t// 属性：可选的值为",
                'field_extra'         => "\t\t\t// 额外：目前只有一个值AUTO_INCREMENT",
                'field_comments'      => "\t\t\t// 注释",
                'field_null'          => "\t\t\t// 空：allow_null",
            ];
            foreach ($user_conf as $key => &$value) {
                $v = var_export($value, TRUE);
                // 转换成 php5.6 的数组格式
                $v = preg_replace('/$\s*\d+\s*=>\s*$/m', '', $v);
                $v = preg_replace('/^(\s*)array \($/m', '\\1[', $v);
                $v = preg_replace('/^(\s*)\)(,?)$/m', '\\1]\\2', $v);
                $v = preg_replace('/=>\s*$\s*\[/m', '=> [', $v);
                $v = preg_replace('/=> \[$\s*\]/m', '=> []', $v);
                // 注释一些可选的字段
                $v = preg_replace("/'field_\w+'\s*=>\s*'',?$/m", '//\\0', $v);
                // 加上字段的注释
                foreach ($comments as $field => $comment) {
                    $v = preg_replace("/'{$field}'\s*=>\s*'.*',/", '\\0 '.$comment, $v, 1);
                }
                $value = $v;
            }

            extract($user_conf);
            $t = time();
            $time = date("Y-m-d H:i:s", $t);
            $date = date("Y-m-d", $t);
            $commandLine = $this->getCommandLine();

            $tpl = <<< EOF
<?php
/*===============================================================
*   Copyright (C) 2016 All rights reserved.
*   
*   file     : {$tableName}.table.php
*   author   : php
*   date     : {$date}
*   descripe : 
*
*   modify   : {$time} 自动生成 {$commandLine}
*              需求文档 v2.5.1 xxx功能  【增加】xxx字段
*              需求文档 v2.5.7 xxx功能  【修改】xxx字段 数据类型 从 enum 改为 int
*              需求文档 v2.6.0 xxx功能  【删除】xxx字段
*
================================================================*/

// 参考如下代码示例
// 参考：可用chrome 控制台抓一下phpMyAdmin 创建表页面的元素，以下字段使用和phpMyAdmin 创建表页面同名的字段

// 表设置
\$_table = {$table};
// 索引设置
\$_keies = {$keies};
// 字段设置
\$_columns = {$columns};

\$user_conf = [
    'table'   => \$_table,
    'columns' => \$_columns,
    'keies'   => \$_keies,
];

return \$user_conf;

EOF;
            return $tpl;
        };

        $write_file = function($tableName, $php_code) use ($opt_assume_yes) {
            $path = "{$this->user_conf_path}/{$tableName}.table.php";
            @$text = file_get_contents($path);
            if ($text) {
                if ( !$opt_assume_yes ) {
                    fputs(STDOUT, "{$path} 已经存在，".PHP_EOL.$this->color_diff."\t是否覆盖？ yes/no?".$this->color_end.PHP_EOL.PHP_EOL);
                    fscanf(STDIN, "%s", $yes);
                    if ('YES' != strtoupper($yes)) {
                        return ;
                    }
                }

                $commandLine = $this->getCommandLine();
                $t = time();
                $time = date("Y-m-d H:i:s", $t);
                $insertText = "*              {$time} 自动生成 {$commandLine}".PHP_EOL;

                $pattern = '/(?<part1>\/\*=+.*modify\s*:[^\n]*\n(\*[^\n]+\n)*)(?<part2>.*=+\*\/\n)(?<part3>.*)/s';

                $matches = [];
                preg_match($pattern, $text, $matches);
                if ($matches) {
                    $text_part1 = $matches['part1'];
                    $text_part2 = $matches['part2'];

                    $matches = [];
                    preg_match($pattern, $php_code, $matches);
                    $code_part3 = $matches['part3'];
                    $php_code = '<?php'.PHP_EOL.$text_part1.$insertText.$text_part2.$code_part3;
                }
            }
            $r = file_put_contents($path, $php_code);
            if (FALSE === $r) {
                $errStr = " :( 写入文件 {$path} 出错";
                throw new \Exception($errStr);
            } else {
                echo "【写入完成】 {$path}", PHP_EOL;
            }
        };

        $tableNames = $this->tablesOnDb();
        if (empty($_tableNames)) {
            $_tableNames = $tableNames;
        }
        foreach ($_tableNames as $tableName) {
            if (in_array($tableName, $tableNames)) {
                $mysql_conf = $this->getMysqlConf($tableName);
                $user_conf = $this->mysql2user($mysql_conf);
                $php_code = $format($tableName, $user_conf);
                $write_file($tableName, $php_code);
            } else {
                echo "【DB 不存在表: 】 {$tableName}", PHP_EOL;
            }
        }
    }

    /**
        * 是否存在 自增的主键
        *
        * @param    $pma_conf
        *
        * @return   boolean
     */
    private function hasAutoIncrement($pma_conf)
    {
        $primary_indexes = json_decode($pma_conf['primary_indexes'], TRUE);
        $field_extra = $pma_conf['field_extra'];

        return $primary_indexes && 'AUTO_INCREMENT' == $field_extra[ $primary_indexes[0]["columns"][0]["col_index"] ];
    }

    private function notEmptyChange(array $change)
    {
        foreach ($change as $_prefix => $ch) {
            if ( !empty($ch) ) {
                if (is_array($ch)) {
                    $n = $this->notEmptyChange($ch);
                    if ($n) {
                        return $n;
                    }
                } else {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

    private function echoChange(array $change, $prefix = '')
    {
        foreach ($change as $_prefix => $ch) {
            if ( !empty($ch) ) {
                if (is_string($_prefix)) {
                    echo "-- {$prefix} {$_prefix}", PHP_EOL;
                }
                if (is_array($ch)) {
                    $this->echoChange($ch, $_prefix);
                } else {
                    echo $ch, PHP_EOL;
                }
            }
        }
    }

    public function test()
    {
        /*
        $r = $this->alterColumn();
        var_export($r);
        echo PHP_EOL;
        echo $r,PHP_EOL;
        return;
         */
        $pma_conf_L = $this->getPmaConf_fromUser('user');
        $pma_conf_R = $this->getPmaConf_fromMysql('user');
        $change = $this->diffPma($pma_conf_L, $pma_conf_R);
        var_export($change);
        echo PHP_EOL;
        $sql = $this->buildCreateSql($pma_conf_L);
        echo $sql,PHP_EOL;
        $sql = $this->buildCreateSql($pma_conf_R);
        echo $sql,PHP_EOL;
        //var_export($change);
        /*
        foreach ($change as $action => $c) {
            echo "-- {$action}",PHP_EOL;
            if (is_array($c)) {
                foreach ($c as $cc) {
                    echo "{$this->color_diff}{$cc}{$this->color_end}",PHP_EOL;
                }
            } else {
                echo "{$this->color_diff}{$c}{$this->color_end}",PHP_EOL;
            }
        }
        var_export($pma_conf_L);
        echo PHP_EOL;
        var_export($pma_conf_R);
         */
        echo PHP_EOL;
    }
}

//$table = new Table();
//$table->test();
//$table->showDiff();
//echo PHP_EOL,PHP_EOL;
/*
$table = new Table();
$pma_conf = $table->getPmaConf_fromUser('user');
$sql = $table->buildCreateSql($pma_conf);
echo $sql,PHP_EOL;
$pma_conf = $table->getPmaConf_fromMysql('user');
$sql = $table->buildCreateSql($pma_conf);
echo $sql,PHP_EOL;
 */


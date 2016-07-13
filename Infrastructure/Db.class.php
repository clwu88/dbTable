<?php
/*===============================================================
*   Copyright (C) 2016 All rights reserved.
*   
*   file     : Db.class.php
*   author   : clwu
*   date     : 2016-04-11
*   descripe : db 封装
*
*   modify   : 
*
================================================================*/

namespace Infrastructure;

final class Db {
    public $dbname = "test";
    protected  $vendor;             // 第三方类

    function __construct()
    {
		$this->vendor = $this->init();
    }

    public function init() {
        $host = [
            \ENV_LOCAL      => 'localhost',	// 开发环境
        ][\MING_ENV];

        $port = 3306;

        $username = [
            \ENV_LOCAL      => 'root',
        ][\MING_ENV];

        $password = [
            \ENV_LOCAL      => '',
        ][\MING_ENV];

        $dsn = "mysql:host={$host};port={$port};dbname={$this->dbname};charset=utf8";

        $db = new \PDO($dsn, $username, $password);

        // 设置为异常模式
        $db->setAttribute ( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );

        return $db;
    }

    /**
        * 查询
        *
        * @param    $sql
        * @param    $fetch_argument
        *
        * @return   array 关联数组
     */
    public function query($sql, $fetch_argument=\PDO::FETCH_ASSOC) {
        $ps = $this->vendor->query($sql);
        $rows = $ps->fetchAll($fetch_argument);
        return $rows;
    }

    /**
        * 查询某一列
        *
        * @param    $sql
        * @param    $column
        *
        * @return   array
     */
    public function queryColumn($sql, $column = 0) {
        $ps = $this->vendor->query($sql);
        $rows = [];
        while ($row = $ps->fetchColumn($column)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
        * 查询一行
        *
        * @param    $sql
        * @param    $fetch_argument
        *
        * @return   array 关联数组
     */
    public function queryOne($sql, $fetch_argument=\PDO::FETCH_ASSOC) {
        $ps = $this->vendor->query($sql);
        $row = $ps->fetch($fetch_argument);
        return $row;
    }

    /**
        * 查询一行，返回一列
        *
        * @param    $sql
        * @param    $column
        *
        * @return   
     */
    public function queryOneColumn($sql, $column = 0) {
        $ps = $this->vendor->query($sql);
        $row = $ps->fetchColumn($column);
        return $row;
    }
}

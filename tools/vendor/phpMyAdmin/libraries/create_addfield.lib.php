<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
namespace tools\vendor\phpMyAdmin;

/**
 * set of functions for tbl_create.php and tbl_addfield.php
 *
 * @package PhpMyAdmin
 */


/**
 * Transforms the radio button field_key into 4 arrays
 *
 * @return array An array of arrays which represents column keys for each index type
 */
function PMA_getIndexedColumns()
{
    $field_cnt      = count($_REQUEST['field_name']);
    $field_primary  = json_decode($_REQUEST['primary_indexes'], true);
    $field_index    = json_decode($_REQUEST['indexes'], true);
    $field_unique   = json_decode($_REQUEST['unique_indexes'], true);
    $field_fulltext = json_decode($_REQUEST['fulltext_indexes'], true);

    return array(
        $field_cnt, $field_primary, $field_index, $field_unique,
        $field_fulltext
    );
}

/**
 * Initiate the column creation statement according to the table creation or
 * add columns to a existing table
 *
 * @param int     $field_cnt      number of columns
 * @param int     &$field_primary primary index field
 * @param boolean $is_create_tbl  true if requirement is to get the statement
 *                                for table creation
 *
 * @return array  $definitions An array of initial sql statements
 *                             according to the request
 */
function PMA_buildColumnCreationStatement(
    $field_cnt, &$field_primary, $is_create_tbl = true
) {
    $definitions = array();
    for ($i = 0; $i < $field_cnt; ++$i) {
        // '0' is also empty for php :-(
        if (empty($_REQUEST['field_name'][$i])
            && $_REQUEST['field_name'][$i] != '0'
        ) {
            continue;
        }

        $definition = PMA_getStatementPrefix($is_create_tbl) .
                PMA_Table::generateFieldSpec(
                    trim($_REQUEST['field_name'][$i]),
                    $_REQUEST['field_type'][$i],
                    $i,
                    $_REQUEST['field_length'][$i],
                    $_REQUEST['field_attribute'][$i],
                    isset($_REQUEST['field_collation'][$i])
                    ? $_REQUEST['field_collation'][$i]
                    : '',
                    isset($_REQUEST['field_null'][$i])
                    ? $_REQUEST['field_null'][$i]
                    : 'NOT NULL',
                    $_REQUEST['field_default_type'][$i],
                    $_REQUEST['field_default_value'][$i],
                    isset($_REQUEST['field_extra'][$i])
                    ? $_REQUEST['field_extra'][$i]
                    : false,
                    isset($_REQUEST['field_comments'][$i])
                    ? $_REQUEST['field_comments'][$i]
                    : '',
                    $field_primary
                );

        $definition .= PMA_setColumnCreationStatementSuffix($i, $is_create_tbl);
        $definitions[] = $definition;
    } // end for

    return $definitions;
}

/**
 * Set column creation suffix according to requested position of the new column
 *
 * @param int     $current_field_num current column number
 * @param boolean $is_create_tbl     true if requirement is to get the statement
 *                                   for table creation
 *
 * @return string $sql_suffix suffix
 */
function PMA_setColumnCreationStatementSuffix($current_field_num,
    $is_create_tbl = true
) {
    // no suffix is needed if request is a table creation
    $sql_suffix = " ";
    if ($is_create_tbl) {
        return $sql_suffix;
    }

    if ($_REQUEST['field_where'] == 'last') {
        return $sql_suffix;
    }

    // Only the first field can be added somewhere other than at the end
    if ($current_field_num == 0) {
        if ($_REQUEST['field_where'] == 'first') {
            $sql_suffix .= ' FIRST';
        } else {
            $sql_suffix .= ' AFTER '
                    . PMA_Util::backquote($_REQUEST['after_field']);
        }
    } else {
        $sql_suffix .= ' AFTER '
                . PMA_Util::backquote(
                    $_REQUEST['field_name'][$current_field_num - 1]
                );
    }

    return $sql_suffix;
}

/**
 * Create relevant index statements
 *
 * @param array   $index         an array of index columns
 * @param string  $index_type    index type that which represents
 *                               the index type of $indexed_fields
 * @param boolean $is_create_tbl true if requirement is to get the statement
 *                               for table creation
 *
 * @return array an array of sql statements for indexes
 */
function PMA_buildIndexStatements($index, $index_type,
    $is_create_tbl = true
) {
    $C_cls = empty($_REQUEST['C_cls']) ? '' : $_REQUEST['C_cls'];
    $color_Key_name = ($C_cls && !empty($index['color_Key_name'])) ? $index['color_Key_name'] : '';
    $color_columns = ($C_cls && !empty($index['color_columns'])) ? $index['color_columns'] : '';
    $color_Index_comment = ($C_cls && !empty($index['color_Index_comment'])) ? $index['color_Index_comment'] : '';

    $statement = array();
    if (!count($index)) {
        return $statement;
    }

    $fields = array();
    foreach ($index['columns'] as $field) {
        $fields[]
            = PMA_Util::backquote($_REQUEST['field_name'][$field['col_index']])
            . (! empty($field['size']) ? '(' . $field['size'] . ')' : '');
    }
    $statement[] = PMA_getStatementPrefix($is_create_tbl)
        . ' ' . $color_Key_name.trim($index_type)
        . (! empty($index['Key_name']) && $index['Key_name'] != 'PRIMARY' ?
        PMA_Util::backquote($index['Key_name'])
        : '').$C_cls
        . ' (' . $color_columns.implode(', ', $fields).$C_cls . ') '
        . (! empty($index['Index_comment']) ? 'COMMENT '
        . "'" . $color_Index_comment.$index['Index_comment'].$C_cls . "' " : '');
    unset($fields);

    return $statement;
}

/**
 * Statement prefix for the PMA_buildColumnCreationStatement()
 *
 * @param boolean $is_create_tbl true if requirement is to get the statement
 *                               for table creation
 *
 * @return string $sql_prefix prefix
 */
function PMA_getStatementPrefix($is_create_tbl = true)
{
    $sql_prefix = " ";
    if (! $is_create_tbl) {
        $sql_prefix = ' ADD ';
    }
    return $sql_prefix;
}

/**
 * Merge index definitions for one type of index 
 *
 * @param array   $definitions     the index definitions to merge to
 * @param boolean $is_create_tbl   true if requirement is to get the statement
 *                                 for table creation
 * @param array   $indexed_columns the columns for one type of index
 * @param string  $index_keyword   the index keyword to use in the definition
 *
 * @return array $index_definitions 
 */
function PMA_mergeIndexStatements(
    $definitions, $is_create_tbl, $indexed_columns, $index_keyword
) {
    foreach ($indexed_columns as $index) {
        $statements = PMA_buildIndexStatements(
            $index, " " . $index_keyword . " ", $is_create_tbl
        );
        $definitions = array_merge($definitions, $statements);
    }
    return $definitions;
}

/**
 * Returns sql statement according to the column and index specifications as
 * requested
 *
 * @param boolean $is_create_tbl true if requirement is to get the statement
 * @param boolean $pretty 是否换行
 *                               for table creation
 *
 * @return string sql statement
 */
function PMA_getColumnCreationStatements($is_create_tbl = true, $pretty = FALSE)
{
    $sql_statement = "";
    list($field_cnt, $field_primary, $field_index,
            $field_unique, $field_fulltext
            ) = PMA_getIndexedColumns();
    $definitions = PMA_buildColumnCreationStatement(
        $field_cnt, $field_primary, $is_create_tbl
    );

    // Builds the PRIMARY KEY statements
    $primary_key_statements = PMA_buildIndexStatements(
        isset($field_primary[0]) ? $field_primary[0] : array(),
        " PRIMARY KEY ",
        $is_create_tbl
    );
    $definitions = array_merge($definitions, $primary_key_statements);

    // Builds the INDEX statements
    $definitions = PMA_mergeIndexStatements(
        $definitions, $is_create_tbl, $field_index, "INDEX"
    );

    // Builds the UNIQUE statements
    $definitions = PMA_mergeIndexStatements(
        $definitions, $is_create_tbl, $field_unique, "UNIQUE"
    );

    // Builds the FULLTEXT statements
    $definitions = PMA_mergeIndexStatements(
        $definitions, $is_create_tbl, $field_fulltext, "FULLTEXT"
    );

    if (count($definitions)) {
        if ($pretty) {
            $prefixSpace = '  ';
            array_walk($definitions, function (&$value, $key) use ($prefixSpace) {
                $value = $prefixSpace . trim($value);
            });
            $glue = ','.PHP_EOL;
            $sql_statement = implode($glue, $definitions);
            $sql_statement = PHP_EOL . $sql_statement . PHP_EOL;
        } else {
            $sql_statement = implode(', ', $definitions);
        }
    }
    $sql_statement = preg_replace('@, $@', '', $sql_statement);

    return $sql_statement;

}

/**
 * Function to get table creation sql query
 *
 * @param string  $db    database name
 * @param string  $table table name
 * @param boolean $pretty 是否换行
 *
 * @return string
 */
function PMA_getTableCreationQuery($db, $table, $pretty = FALSE)
{
    $C_cls = empty($_REQUEST['C_cls']) ? '' : $_REQUEST['C_cls'];

    // get column addition statements
    $sql_statement = PMA_getColumnCreationStatements(true, $pretty);

    // Builds the 'create table' statement
    $sql_query = 'CREATE TABLE ' . PMA_Util::backquote($db) . '.'
        . PMA_Util::backquote($table) . ' (' . $sql_statement . ')';

    // Adds table type, character set, comments and partition definition
    if (!empty($_REQUEST['tbl_storage_engine'])
        && ($_REQUEST['tbl_storage_engine'] != 'Default')
    ) {
        $color_tbl_storage_engine = ($C_cls && !empty($_REQUEST['color_tbl_storage_engine'])) ? $_REQUEST['color_tbl_storage_engine'] : '';
        $sql_query .= ' ENGINE = ' . $color_tbl_storage_engine.$_REQUEST['tbl_storage_engine'].$C_cls;
    }

    if (!empty($_REQUEST['auto_increment']) && intval($_REQUEST['auto_increment'])) {
        $auto_increment = intval($_REQUEST['auto_increment']);
        $color_auto_increment = ($C_cls && !empty($_REQUEST['color_auto_increment'])) ? $_REQUEST['color_auto_increment'] : '';
        $sql_query .= " {$color_auto_increment}AUTO_INCREMENT={$auto_increment}{$C_cls} ";
    }

    if (!empty($_REQUEST['tbl_collation'])) {
        $color_tbl_collation = ($C_cls && !empty($_REQUEST['color_tbl_collation'])) ? $_REQUEST['color_tbl_collation'] : '';
        $sql_query .= $color_tbl_collation.PMA_generateCharsetQueryPart($_REQUEST['tbl_collation']).$C_cls;
    }
    if (!empty($_REQUEST['comment'])) {
        $color_comment = ($C_cls && !empty($_REQUEST['color_comment'])) ? $_REQUEST['color_comment'] : '';
        $sql_query .= ' COMMENT = \''
            . $color_comment.PMA_Util::sqlAddSlashes($_REQUEST['comment']).$C_cls . '\'';
    }
    if (!empty($_REQUEST['partition_definition'])) {
        $sql_query .= ' ' . PMA_Util::sqlAddSlashes(
            $_REQUEST['partition_definition']
        );
    }
    $sql_query .= ';';

    return $sql_query;
}

/**
 * Function to get the number of fields for the table creation form
 *
 * @return int
 */
function PMA_getNumberOfFieldsFromRequest()
{
    if (isset($_REQUEST['submit_num_fields'])) {
        $num_fields = $_REQUEST['orig_num_fields'] + $_REQUEST['added_fields'];
    } elseif (isset($_REQUEST['num_fields'])
        && intval($_REQUEST['num_fields']) > 0
    ) {
        $num_fields = (int) $_REQUEST['num_fields'];
    } else {
        $num_fields = 4;
    }

    return $num_fields;
}

/**
 * Function to execute the column creation statement
 *
 * @param string $db      current database
 * @param string $table   current table
 * @param string $err_url error page url
 *
 * @return array
 */
function PMA_tryColumnCreationQuery($db, $table, $err_url = '')
{
    // get column addition statements
    $sql_statement = PMA_getColumnCreationStatements(false);

    $sql_query    = 'ALTER TABLE ' .
        PMA_Util::backquote($table) . ' ' . $sql_statement . ';';

    return $sql_query;
}
?>

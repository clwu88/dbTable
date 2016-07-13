<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
namespace tools\vendor\phpMyAdmin;
/**
 * Common functions for classes based on PMA_StringByte interface.
 *
 * @package PhpMyAdmin-String
 */

require_once __DIR__.'/StringType.int.php';

/**
 * Implements PMA_StringByte interface using native PHP functions.
 *
 * @package PhpMyAdmin-String
 */
abstract class PMA_StringAbstractType implements PMA_StringType
{
    /**
     * Checks if a number is in a range
     *
     * @param integer $num   number to check for
     * @param integer $lower lower bound
     * @param integer $upper upper bound
     *
     * @return boolean  whether the number is in the range or not
     */
    public function numberInRangeInclusive($num, $lower, $upper)
    {
        return ($num >= $lower && $num <= $upper);
    }
}
?>

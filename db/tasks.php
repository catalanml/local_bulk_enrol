<?php 
/**
 *
 * @package   local_bulk_enrol
 * @author    Lucas Catalan <catalan.munoz.l@gmail.com>
 */

$tasks = [
    [
       'classname' => 'local_bulk_enrol\task\process_trx',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '3',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    ]
];
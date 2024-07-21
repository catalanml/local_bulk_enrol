<?php

/**
 * External services.
 *
 * @package   local_bulk_enrol
 * @author     Lucas Catalan <catalan.munoz.l@gmail.com>
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'local_bulk_enrol_receive_trx' =>
    [
        'classname' => 'local_bulk_enrol\external',
        'methodname' => 'local_bulk_enrol_receive_trx',
        'classpath' => 'local/bulk_enrol/classes/external.php',
        'description' => 'Receive transaction to store in temporal table for later processing',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ],
];
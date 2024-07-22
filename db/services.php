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
    'local_bulk_enrol_send_process_result' =>
    [
        'classname' => 'local_bulk_enrol\external',
        'methodname' => 'local_bulk_enrol_send_process_result',
        'classpath' => 'local/bulk_enrol/classes/external.php',
        'description' => 'Send transaction processing result',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ],
];
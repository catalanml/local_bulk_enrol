<?php

/**
 * External API for Moodle plugin.
 *
 * @package     local_bulk_enrol
 * @category    external
 */

namespace local_bulk_enrol;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/bulk_enrol/lib.php');

use core_reportbuilder\external\columns\sort\get;
use external_api;
use external_value;
use external_single_structure;
use external_multiple_structure;
use external_function_parameters;
use stdClass;


define('UDLASENDTRXRESULT', '/api/v1.0/internal/moodle/transaction');


// Define your external API class here

class external extends external_api
{

    // Add more API functions as needed

    // Define the API functions available
    public static function local_bulk_enrol_receive_trx_parameters()
    {
        return new external_function_parameters(
            [
                'trxId' => new external_value(PARAM_RAW, 'Transaction ID', VALUE_REQUIRED),
                'trxType' => new external_value(PARAM_TEXT, 'Transaction type, student or editingteacher', VALUE_REQUIRED),
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'rut' => new external_value(PARAM_RAW, 'User RUT', VALUE_REQUIRED),
                            'firstname' => new external_value(PARAM_TEXT, 'User firstname'),
                            'lastname' => new external_value(PARAM_TEXT, 'User lastname'),
                            'email' => new external_value(PARAM_TEXT, 'User email'),
                            'courses' => new external_multiple_structure(
                                new external_value(PARAM_TEXT, 'Course shortname', VALUE_REQUIRED)
                            )

                        ]
                    ),
                    'Array of users to enrol in n courses as students or teachers, based on the trxType parameter.'
                )
            ]
        );
    }


    public static function local_bulk_enrol_receive_trx($trxId, $trxType, $data)
    {
        global $DB;

        $to_validate = [
            'trxId' => $trxId,
            'trxType' => $trxType,
            'data' => $data
        ];

        $params = (object) self::validate_parameters(self::local_bulk_enrol_receive_trx_parameters(),  $to_validate);

        $trx_packet = new stdClass();
        $trx_packet->trx_id = $params->trxId;
        $trx_packet->trx_type = $params->trxType;
        $trx_packet->status = 0;
        $trx_packet->creation_date = time();
        $trx_packet->process_date = null;

        $trx_id = $DB->insert_record('bulk_enrol_trx', $trx_packet);

        foreach ($params->data as $user) {

            $user_data = new stdClass();
            $user_data->trx_id = $trx_id;
            $user_data->rut = $user['rut'];
            $user_data->firstname = $user['firstname'];
            $user_data->lastname = $user['lastname'];
            $user_data->email = $user['email'];
            $user_data->courses = json_encode($user['courses']);
            $DB->insert_record('bulk_enrol_trx_tmp_records', $user_data);
        }

        return true;
    }

    public static function local_bulk_enrol_receive_trx_returns()
    {
        return new external_value(PARAM_BOOL, 'True if the transaction was stored successfully');
    }

    public static function local_bulk_enrol_send_process_result_parameters()
    {
        return new external_function_parameters(
            [
                'data' => new external_single_structure(
                    [
                        'trxid' => new external_value(PARAM_RAW, 'Transaction ID', VALUE_REQUIRED),
                        'data' => new external_multiple_structure(
                            new external_single_structure(
                                [
                                    'rut' => new external_value(PARAM_RAW, 'User RUT', VALUE_REQUIRED),
                                    'courses' => new external_multiple_structure(
                                        new external_value(PARAM_TEXT, 'Course shortname', VALUE_REQUIRED)
                                    )
                                ],
                                'proccesed data',
                                VALUE_OPTIONAL
                            ),
                            'proccesed data',
                            VALUE_OPTIONAL
                        ),
                        'errors' => new external_multiple_structure(
                            new external_single_structure(
                                [
                                    'rut' => new external_value(PARAM_RAW, 'User RUT', VALUE_REQUIRED),
                                    'detail' => new external_value(PARAM_TEXT, 'Error detail', VALUE_REQUIRED),
                                    'courses' => new external_multiple_structure(
                                        new external_single_structure(
                                            [
                                                'course' => new external_value(PARAM_TEXT, 'Course shortname', VALUE_REQUIRED),
                                                'detail' => new external_value(PARAM_TEXT, 'Error detail', VALUE_REQUIRED)
                                            ],
                                            'Course error',
                                            VALUE_OPTIONAL
                                        )
                                    )
                                ],
                                'User error',
                                VALUE_OPTIONAL
                            )

                        ),
                    ]
                )
            ]
        );
    }

    public static function local_bulk_enrol_send_process_result($data)
    {
        global $DB, $CFG;

        $params = (object) self::validate_parameters(self::local_bulk_enrol_send_process_result_parameters(),  $data);

        $response = json_encode([$params->data], JSON_PRETTY_PRINT);

        $trx_id = $params->data['trxid'];

        $trx_packet_sql = "SELECT * FROM {bulk_enrol_trx} WHERE trx_id = :trx_id";
        $trx_packet = $DB->get_record_sql($trx_packet_sql, ['trx_id' => $trx_id]);

        $trx_packet->status = 1;
        $trx_packet->process_date = time();


        try {
            require_once($CFG->dirroot . '/local/bulk_enrol/classes/local_bulk_enrol_curl_manager.php');

            $destiny_endpoint = get_config('bulk_enrol', 'destiny_endpoint');
            $token = get_config('bulk_enrol', 'current_token');

            if (!$destiny_endpoint) {
                $destiny_endpoint = get_config('gradabledatasender', 'destiny_endpoint');
            }

            if (!$token) {
                $token = get_config('gradabledatasender', 'current_token');
            }

            $curl = new \local_bulk_enrol_curl_manager();


            $headers[] = 'Content-Type: application/json';

            $wsresult = $curl->make_request(
                $destiny_endpoint . UDLASENDTRXRESULT,
                'POST',
                [$response],
                $headers,
                'bearer',
                $token

            );

            if ($wsresult->remote_endpoint_status === 200) {
                $curl->close();
                $DB->update_record('bulk_enrol_trx', $trx_packet);
                return true;
            } else {
                $curl->close();
                return false;
            }
        } catch (\Throwable $th) {
            return false;  
        }
    }

    public static function local_bulk_enrol_send_process_result_returns()
    {
        return new external_value(PARAM_BOOL, 'True if the result was sended, false otherwise');
    }
}

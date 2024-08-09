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
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'trxid' => new external_value(PARAM_RAW, 'Transaction ID', VALUE_REQUIRED),
                            'errors' => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'rut' => new external_value(PARAM_RAW, 'User RUT', VALUE_OPTIONAL),
                                        'detail' => new external_value(PARAM_RAW, 'Error detail', VALUE_OPTIONAL),
                                        'courses' => new external_multiple_structure(
                                            new external_single_structure(
                                                [
                                                    'course' => new external_value(PARAM_RAW, 'Course shortname', VALUE_OPTIONAL),
                                                    'detail' => new external_value(PARAM_RAW, 'Error detail', VALUE_OPTIONAL)
                                                ],
                                                'Course error',
                                                VALUE_OPTIONAL
                                            ),
                                            'Array of courses with errors',
                                            VALUE_OPTIONAL,
                                            []
                                        )
                                    ],
                                    'User error',
                                    VALUE_OPTIONAL
                                )
                            ),
                            'data' => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'rut' => new external_value(PARAM_RAW, 'User RUT', VALUE_OPTIONAL),
                                        'courses' => new external_multiple_structure(
                                            new external_value(PARAM_RAW, 'Course shortname', VALUE_OPTIONAL),
                                            'Array of courses',
                                            VALUE_OPTIONAL,
                                            []
                                        )
                                    ],
                                    'Processed data',
                                    VALUE_OPTIONAL
                                ),
                                'Processed data array',
                                VALUE_OPTIONAL
                            )
                        ]
                    )
                )
            ]
        );
    }
    

    public static function local_bulk_enrol_send_process_result($data)
    {
        global $DB, $CFG;
    
        // Validate and decode the parameters
        $params = self::validate_parameters(self::local_bulk_enrol_send_process_result_parameters(), $data);
    
        // Extract the transaction ID from the params
        $trx_id = $params['data'][0]['trxid'];
    
        // Retrieve the transaction record from the database
        $trx_packet_sql = "SELECT * FROM {bulk_enrol_trx} WHERE trx_id = :trx_id";
        $trx_packet = $DB->get_record_sql($trx_packet_sql, ['trx_id' => $trx_id]);
    
        // Update the transaction packet with the status and processing date
        $trx_packet->status = 1;
        $trx_packet->process_date = time();
    
        try {
            // Include the cURL manager
            require_once($CFG->dirroot . '/local/bulk_enrol/classes/local_bulk_enrol_curl_manager.php');
    
            // Get the destination endpoint and token from configuration
            $destiny_endpoint = get_config('bulk_enrol', 'destiny_endpoint') ?: get_config('gradabledatasender', 'destiny_endpoint');
            $token = get_config('bulk_enrol', 'current_token') ?: get_config('gradabledatasender', 'current_token');
    
            // Initialize the cURL manager
            $curl = new \local_bulk_enrol_curl_manager();
    
            // Prepare the headers for the request
            $headers = ['Content-Type: application/json'];
    
            // Make the request to the external service
            $wsresult = $curl->make_request(
                $destiny_endpoint . UDLASENDTRXRESULT,
                'POST',
                [$data],
                $headers,
                'bearer',
                $token
            );
    
            // Close the cURL session
            $curl->close();
    
            // Check the response status and update the transaction record if successful
            if ($wsresult->remote_endpoint_status === 200) {
                $DB->update_record('bulk_enrol_trx', $trx_packet);
                return true;
            } else {
                return false;
            }
        } catch (\Throwable $th) {
            // Log or handle the exception
            return false;
        }
    }
    
    

    public static function local_bulk_enrol_send_process_result_returns()
    {
        return new external_value(PARAM_BOOL, 'True if the result was sended, false otherwise');
    }
}

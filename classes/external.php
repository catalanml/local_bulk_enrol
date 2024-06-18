<?php

/**
 * External API for Moodle plugin.
 *
 * @package     local_bulk_enrol
 * @category    external
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/bulk_enrol/lib.php');



// Define your external API class here

class local_bulk_enrol_external extends external_api
{

    // Add more API functions as needed

    // Define the API functions available
    public static function bulk_enrol_users_parameters()
    {
        return new external_function_parameters(
            [
                'trxId' => new external_value(PARAM_RAW, 'Transaction ID', VALUE_REQUIRED),
                'trxType' => new external_value(PARAM_TEXT, 'Transaction type, student or editingteacher', VALUE_REQUIRED),
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        [
                            'rut' => new external_value(PARAM_RAW, 'User RUT', VALUE_REQUIRED),
                            'fisrtname' => new external_value(PARAM_TEXT, 'User firstname'),
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

    // Define your API functions here

    /**
     * Example API function.
     *
     * @param string $trxId The transaction ID.
     * @param string $trxType The transaction type.
     * @param array $data The data to process.
     * @return void 
     */

    public static function bulk_enrol_users($trxId, $trxType, $data)
    {
        global $DB;
        
        $to_validate = [
            'trxId' => $trxId,
            'trxType' => $trxType,
            'data' => $data
        ];

        $params = (object) self::validate_parameters(self::bulk_enrol_users_parameters(), ['trxId', 'trxType', 'data']);

        foreach ($data as $user_obj){
            process_user($user_obj);
        }

        
    }



    public static function enrol_users_returns()
    {
        return [];
    }
}

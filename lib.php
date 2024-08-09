<?php


/**
 *
 * @package   local_bulk_enrol
 * @author    Lucas Catalan <catalan.munoz.l@gmail.com>
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . "/local/bulk_enrol/classes/external.php");

use core_reportbuilder\external\filters\set;
use local_bulk_enrol\external;


global $CFG;
define('DEFAULT_AUTH', 'auth_ldap');

if (file_exists("$CFG->dirroot/local/gradabledatasender/version.php")) {
    define('UDLALOGINWS', '/api/v1.0/login');
    define('UDLASENDQUIZ', '/api/v1.0/internal/moodle');
}



defined('MOODLE_INTERNAL') || die;



function local_bulk_enrol_refresh_token()
{
    global $CFG;
    $curl = new \local_bulk_enrol_curl_manager();

    if (!file_exists($CFG->dirroot . '/local/gradabledatasender/version.php')) {
        $endpoint_username = get_config('bulk_enrol', 'endpoint_username');
        $endpoint_password = get_config('bulk_enrol', 'endpoint_password');
        $destiny_endpoint = get_config('bulk_enrol', 'destiny_endpoint');
    } else {
        $endpoint_username = get_config('gradabledatasender', 'endpoint_username');
        $endpoint_password = get_config('gradabledatasender', 'endpoint_password');
        $destiny_endpoint = get_config('gradabledatasender', 'destiny_endpoint');

        set_config('endpoint_username', $endpoint_username, 'bulk_enrol');
        set_config('endpoint_password', $endpoint_password, 'bulk_enrol');
        set_config('destiny_endpoint', $destiny_endpoint, 'bulk_enrol');
    }

    $data = [
        'username' => $endpoint_username,
        'password' => $endpoint_password,
    ];

    $headers[] = 'Content-Type: application/json';

    try {
        $wsresult = $curl->make_request(
            $destiny_endpoint . UDLALOGINWS,
            'POST',
            $data,
            $headers
        );

        if ($wsresult->remote_endpoint_status === 200) {
            $curl->close();
            return $wsresult->remote_endpoint_response->data->accessToken;
        } else {
            $curl->close();
            return false;
        }
    } catch (\Throwable $th) {
        return false;
    }
}


function process_user($user_data, $user_role)
{
    global $DB;

    $user = new stdClass();
    $user->username = strtolower($user_data->rut); // Ensure username is lowercase
    $user->password = $user_data->rut;
    $user->firstname = $user_data->firstname;
    $user->lastname = $user_data->lastname;
    $user->email = $user_data->email;

    $data = [];
    $errors = [];

    // Check if the user exists or create a new one
    if (!$user_id = $DB->get_field('user', 'id', ['username' => $user->username])) {
        try {
            $user_id = user_create_user($user);
            $data['rut'] = $user_data->rut;  // Add user to data if successfully created
        } catch (Exception $e) {
            $errors[] = [
                'rut' => $user_data->rut, 
                'detail' => $e->getMessage(),
                'courses' => []
            ];
            return ['data' => $data, 'errors' => $errors];
        }
    } else {
        // User already exists, add user to data
        $data['rut'] = $user_data->rut;
    }

    // Validate user role
    $valid_roles = ['student', 'editingteacher'];
    if (!in_array($user_role, $valid_roles)) {
        $errors[] = [
            'rut' => $user_data->rut,
            'detail' => 'Invalid role',
            'courses' => []
        ];
        return ['data' => $data, 'errors' => $errors];
    }

    $role_id = $DB->get_field('role', 'id', ['shortname' => $user_role]);
    if (!$role_id) {
        $errors[] = [
            'rut' => $user_data->rut,
            'detail' => 'Invalid role',
            'courses' => []
        ];
        return ['data' => $data, 'errors' => $errors];
    }

    $enrol = enrol_get_plugin('manual');
    if (empty($enrol)) {
        $errors[] = [
            'rut' => $user_data->rut,
            'detail' => 'Manual plugin not installed',
            'courses' => []
        ];
        return ['data' => $data, 'errors' => $errors];
    }

    // Initialize course errors array
    $course_errors = [];
    $success_courses = [];

    // Enroll user to courses
    foreach ($user_data->courses as $course) {
        try {
            $course_record = $DB->get_record('course', ['shortname' => $course], 'id');
            if (!$course_record) {
                throw new Exception('Course not found');
            }

            $enrolinstances = enrol_get_instances($course_record->id, true);
            $validinstance = null;

            foreach ($enrolinstances as $courseenrolinstance) {
                if ($courseenrolinstance->enrol == 'manual') {
                    $validinstance = $courseenrolinstance;
                    break;
                }
            }

            if (empty($validinstance)) {
                throw new moodle_exception('noenrolments', 'enrol_manual');
            }

            // Enroll the user in the course
            $enrol->enrol_user($validinstance, $user_id, $role_id, 0, 0, ENROL_USER_ACTIVE);
            $success_courses[] = $course;
        } catch (Exception $e) {
            $course_errors[] = [
                'course' => $course,
                'detail' => $e->getMessage()
            ];
        }
    }

    // Update data with successful course enrollments
    if (!empty($success_courses)) {
        $data['courses'] = $success_courses;
    }

    // If there were course errors, add them to the errors array
    if (!empty($course_errors)) {
        $errors[] = [
            'rut' => $user_data->rut,
            'detail' => 'Courses not found',
            'courses' => $course_errors
        ];
    }

    return ['data' => $data, 'errors' => $errors];
}

function testcore()
{
    global $DB;

    // Get pending transactions to process
    $pending_transactions = $DB->get_records('bulk_enrol_trx', ['status' => 0]);

    foreach ($pending_transactions as $pending_transaction) {
        // Initialize response array with trxid as required
        $transaction_result = [
            'trxid' => $pending_transaction->trx_id,
            'errors' => [],
            'data' => [],
        ];

        // Get records associated with the current transaction
        $transactions = $DB->get_records('bulk_enrol_trx_tmp_records', ['trx_id' => $pending_transaction->id]);
        
        foreach ($transactions as $transaction) {
            $user_data = new stdClass();
            $user_data->rut = $transaction->rut;
            $user_data->firstname = $transaction->firstname;
            $user_data->lastname = $transaction->lastname;
            $user_data->email = $transaction->email;
            $user_data->courses = json_decode($transaction->courses);
            $user_role = $pending_transaction->trx_type;

            // Process each user
            $result = process_user($user_data, $user_role);

            // Append the processed data and errors to the response
            if (!empty($result['data']['courses'])) { // Check if there are successful course enrollments or user creation
                $transaction_result['data'][] = $result['data'];
            }
            if (!empty($result['errors'])) {
                // Add errors directly to the errors array
                $transaction_result['errors'] = array_merge($transaction_result['errors'], $result['errors']);
            }
        }

        // Prepare the data structure according to the expected format
        $data = ['data' => [$transaction_result]];

        try {
            var_dump(external::local_bulk_enrol_send_process_result($data));
        } catch (Exception $e) {
            // Log error
            print_object($e);
        }
    }
}

<?php


/**
 *
 * @package   local_bulk_enrol
 * @author    Lucas Catalan <catalan.munoz.l@gmail.com>
 */

 define('DEFAULT_AUTH', 'auth_ldap');
 define('UDLALOGINWS', '/api/v1.0/login');
 define('UDLASENDQUIZ', '/api/v1.0/internal/moodle');
 
 
 defined('MOODLE_INTERNAL') || die;
 
 function process_user($user_data, $user_role) {
     global $DB;
 
     $user = new stdClass();
     $user->auth = DEFAULT_AUTH;
     $user->username = $user_data->rut;
     $user->password = $user_data->rut;
     $user->firstname = $user_data->firstname;
     $user->lastname = $user_data->lastname;
     $user->email = $user_data->email;
 
     // Check if the user exists or create a new one
     if (!$user_id = $DB->get_field('user', 'id', ['username' => $user->username])) {
         $user_id = user_create_user($user);
     }
 
     // Validate user role
     $valid_roles = ['student', 'editingteacher'];
     if (!in_array($user_role, $valid_roles)) {
         throw new Exception('Invalid role');
     }
 
     $role_id = $DB->get_field('role', 'id', ['shortname' => $user_role]);
     if (!$role_id) {
         throw new Exception('Role not found');
     }
 
     $enrol = enrol_get_plugin('manual');
     if (empty($enrol)) {
         throw new moodle_exception('manualpluginnotinstalled', 'enrol_manual');
     }
 
     // Enroll user to courses
     foreach ($user_data->courses as $course) {
         $course_id = $DB->get_field('course', 'id', ['shortname' => $course]);
         if (!$course_id) {
             throw new Exception('Course not found');
         }
 
         $enrolinstances = enrol_get_instances($course_id, true);
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
 
         $enrol->enrol_user($validinstance, $user_id, $role_id, 0, 0, ENROL_USER_ACTIVE);
     }
 
     return true;
 }
 
 function local_bulk_enrol_refresh_token() {
     $curl = new \local_bulk_enrol_curl_manager();
 
     $destiny_endpoint = get_config('gradabledatasender', 'destiny_endpoint');
     $endpoint_username = get_config('gradabledatasender', 'endpoint_username');
     $endpoint_password = get_config('gradabledatasender', 'endpoint_password');
 
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
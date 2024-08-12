<?php

/**
 *
 * @package   local_bulk_enrol
 * @author    Lucas Catalan <catalan.munoz.l@gmail.com>
 */

defined('MOODLE_INTERNAL') || die();

namespace local_bulk_enrol\task;
use local_bulk_enrol\external;

require_once($CFG->dirroot . '/local/bulk_enrol/lib.php');
require_once($CFG->dirroot . '/local/bulk_enrol/classes/external.php');

class process_trx extends \core\task\scheduled_task
{
    /**
     * Get task name
     * @return string
     * @throws coding_exception
     */
    public function get_name()
    {
        return get_string($this->stringname, 'local_bulk_enrol');
    }

    /** @var string $stringname */
    protected $stringname = 'refresh_token_cron';

    /**
     * Execute task
     */
    public function execute()
    {
        global $DB;

        try {
            // Get token from gradable datasender config (cross dependency in UDLA)
            $current_token = get_config('bulk_enrol', 'current_token');

            if (($current_token !== false)) {
                $token = local_bulk_enrol_refresh_token();
                set_config('current_token', $token, 'bulk_enrol');
                mtrace('Token updated');

                $pending_transactions = $DB->get_records('bulk_enrol_trx', ['status' => 0]);

                // Read pending transactions to process
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
                        $user_data = new \stdClass();
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
                    $data = $transaction_result;

                    // Send data to the external service
                    external::local_bulk_enrol_send_process_result($data);
                }
                return true;

            }
        } catch (\Throwable $th) {
            mtrace('Error in external endpoint');
            mtrace($th->getMessage());
            return false;
        }
    }
}

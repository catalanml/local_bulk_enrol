<?php

/**
 *
 * @package   local_bulk_enrol
 * @author    Lucas Catalan <catalan.munoz.l@gmail.com>
 */

namespace local_bulk_enrol\task;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/bulk_enrol/lib.php');
require_once($CFG->dirroot . "/local/bulk_enrol/classes/external.php");



use local_bulk_enrol\external;

class cron_task extends \core\task\scheduled_task
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
                    // Initialize response array
                    $transaction_result = [
                        'trxid' => $pending_transaction->trx_id,
                        'data' => [],
                        'errors' => [],
                    ];

                    $transactions = $DB->get_records('bulk_enrol_trx_tmp_records', ['trx_id' => $pending_transaction->id]);

                    foreach ($transactions as $transaction) {
                        $user_data = new \stdClass();
                        $user_data->rut = $transaction->rut;
                        $user_data->firstname = $transaction->firstname;
                        $user_data->lastname = $transaction->lastname;
                        $user_data->email = $transaction->email;
                        $user_data->courses = json_decode($transaction->courses);
                        $user_role = $pending_transaction->trx_type;

                        $result = process_user($user_data, $user_role);

                        $transaction_result['data'][] = $result['data'];
                        if (!empty($result['errors'])) {
                            $transaction_result['errors'][] = $result['errors'];
                        }
                    }


                    $data = ['data' => $transaction_result];


                    //external::local_bulk_enrol_send_process_result($data);

                    return true;
                }
            }
        } catch (\Throwable $th) {
            mtrace('Error in external endpoint');
            return false;
        }
    }
}

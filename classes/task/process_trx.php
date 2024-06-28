<?php

/**
 *
 * @package   local_bulk_enrol
 * @author    Lucas Catalan <catalan.munoz.l@gmail.com>
 */

namespace local_bulk_enrol\task;



defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/bulk_enrol/lib.php');
require_once($CFG->dirroot . "/local/bulk_enrol/classes/external.php");


use context_system;
use Exception;
use stdClass;
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
        global $DB, $CFG;
        try {
            // Get token from gradable datasender config (cross dependency in UDLA)
            $current_token = get_config('gradabledatasender', 'current_token');

            if (($current_token !== false)) {
                $token = refresh_token();
                set_config('current_token', $token, 'gradabledatasender');
                mtrace('Token updated');

                $pending_transactions_ids = $DB->get_records('bulk_enrol_trx', ['status' => 0]);

                // Read pending transactions to process
                foreach ($pending_transactions_ids as $pending_transaction_id) {

                    // Initialize response array
                    $transaction_result = [
                        'trxid' => $pending_transaction_id->trx_id,
                        'errors' => [],
                        'data' => []
                    ];

                    $transactions = $DB->get_records('bulk_enrol_trx_tmp_records', ['trx_id' => $pending_transaction_id->trx_id]);


                    foreach ($transactions as $transaction) {
                        $user_data = $transaction->user_data;
                        $user_role = $transaction->user_role;


                        try {
                            if (process_user($user_data, $user_role)) {
                                $transaction_result['data'][] = [
                                    'rut' => $user_data->rut,
                                    'courses' => $user_data->courses
                                ];
                            }
                        } catch (Exception $e) {
                            $error_detail = [
                                'rut' => $user_data->rut,
                                'detail' => $e->getMessage(),
                                'courses' => []
                            ];

                            foreach ($user_data->courses as $course) {
                                $error_detail['courses'][] = [
                                    'name' => $course,
                                    'detail' => $e->getMessage()
                                ];
                            }

                            $transaction_result['errors'][] = $error_detail;
                        }

                        $response[] = $transaction_result;
                    }

                    // Convert response array to JSON and store or log it as needed
                    $response_json = json_encode($response, JSON_PRETTY_PRINT);

                    //external::local_bulk_enrol_send_process_result($response_json);

                    return true;
                }
            }
        } catch (\Throwable $th) {
            mtrace('Error in external endpoint');
            return false;
        }
    }
}

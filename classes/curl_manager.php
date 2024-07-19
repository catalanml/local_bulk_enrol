<?php

/**
 * Curl Manager Class
 *
 * @package    local_bulk_enrol
 * @copyright  2024 Lucas Catalan
 * @author     Lucas Catalan <catalan.munoz.l@gmail.com>
 */

class local_bulk_enrol_curl_manager {
    private $curl;

    public function __construct() {
        $this->curl = curl_init();
    }

    /**
     * Makes an HTTP request using cURL.
     * 
     * This method supports various HTTP methods like GET and POST, and allows for additional
     * headers, including optional authentication headers. It is capable of handling 
     * different authentication methods (Bearer, Basic, Token).
     * 
     * @param string $url The URL to make the request to.
     * @param string $method The HTTP method to use ('GET', 'POST', etc.). Default is 'GET'.
     * @param array $data The data to be sent with the request. For GET requests, it's used as query parameters. For POST requests, it's sent as the request body.
     * @param array $headers Optional. Additional headers to be sent with the request.
     * @param string|null $authMethod Optional. The authentication method to use ('bearer', 'basic', 'token').
     * @param string|null $authToken Optional. The authentication token to be used with the selected authentication method.
     * @param bool $plain_remote_response Optional. Whether to return the plain remote response. Default is false.
     * @return object Enriched response from the URL.
     * @throws Exception If there is a cURL error or an HTTP error status code is returned.
     */
    public function make_request(
        string $url,
        string $method = 'GET',
        array $data = [],
        array $headers = [],
        ?string $authMethod = null,
        ?string $authToken = null,
        bool $plain_remote_response = false
    ): stdClass {
        $response = new stdClass();

        $response->remote_endpoint_status = 0;
        $response->remote_endpoint_error = false;
        $response->remote_endpoint_response = null;
        $response->message = '';

        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_ENCODING, '');
        curl_setopt($this->curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        // Handle different authentication methods
        if (!is_null($authMethod) && !is_null($authToken)) {
            switch (strtolower($authMethod)) {
                case 'bearer':
                    $headers[] = "Authorization: Bearer $authToken";
                    break;
                case 'basic':
                    $headers[] = "Authorization: Basic " . base64_encode($authToken);
                    break;
                default:
                    break;
            }
        }

        // Set headers
        if (!empty($headers)) {
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
        }

        if ($method == 'POST') {
            curl_setopt($this->curl, CURLOPT_POST, true);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($data));
        }

        if ($method == 'GET' && !empty($data)) {
            curl_setopt($this->curl, CURLOPT_URL, $url . '?' . http_build_query($data));
        }

        // Execute request
        $wsresponse = curl_exec($this->curl);
        $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);

        $response->remote_endpoint_response = $wsresponse;
        $response->remote_endpoint_status = $httpCode;

        if (curl_errno($this->curl)) {
            $response->remote_endpoint_error = true;
            $response->message = 'Curl Error: ' . curl_error($this->curl);
        }

        if ($httpCode >= 400) {
            $response->remote_endpoint_error = true;
            $response->message = 'HTTP Request Failed with Status Code ' . $httpCode;
        }

        if (!$plain_remote_response && !$response->remote_endpoint_error) {
            $response->remote_endpoint_response = json_decode($response->remote_endpoint_response);
        }

        return $response;
    }

    public function close(): void {
        curl_close($this->curl);
    }

    // Destructor to ensure the curl resource is freed.
    public function __destruct() {
        $this->close();
    }
}
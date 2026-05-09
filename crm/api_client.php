<?php
// crm/api_client.php
require_once 'config.php';

/**
 * Fetch a list of records from the remote API
 */
function fetchFromApi($type, $page = 1, $search = '', $extra = []) {
    $endpoint = ($type === 'investor') ? "/api_investors.php" : "/api.php";
    
    $params = [
        'action' => 'list',
        'type' => $type,
        'page' => $page,
        'search' => $search
    ];
    
    // Merge extra params
    foreach ($extra as $key => $val) {
        if ($val !== '') $params[$key] = $val;
    }
    
    $url = API_BASE_URL . $endpoint . "?" . http_build_query($params);
    return callApi($url);
}

/**
 * Fetch a single record by ID from the remote API
 */
function fetchSingleFromApi($type, $id) {
    $endpoint = ($type === 'investor') ? "/api_investors.php" : "/api.php";
    $url = API_BASE_URL . $endpoint . "?action=details&type=$type&id=$id";
    return callApi($url);
}

/**
 * Fetch multiple records by IDs from the remote API
 */
function fetchBulkFromApi($type, $ids) {
    if (empty($ids)) return ['status' => 'success', 'data' => []];
    $ids_str = implode(',', $ids);
    $endpoint = ($type === 'investor') ? "/api_investors.php" : "/api.php";
    $url = API_BASE_URL . $endpoint . "?action=bulk&type=$type&ids=$ids_str";
    return callApi($url);
}

/**
 * Validate if an ID exists in the master database
 */
function validateIdFromApi($type, $id) {
    $endpoint = ($type === 'investor') ? "/api_investors.php" : "/api.php";
    $url = API_BASE_URL . $endpoint . "?action=validate&type=$type&id=$id";
    return callApi($url);
}

/**
 * Base function to call the API using cURL
 */
function callApi($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'status' => 'error',
            'message' => 'API Connection Error: ' . $error,
            'data' => [],
            'total' => 0
        ];
    }
    
    $decoded = json_decode($response, true);
    if (!$decoded) {
        return [
            'status' => 'error',
            'message' => 'Invalid API Response from ' . $url,
            'data' => [],
            'total' => 0
        ];
    }
    
    return $decoded;
}
?>

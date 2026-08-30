<?php
require_once 'Zkteco.php';
header('Content-Type: application/json');
$zk = new Zkteco();
$zk->initialize([
    'ip' => "192.168.0.245",
    'port' => 4370
]);
if ($zk->connect()) {
    $machine_time =  $zk->getUser();
    $response = [
        'status' => true,
        'message' => 'Attendance retrieved successfully',
        'data' => $machine_time
    ];
    $zk->disconnect();
} else {
    $response = ['message' => 'Failed to connect to the machine'];
}
//echo json_encode($response);
print_r('<pre>');
print_r($response);
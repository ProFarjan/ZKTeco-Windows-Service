<?php
require_once 'Zkteco.php';

$zk = new Zkteco();
$zk->initialize(['ip' => '192.168.0.100', 'port' => 4370, 'protocol' => 'TCP', 'password' => '246810']);

// Connect raw
$command = ZkUtil::CMD_CONNECT;
$buf = ZkUtil::createHeader($command, 0, 0, 65534, '');
$zk->zk_send($buf);
$raw = $zk->zk_recv(1024);
if (strlen($raw) >= 8) {
    $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($raw, 0, 8));
    $session_id = hexdec($u['h6'] . $u['h5']);
    $reply_id = hexdec($u['h8'] . $u['h7']);
    $cmd_resp = hexdec($u['h2'] . $u['h1']);
    echo "Connect Response: $cmd_resp (Session: $session_id)\n";

    if ($cmd_resp == 6001 || $cmd_resp == 2005) {
        $payload = ZkUtil::makeCommKey('246810', $session_id);
        $buf = ZkUtil::createHeader(1102, 0, $session_id, $reply_id, $payload);
        $zk->zk_send($buf);
        $raw = $zk->zk_recv(1024);
        if (strlen($raw) >= 8) {
            $u = unpack('H2h1/H2h2', substr($raw, 0, 8));
            echo "Auth Response: " . hexdec($u['h2'] . $u['h1']) . "\n";
        } else {
            echo "Auth Response: No Data\n";
        }
    }
} else {
    echo "Connect Response: No Data / Timeout\n";
}

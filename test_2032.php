<?php
require_once 'Zkteco.php';

$zk = new Zkteco();
$zk->initialize(['ip' => '192.168.0.100', 'port' => 4370, 'protocol' => 'TCP']);
$zk->_zkclient = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($zk->_zkclient, $zk->_ip, $zk->_port);

// raw connect
$command = ZkUtil::CMD_CONNECT;
$buf = ZkUtil::createHeader($command, 0, 0, 65534, '');
$zk->zk_send($buf);
$raw = $zk->zk_recv(1024);
$u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($raw, 0, 8));
$session_id = hexdec($u['h6'] . $u['h5']);
$reply_id = hexdec($u['h8'] . $u['h7']);
echo "Connect returned: " . hexdec($u['h2'] . $u['h1']) . "\n";

// try to get time without auth
$buf = ZkUtil::createHeader(201, 0, $session_id, $reply_id, '');
$zk->zk_send($buf);
$raw = $zk->zk_recv(1024);
if(strlen($raw) >= 8) {
    $u = unpack('H2h1/H2h2', substr($raw, 0, 8));
    echo "GetTime without auth returned: " . hexdec($u['h2'] . $u['h1']) . "\n";
} else {
    echo "No response for GetTime\n";
}

// now try auth
$payload = ZkUtil::makeCommKey(111111, $session_id);
$buf = ZkUtil::createHeader(1102, 0, $session_id, $reply_id+1, $payload);
$zk->zk_send($buf);
$raw = $zk->zk_recv(1024);
if(strlen($raw) >= 8) {
    $u = unpack('H2h1/H2h2', substr($raw, 0, 8));
    echo "Auth returned: " . hexdec($u['h2'] . $u['h1']) . "\n";
}

// get time after auth
$buf = ZkUtil::createHeader(201, 0, $session_id, $reply_id+2, '');
$zk->zk_send($buf);
$raw = $zk->zk_recv(1024);
if(strlen($raw) >= 8) {
    $u = unpack('H2h1/H2h2', substr($raw, 0, 8));
    echo "GetTime after auth returned: " . hexdec($u['h2'] . $u['h1']) . "\n";
}


<?php

class ZkConnect
{
    static public function connect($self)
    {
        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_CONNECT;
        $command_string = '';
        $chksum = 0;
        $session_id = 0;
        $reply_id = -1 + ZkUtil::USHRT_MAX;

        $buf = ZkUtil::createHeader($command, $chksum, $session_id, $reply_id, $command_string);

        $self->zk_send($buf);

        try {
            $self->_data_recv = $self->zk_recv(1024);
            if (strlen($self->_data_recv) > 0) {
                $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr($self->_data_recv, 0, 8));

                $session = hexdec($u['h6'] . $u['h5']);
                if (empty($session)) {
                    return false;
                }

                $self->_session_id = $session;

                $command_resp = hexdec($u['h2'] . $u['h1']);
                if ($command_resp == ZkUtil::CMD_ACK_UNAUTH || $command_resp == 6001) {
                    $auth_payload = ZkUtil::makeCommKey($self->_password, $session);
                    $self->_command(ZkUtil::CMD_AUTH, $auth_payload);
                }

                return ZkUtil::checkValid($self->_data_recv);
            } else {
                return false;
            }
        } catch (ErrorException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    static public function disconnect($self)
    {
        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_EXIT;
        $command_string = '';
        $chksum = 0;
        $session_id = $self->_session_id;

        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($self->_data_recv, 0, 8));
        $reply_id = hexdec($u['h8'] . $u['h7']);

        $buf = ZkUtil::createHeader($command, $chksum, $session_id, $reply_id, $command_string);

        $self->zk_send($buf);
        try {
            $self->_data_recv = $self->zk_recv(1024);

            $self->_session_id = 0;
            return ZkUtil::checkValid($self->_data_recv);
        } catch (ErrorException $e) {
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
}
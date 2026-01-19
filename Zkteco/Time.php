<?php

class ZkTime
{
    static public function set($self, $t)
    {
        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_SET_TIME;
        $command_string = pack('I', ZkUtil::encodeTime($t));

        return $self->_command($command, $command_string);
    }

    static public function get($self)
    {
        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_GET_TIME;
        $command_string = '';

        $ret = $self->_command($command, $command_string);

        if ($ret) {
            return ZkUtil::decodeTime(hexdec(ZkUtil::reverseHex(bin2hex($ret))));
        } else {
            return false;
        }
    }
}
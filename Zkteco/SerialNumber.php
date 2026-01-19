<?php

class ZkSerialNumber
{
    static public function get($self)
    {
        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_DEVICE;
        $command_string = '~SerialNumber';

        return $self->_command($command, $command_string);
    }
}
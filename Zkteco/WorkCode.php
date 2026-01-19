<?php

class ZkWorkCode
{
    static public function get($self)
    {
        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_DEVICE;
        $command_string = 'WorkCode';

        return $self->_command($command, $command_string);
    }
}
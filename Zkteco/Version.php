<?php

class ZkVersion
{
    static public function get($self)
    {
        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_VERSION;
        $command_string = '';

        return $self->_command($command, $command_string);
    }
}
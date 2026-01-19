<?php

class ZkPlatform
{
    static public function get($self)
    {
        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_DEVICE;
        $command_string = '~Platform';

        return $self->_command($command, $command_string);
    }

    static public function getVersion($self)
    {
        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_DEVICE;
        $command_string = '~ZKFPVersion';

        return $self->_command($command, $command_string);
    }
}
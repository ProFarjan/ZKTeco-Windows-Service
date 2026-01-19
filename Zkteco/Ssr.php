<?php

class ZkSsr
{
    static public function get($self)
    {
        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_DEVICE;
        $command_string = '~SSR';

        return $self->_command($command, $command_string);
    }
}
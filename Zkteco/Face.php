<?php

class ZkFace
{
    static public function on($self)
    {
        $self->_section = __METHOD__;

        $command = ZkUtil::CMD_DEVICE;
        $command_string = 'FaceFunOn';

        return $self->_command($command, $command_string);
    }
}
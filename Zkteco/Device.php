<?php

class ZkDevice
{
  static public function name($self)
  {
    $self->_section = __METHOD__;

    $command = ZkUtil::CMD_DEVICE;
    $command_string = '~DeviceName';

    return $self->_command($command, $command_string);
  }

  static public function enable($self)
  {
    $self->_section = __METHOD__;

    $command = ZkUtil::CMD_ENABLE_DEVICE;
    $command_string = '';

    return $self->_command($command, $command_string);
  }

  static public function disable($self)
  {
    $self->_section = __METHOD__;

    $command = ZkUtil::CMD_DISABLE_DEVICE;
    $command_string = chr(0) . chr(0);

    return $self->_command($command, $command_string);
  }

  public static function powerOff($self)
  {
    $self->_section = __METHOD__;

    $command = ZkUtil::CMD_POWEROFF;
    $command_string = chr(0) . chr(0);
    return $self->_command($command, $command_string);
  }

  public static function restart($self)
  {
    $self->_section = __METHOD__;

    $command = ZkUtil::CMD_RESTART;
    $command_string = chr(0) . chr(0);
    return $self->_command($command, $command_string);
  }

  public static function sleep($self)
  {
    $self->_section = __METHOD__;

    $command = ZkUtil::CMD_SLEEP;
    $command_string = chr(0) . chr(0);
    return $self->_command($command, $command_string);
  }

  public static function resume($self)
  {
    $self->_section = __METHOD__;

    $command = ZkUtil::CMD_RESUME;
    $command_string = chr(0) . chr(0);
    return $self->_command($command, $command_string);
  }

  public static function testVoice($self)
  {
    $self->_section = __METHOD__;

    $command = ZkUtil::CMD_TESTVOICE;
    $command_string = chr(0) . chr(0);
    return $self->_command($command, $command_string);
  }

  public static function clearLCD($self)
  {
    $self->_section = __METHOD__;

    $command = ZkUtil::CMD_CLEAR_LCD;
    return $self->_command($command, '');
  }

  public static function writeLCD($self, $rank, $text)
  {
    $self->_section = __METHOD__;

    $command = ZkUtil::CMD_WRITE_LCD;
    $byte1 = chr((int)($rank % 256));
    $byte2 = chr((int)($rank >> 8));
    $byte3 = chr(0);
    $command_string = $byte1.$byte2.$byte3.' '.$text;
    return $self->_command($command, $command_string);
  }
}
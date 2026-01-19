<?php

class ZkFingerprint
{
    static public function get($self, $uid)
    {
        $self->_section = __METHOD__;

        $data = [];
        for ($i = 0; $i <= 9; $i++) {
          $finger = new self();
            $tmp = $finger->_getFinger($self, $uid, $i);
            if ($tmp['size'] > 0) {
                $data[$i] = $tmp['tpl'];
            }
            unset($tmp);
        }
        return $data;
    }

    private function _getFinger($self, $uid, $finger)
    {
        $command = ZkUtil::CMD_USER_TEMP_RRQ;
        $byte1 = chr((int)($uid % 256));
        $byte2 = chr((int)($uid >> 8));
        $command_string = $byte1 . $byte2 . chr($finger);

        $ret = [
            'size' => 0,
            'tpl' => ''
        ];

        $session = $self->_command($command, $command_string, ZkUtil::COMMAND_TYPE_DATA);
        if ($session === false) {
            return $ret;
        }

        $data = ZkUtil::recData($self, 10, false);

        if (!empty($data)) {
            $templateSize = strlen($data);
            $prefix = chr($templateSize % 256) . chr(round($templateSize / 256)) . $byte1 . $byte2 . chr($finger) . chr(1);
            $data = $prefix . $data;
            if (strlen($templateSize) > 0) {
                $ret['size'] = $templateSize;
                $ret['tpl'] = $data;
            }
        }

        return $ret;
    }

    static public function set($self, $uid, array $data)
    {
        $self->_section = __METHOD__;


        $count = 0;
        foreach ($data as $finger => $item) {
            $allowSet = true;
            $fingerPrint = new self();
            if ($fingerPrint->_checkFinger($self, $uid, $finger) === true) {
                $allowSet = $fingerPrint->_removeFinger($self, $uid, $finger);
            }
            if ($allowSet === true && $fingerPrint->_setFinger($self, $item) === true) {
                $count++;
            }
        }

        return $count;
    }

    private function _setFinger($self, $data)
    {
        $command = ZkUtil::CMD_USER_TEMP_WRQ;
        $command_string = $data;

        return $self->_command($command, $command_string);
    }

    static public function remove($self, $uid, array $data)
    {
        $self->_section = __METHOD__;

        $count = 0;
        foreach ($data as $finger) {
          $fingerPrint = new self();
            if ($fingerPrint->_checkFinger($self, $uid, $finger) === true) {
                if ($fingerPrint->_removeFinger($self, $uid, $finger) === true) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function _removeFinger($self, $uid, $finger)
    {
        $command = ZkUtil::CMD_DELETE_USER_TEMP;
        $byte1 = chr((int)($uid % 256));
        $byte2 = chr((int)($uid >> 8));
        $command_string = ($byte1 . $byte2) . chr($finger);

        $self->_command($command, $command_string);
        $fingerPrint = new self();
        return !($fingerPrint->_checkFinger($self, $uid, $finger));
    }

    private function _checkFinger($self, $uid, $finger)
    {
      $fingerPrint = new self();
        $res = $fingerPrint->_getFinger($self, $uid, $finger);
        return (bool)($res['size'] > 0);
    }
}
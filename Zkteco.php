<?php 
//if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once 'Zkteco/Attendance.php';
require_once 'Zkteco/Connect.php';
require_once 'Zkteco/Device.php';
require_once 'Zkteco/Face.php';
require_once 'Zkteco/Fingerprint.php';
require_once 'Zkteco/Os.php';
require_once 'Zkteco/Pin.php';
require_once 'Zkteco/Platform.php';
require_once 'Zkteco/SerialNumber.php';
require_once 'Zkteco/Ssr.php';
require_once 'Zkteco/Time.php';
require_once 'Zkteco/User.php';
require_once 'Zkteco/Util.php';
require_once 'Zkteco/Version.php';
require_once 'Zkteco/WorkCode.php';

class Zkteco {
    public $_ip;
    public $_port;
    public $_zkclient;

    public $_data_recv = '';
    public $_session_id = 0;
    public $_section = '';

    public function __construct() {
        // CI3 libraries are initialized using the initialize method.
    }

    public function initialize($params = array()) {
        if (isset($params['ip'])) {
            $this->_ip = $params['ip'];
        }
        if (isset($params['port'])) {
            $this->_port = $params['port'];
        } else {
            $this->_port = 4370;
        }

        if ($this->_ip) {
            $this->_zkclient = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            $timeout = array('sec' => 60, 'usec' => 500000);
            socket_set_option($this->_zkclient, SOL_SOCKET, SO_RCVTIMEO, $timeout);
        }
    }

    public function _command($command, $command_string, $type = ZkUtil::COMMAND_TYPE_GENERAL)
    {
        $chksum = 0;
        $session_id = $this->_session_id;

        $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6/H2h7/H2h8', substr($this->_data_recv, 0, 8));
        $reply_id = hexdec($u['h8'] . $u['h7']);

        $buf = ZkUtil::createHeader($command, $chksum, $session_id, $reply_id, $command_string);

        socket_sendto($this->_zkclient, $buf, strlen($buf), 0, $this->_ip, $this->_port);

        try {
            @socket_recvfrom($this->_zkclient, $this->_data_recv, 1024, 0, $this->_ip, $this->_port);

            $u = unpack('H2h1/H2h2/H2h3/H2h4/H2h5/H2h6', substr($this->_data_recv, 0, 8));

            $ret = false;
            $session = hexdec($u['h6'] . $u['h5']);

            if ($type === ZkUtil::COMMAND_TYPE_GENERAL && $session_id === $session) {
                $ret = substr($this->_data_recv, 8);
            } else if ($type === ZkUtil::COMMAND_TYPE_DATA && !empty($session)) {
                $ret = $session;
            }

            return $ret;
        } catch (Exception $e) {
            return false;
        }
    }

    public function connect()
    {
        return ZkConnect::connect($this);
    }

    public function disconnect()
    {
        return ZkConnect::disconnect($this);
    }

    public function version()
    {
        return ZkVersion::get($this);
    }

    public function osVersion()
    {
        return ZkOs::get($this);
    }

    public function platform()
    {
        return ZkPlatform::get($this);
    }

    public function fmVersion()
    {
        return ZkPlatform::getVersion($this);
    }

    public function workCode()
    {
        return ZkWorkCode::get($this);
    }

    public function ssr()
    {
        return ZkSsr::get($this);
    }

    public function pinWidth()
    {
        return ZkPin::width($this);
    }

    public function faceFunctionOn()
    {
        return ZkFace::on($this);
    }

    public function serialNumber()
    {
        return ZkSerialNumber::get($this);
    }

    public function deviceName()
    {
        return ZkDevice::name($this);
    }

    public function disableDevice()
    {
        return ZkDevice::disable($this);
    }

    public function enableDevice()
    {
        return ZkDevice::enable($this);
    }

    public function getUser()
    {
        return ZkUser::get($this);
    }

    public function setUser($uid, $userid, $name, $password, $role = ZkUtil::LEVEL_USER, $cardno = 0)
    {
        return ZkUser::set($this, $uid, $userid, $name, $password, $role, $cardno);
    }

    public function clearUsers()
    {
        return ZkUser::clear($this);
    }

    public function clearAdmin()
    {
        return ZkUser::clearAdmin($this);
    }

    public function removeUser($uid)
    {
        return ZkUser::remove($this, $uid);
    }

    public function getFingerprint($uid)
    {
        return ZkFingerprint::get($this, $uid);
    }

    public function setFingerprint($uid, array $data)
    {
        return ZkFingerprint::set($this, $uid, $data);
    }

    public function removeFingerprint($uid, array $data)
    {
        return ZkFingerprint::remove($this, $uid, $data);
    }

    public function getAttendance()
    {
        return ZkAttendance::get($this);
    }

    public function clearAttendance()
    {
        return ZkAttendance::clear($this);
    }

    public function setTime($t)
    {
        return ZkTime::set($this, $t);
    }

    public function getTime()
    {
        return ZkTime::get($this);
    }

    public function shutdown()
    {
        return ZkDevice::powerOff($this);
    }

    public function restart()
    {
        return ZkDevice::restart($this);
    }

    public function sleep()
    {
        return ZkDevice::sleep($this);
    }

    public function resume()
    {
        return ZkDevice::resume($this);
    }

    public function testVoice()
    {
        return ZkDevice::testVoice($this);
    }

    public function clearLCD()
    {
        return ZkDevice::clearLCD($this);
    }

    public function writeLCD()
    {
        return ZkDevice::writeLCD($this, 2, "RAIHAN Afroz Topu");
    }
}
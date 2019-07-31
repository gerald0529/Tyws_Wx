<?php

namespace app\mp\controller;

use \think\Request;
use \think\Db;


class Box
{
    public function index()
    {
        echo '<style type="text/css">*{ padding: 0; margin: 0; } .think_default_text{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>HELLO!</h1><p>Welcome to TaiYuWeiShi!<br/><span style="font-size:30px">wx.taiyuweishiali.cn</span></p>';
    }

    //获取附近的柜子
    public function localstorge()
    {

        if (!(Request::instance()->has('lat', 'post'))
            || !(Request::instance()->has('lng', 'post'))) {
            return;
        }

        $aPost = Request::instance()->post();
        $sLng = $aPost['lng'];
        $sLat = $aPost['lat'];

        $result = Db::table('hotelinformation')
		->field('lat,lon,hotelname,hoteladdress')
		->where('lat', '>', 0)
		->where('lon', '>', 0)
		->select();

        return $result;
    }

    public function getvr()
    {

        if (!(Request::instance()->has('openid', 'post'))
            || !(Request::instance()->has('storgeid', 'post'))) {
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];
        $sStorgeId = $aPost['storgeid'];

        if (!$this->checkStorgeOnline($sStorgeId)) {
            return array(
                'result' => 'FAIL',
                'boxid' => '',
                'msg' => '此柜暂停服务中，请选择其他柜子试试！'
            );
        }


        if ($this->checkBoxUser($sStorgeId)) {
            return array(
                'result' => 'FAIL',
                'boxid' => '',
                'msg' => '此柜有箱门已被其他用户打开，请等待使用完闭关好所有门再试！'
            );
        }


        $aBox = $this->getFullBoxByStorgeId($sStorgeId);


        if ($aBox == false) {
            return array(
                'result' => 'FAIL',
                'boxid' => '',
                'msg' => '此柜已没有可用VR，请选择其他柜子试试！'
            );
        } else {
            //做占位标记
            $this->updateBox($sStorgeId, $aBox['BoxID'], $sOpenId);

            //发送开门请求
            $data = array();
            $data['HotelID'] = $sStorgeId;
            $data['BoxID'] = $aBox['BoxID'];
            $data['messageid'] = 'msg' . date('YmdHis') . ($sStorgeId) . sprintf("%0" . strlen(100000) . "d", mt_rand(100000, 999999));
            $data['WxOpenID'] = $sOpenId;
            $data['command'] = 2;
            $data['sendtime'] = date('Y-m-d H:i:s');
            $data['state'] = 0;
			$data['type'] = 0;

            Db::table('commessageorder')->insert($data);

            return array(
                'result' => 'SUCCESS',
                'boxid' => $aBox['BoxID'],
                'vrid' => $aBox['VRID'],
                'msg' => '开门成功'
            );
        }
    }

    public function returnvr()
    {
        if (!(Request::instance()->has('openid', 'post'))
            || !(Request::instance()->has('storgeid', 'post'))) {
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];
        $sStorgeId = $aPost['storgeid'];


        if (!$this->checkStorgeOnline($sStorgeId)) {
            return array(
                'result' => 'FAIL',
                'boxid' => '',
                'msg' => '此柜暂停服务中，请选择其他柜子试试！'
            );
        }

        if ($this->checkBoxUser($sStorgeId)) {
            return array(
                'result' => 'FAIL',
                'boxid' => '',
                'msg' => '此柜有箱门已被其他用户打开，请等待使用完闭关好所有门再试！'
            );
        }

        $aBox = $this->getFreeBoxByStorgeId($sStorgeId);


        if ($aBox == false) {
            return array(
                'result' => 'FAIL',
                'msg' => '此柜已没有可用空箱，请选择其他柜子试试！',
                'boxid' => ''
            );
        } else {
            $this->updateBox($sStorgeId, $aBox['BoxID'], $sOpenId);
            $data = array();
            $data['HotelID'] = $sStorgeId;
            $data['BoxID'] = $aBox['BoxID'];
            $data['messageid'] = 'msg' . date('YmdHis') . ($sStorgeId) . sprintf("%0" . strlen(100000) . "d", mt_rand(100000, 999999));
            $data['WxOpenID'] = $sOpenId;
            $data['command'] = 2;
            $data['sendtime'] = date('Y-m-d H:i:s');
            $data['state'] = 0;
			$data['type'] = 1;

            Db::table('commessageorder')->insert($data);

            return array(
                'result' => 'SUCCESS',
                'boxid' => $aBox['BoxID']
            );
        }
    }


    public function inuse()
    {
        if (!(Request::instance()->has('openid', 'post'))) {
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];

        if ($sOpenId == '') {
            return;
        }

        $data = Db::table('receivables')->field('vrid,starttime,endtime')
            ->where('WxOpenID', $sOpenId)
            ->where('PaymentSituation', '0')
            ->where('EndTime', 'null')
            ->select();

        if (count($data)) {
            return array(
                'result' => 'SUCCESS',
                'boxid' => $data[0]['vrid'],
                'start' => $data[0]['starttime'],
                'len' => intval((time() - strtotime($data[0]['starttime'])) / 60)
            );
        } else {
            return array(
                'result' => 'FAIL',
                'boxid' => '',
                'start' => ''
            );
        }


    }

    //管理员操作柜子
    public function admininfo()
    {
        if (!(Request::instance()->has('openid', 'post'))
            || !(Request::instance()->has('storgeid', 'post'))) {
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];
        $sStorgeId = $aPost['storgeid'];

        $aBox = Db::table('boxinformation')
            ->where('HotelID', $sStorgeId)
            ->order('BoxID ASC')
            ->select();;

        return array('grids' => $aBox);

    }

    public function adminget()
    {
        if (!(Request::instance()->has('openid', 'post'))
            || !(Request::instance()->has('storgeid', 'post'))
            || !(Request::instance()->has('boxid', 'post'))) {
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];
        $sBoxId = $aPost['boxid'];
        $sStorgeId = $aPost['storgeid'];

        if ($this->checkBoxUser($sStorgeId)) {
            return array(
                'result' => 'FAIL',
                'id' => '',
                'msg' => '此柜有箱门已被其他用户打开，请等待使用完闭关好所有门再试！'
            );
        }

        $this->adminUpdateBox($sStorgeId, $sBoxId, '', $sOpenId);


        $data = array();
        $data['HotelID'] = $sStorgeId;
        $data['BoxID'] = $sBoxId;
        $data['messageid'] = 'app';
        $data['WxOpenID'] = $sOpenId;
        $data['command'] = 2;
        $data['sendtime'] = date('Y-m-d H:i:s');
        $data['state'] = 0;
		$data['type'] = 3;

        $sInsertId = Db::table('commessageorder')->insertGetId($data);

        if (count($sInsertId)) {
            return array(
                'result' => 'SUCCESS',
                'id' => $sInsertId
            );
        } else {
            return array(
                'result' => 'FAIL',
                'id' => ''
            );
        }
    }

    public function adminsave()
    {
        if (!(Request::instance()->has('openid', 'post'))
            || !(Request::instance()->has('storgeid', 'post'))
            || !(Request::instance()->has('boxid', 'post'))
            || !(Request::instance()->has('vrid', 'post'))) {
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];
        $sBoxId = $aPost['boxid'];
        $sVRId = $aPost['vrid'];
        $sStorgeId = $aPost['storgeid'];

        if ($this->checkBoxUser($sStorgeId)) {
            return array(
                'result' => 'FAIL',
                'id' => '',
                'msg' => '此柜有箱门已被其他用户打开，请等待使用完闭关好所有门再试！'
            );
        }
        $this->adminUpdateBox($sStorgeId, $sBoxId, $sVRId, $sOpenId);

        $data = array();
        $data['HotelID'] = $sStorgeId;
        $data['BoxID'] = $sBoxId;
        $data['messageid'] = 'app';
        $data['WxOpenID'] = $sOpenId;
        $data['command'] = 2;
        $data['sendtime'] = date('Y-m-d H:i:s');
        $data['state'] = 0;
		$data['type'] = 4;

        if (count(Db::table('commessageorder')->insertGetId($data))) {
            return array(
                'result' => 'SUCCESS',
                'id' => Db::table('commessageorder')->insertGetId($data)
            );
        } else {
            return array(
                'result' => 'FAIL',
                'id' => ''
            );
        }
    }

    //管理员打开所有格子
    public function adminopenall()
    {
        if (!(Request::instance()->has('openid', 'post'))
            || !(Request::instance()->has('storgeid', 'post'))) {
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];
        $sStorgeId = $aPost['storgeid'];

        $data = array();
        $data['HotelID'] = $sStorgeId;
        $data['BoxID'] = '';
        $data['messageid'] = 'app';
        $data['WxOpenID'] = $sOpenId;
        $data['command'] = 4;
        $data['sendtime'] = date('Y-m-d H:i:s');
        $data['state'] = 0;
		$data['type'] = 4;

        $iId = Db::table('commessageorder')->insertGetId($data);
        if (count($iId)) {
            return array(
                'result' => 'SUCCESS',
                'id' => $iId
            );
        } else {
            return array(
                'result' => 'FAIL',
                'id' => ''
            );
        }


    }

    public function checkcommand()
    {
        $bState = 'FAIL';
        $sMsg = '';
        $sUpdate = '';

        if (!(Request::instance()->has('comid', 'post'))) {
            $bState = 'FAIL';
            $sMsg = 'parameter error';
        } else {
            $sId = Request::instance()->post('comid');
            $res = DB::table('commessageorder')
                ->where('ID', $sId)
                ->select();
            if (count($res)) {
                $bState = 'SUCCESS';
                $sMsg = 'success';
                $sUpdate = $res[0]['Update'];
            } else {
                $bState = 'FAIL';
                $sMsg = 'no data';
            }
        }

        return array(
            'result' => $bState,
            'state' => $sUpdate,
            'msg' => $sMsg

        );

    }

    function checkBoxUser($sStorgeId)
    {
        $data = Db::table('boxinformation')
            ->field('User')
            ->where('HotelID', $sStorgeId)
            ->where('User', 1)
            ->select();
        if (count($data)) {
            return true;
        } else {
            return false;
        }

    }

    function checkStorgeOnline($sStorgeId)
    {
        $data = Db::table('hotelinformation')
            ->field('OnlineState')->where('HotelID', $sStorgeId)->select();
        if (count($data)) {
            if ($data[0]['OnlineState'] == '在线') {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }

    }

    //获取空格子
    function getFreeBoxByStorgeId($sStorgeId)
    {
        $aBox = $this->getAllBoxByStoregeId($sStorgeId);

        if (count($aBox)) {
            foreach ($aBox as $k => $v) {
                if ($v['Equipment'] == 0) {
                    return $v;
                }
            }
        } else {
            return false;
        }
    }

    //获取有设备的格子
    function getFullBoxByStorgeId($sStorgeId)
    {
        $aBox = $this->getAllBoxByStoregeId($sStorgeId);
        //dump($aBox);
        if (count($aBox)) {
            foreach ($aBox as $v) {
                if ($v['Equipment'] == 1 && $v['GateState'] == 0 && $v['WxOpenID'] == '') {
                    return $v;
                }
            }
        } else {
            return false;
        }
    }

    //根据柜子ID获取所有格子
    function getAllBoxByStoregeId($sStorgeId)
    {
        return Db::table('boxinformation')
            ->where('HotelID', $sStorgeId)
            ->order('OpenTime ASC')
            ->select();
    }

    //更新格子信息
    function updateBox($sStorgeId, $sBoxId, $sWxOpenId)
    {
        Db::table('boxinformation')
            ->where('BoxID', $sBoxId)
            ->where('HotelID', $sStorgeId)
            ->update([
                'WxOpenID' => $sWxOpenId,
                'OpenTime' => date('Y-m-d H:i:s')
            ]);
    }

    function adminUpdateBox($sStorgeId, $sBoxId, $sVRId, $sWxOpenId)
    {
        Db::table('boxinformation')
            ->where('BoxID', $sBoxId)
            ->where('HotelID', $sStorgeId)
            ->update([
                'WxOpenID' => $sWxOpenId,
                'VRID' => $sVRId,
                //'GateState' => 1,
                'Equipment' => ($sVRId == ''?0:1),
                'OpenTime' => date('Y-m-d H:i:s')
            ]);
    }

    public function checkreturn()
    {
        $sOpenId = Request::instance()->post('openid');
        $sStorgeId = Request::instance()->post('storgeid');
        $sBoxId = Request::instance()->post('boxid');

        if ($sStorgeId == '' || $sBoxId == '' || $sOpenId == '') {
            return array(
                'result' => 'FAIL',
                'state' => '1',
                'msg' => 'data error'
            );
        }

        $result = Db::table('boxinformation')
            ->field('gatestate,opencount')
            ->where('HotelID', $sStorgeId)
            ->where('BoxID', $sBoxId)
            ->select();
        if (count($result)) {

            if($result[0]['opencount']==3){
                return array(
                    'result' => 'ERROR',
                    'state' => '1',
                    'msg' => '归还VR失败:VR未正确连接数据线，请联系客户处理！(oc=3)'
                );
            }


            $result0 = Db::table('receivables')
                ->where('EndTime', null)
                ->where('WxOpenID', $sOpenId)
                ->where('PaymentSituation', 0)
                ->limit(1)
                ->select();

            if (count($result0)) {
                return array(
                    'result' => 'FAIL',
                    'state' => '1',
                    'msg' => '---'
                );
            } else {
                return array(
                    'result' => 'SUCCESS',
                    'state' => 0, //可以付款
                    'msg' => ''
                );
            }
//            if ($result[0]['gatestate'] == 3) {
//                return array(
//                    'result' => 'FAIL',
//                    'state' => '1',
//                    'msg' => '归还失败，请联系客服处理！'
//                );
//            } else {
//
//            }


        } else {
            return array(
                'result' => 'FAIL',
                'state' => '1',
                'msg' => ''
            );
        }
    }
}

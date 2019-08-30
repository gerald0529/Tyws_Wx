<?php
namespace app\mp\controller;
use \think\Request;
use \think\Db;
use \think\Config;
use Wx\WXBizDataCrypt;

class User
{
    public function index()
    {
        echo '<style type="text/css">*{ padding: 0; margin: 0; } .think_default_text{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>HELLO!</h1><p>Welcome to TaiYuWeiShi!<br/><span style="font-size:30px">wx.taiyuweishiali.cn</span></p>';
    }

    //根据CODE获取openid
    public function openid(){
        if(!(Request::instance()->has('code','post'))){
            return;
        }
        $aPost = Request::instance()->post();
        $sCode = $aPost['code'];
		
		$sUrl = "https://api.weixin.qq.com/sns/jscode2session?"
            ."appid=".\think\Config::get('appid')
            ."&secret=".\think\Config::get('appsecret')
            ."&js_code=".$sCode."&grant_type=authorization_code";
        $weixin =  file_get_contents($sUrl);
		
		\Think\Log::write('url'.$sUrl, 'INFO');
		\Think\Log::write('opeid'.$weixin, 'INFO');
        echo $weixin;
    }
	
	public function info(){
		//获取用户信息1
        if(!(Request::instance()->has('openid','post'))){
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];
		
		$sUnionId = '';
		if((Request::instance()->has('unionid','post'))){
            $sUnionId = $aPost['unionid'];
        }

        if(($sOpenId == '')) return;

		$aConfig = $this->getConfiguration();
		//dump($aConfig);
		$sMinDeposit = $aConfig['deposit'];//$sMinDeposit = 0.01;
		$sDepositLockTime = $aConfig['deposit_lock_time'];
		$sUnitPrice = $aConfig['unit_price'];
		$where['WxOpenID'] = $sOpenId;
		
		$count = Db::table('customerinformation')->where($where)->count();
		
		//dump($Data->getLastSql());
		//print_r($count);
		if($count){
		    //老用户
			$source['UnionID'] = $sUnionId;	
			if($sUnionId != ''){
				Db::table('customerinformation')->where('WxOpenID', $sOpenId)->update($source);
			}
			
			$record = Db::table('customerinformation')->field('isadmin,deposit,balance,state,phonenumber,vrid,errordoor')->where($where)->select();
			$result['isDep'] = $record[0]['deposit']>0?true:false;
            $result['isAdmin'] = $record[0]['isadmin']?true:false;
			$result['minDeposit'] = $sMinDeposit;
			$result['depositLockTime'] = $sDepositLockTime;
			$result['deposit'] = $record[0]['deposit'];
            $result['balance'] = $record[0]['balance'];
            $result['vrid'] = $record[0]['vrid'] == null?'':$record[0]['vrid'];
			$result['unitPrice'] = $sUnitPrice;
			$result['phone'] = $record[0]['phonenumber'];
			$result['state'] = $record[0]['errordoor'] == 1?'3':$this->checkState($sOpenId);

			return $result;
		}else{
			//新用户.
			$source = array();
			$source['WxOpenID'] = $sOpenId;
			if($sUnionId != ''){
				$source['UnionID'] = $sUnionId;	
			}
			
			$aCustomer['state'] = 0;
			Db::table('customerinformation')->insert($source);
			
			$result['isDep'] = false;
            $result['isAdmin'] = false;
			$result['minDeposit'] = $sMinDeposit;
			$result['depositLockTime'] = $sDepositLockTime;
			$result['deposit'] = 0;
			$result['phone'] = null;
			$result['state'] = 0;
            $result['vrid'] = '';
			$result['unitPrice'] = $sUnitPrice;
			return $result;
		}
	}

	//最后一次使用记录
	public function lastuse(){
        $sOpenId = Request::instance()->post('openid');
        if($sOpenId == '') return false;
        $result = Db::table('receivables')
            ->field('id,vrid,messageid,uselen,starttime,endtime,amountmoney')
            ->where('WxOpenID', $sOpenId)
            //->where('EndTime', null)
            ->where('PaymentSituation', 0)
            ->where('InPayment', 0)
            ->order('StartTime desc')
            ->select();//print_r($result);

            //判断VR租用时长是否满一天，每过一天减免12元
            $a =intval($result[0]['amountmoney']/32)*12; //减免金额
            //$a =intval($result[0]['amountmoney']/10)*9.99;//测试
            if($a>0){
                $result[0]['amountmoney'] = $result[0]['amountmoney']-$a;
            }
        if(count($result)){
            return $result[0];
        } else {
            return array(
                'amountmoney'=>'',
                'messageid' => ''
                );
        }

    }

	public function updateuser(){
        //获取用户信息
        $sOpenId = Request::instance()->post('openid');
        if($sOpenId == '') return false;

        if(Request::instance()->post('nick') != ''){
            $appid = Config::get('APPID');
            $sessionKey = Request::instance()->post('sk');
            $encryptedData=Request::instance()->post('encry');
            $iv = Request::instance()->post('i');
            $pc = new WXBizDataCrypt();
            $pc->WXBizDataCrypt($appid, $sessionKey);
            $errCode = $pc->decryptData($encryptedData, $iv, $sData );

            if ($errCode == 0) {
                //print($sData . "|");
                $oData = json_decode($sData);
				//print_r($oData);

                $aCustomer['NickName'] 		= $oData->nickName;
                $aCustomer['Gender'] 		= $oData->gender;
                $aCustomer['Country'] 		= $oData->country;
                $aCustomer['Province'] 		= $oData->province;
                $aCustomer['City'] 			= $oData->city;
                $aCustomer['AvatarUrl'] 	= $oData->avatarUrl;

                Db::table('customerinformation')->where('WxOpenID', $sOpenId)->update($aCustomer);
                return array(
                    'result'    => 'SUCCESS',
                    'msg'       => ''
                );
            } else {
                return array(
                    'result'    => 'FAIL',
                    'msg'       => $errCode
                );
                return $errCode;
            }
        } else {
            return array(
                'result'    => 'FAIL',
                'msg'       => 'Nick is null.'
            );
        }
    }


    //我的账户
    public function account(){

        if(!(Request::instance()->has('openid','post'))){
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];

        if(($sOpenId == '')) return;

        $result = Db::table('customerinformation')
            ->where('WxOpenID', $sOpenId)->select();
        return $result;
    }


    //账户流水
    public function ls(){
        //return 22;
        if(!(Request::instance()->has('openid','post'))){
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];

        if(($sOpenId == '')) return;

        $result =array();
        $result = Db::table('customerinformation')
            ->where('WxOpenID', $sOpenId)->select();

        $res = Db::table('Wxpayorder')
            ->alias('w')
            ->join('customerinformation c','c.WxOpenID = w.openid','left')
            ->where('w.openid', $sOpenId)
            ->field('w.id,order_type, w.messageid, w.body,w.out_trade_no,w.total_fee,w.time_end,w.refund_state,w.detail,c.NickName')
            ->order('w.time_end desc')
            ->select();

        foreach($res as $v){
            $result[] = array(
                'body'			=> $v['body'],
                'out_trade_no'	=> $v['out_trade_no'],
                'total_fee'		=> $v['total_fee'],
                'time_end'		=> $v['time_end'],
                'pay'			=> true,
                'messageid'		=> $v['messageid'],
                'type'			=> $v['order_type'],
                'refund_state'	=> $v['refund_state'],
                'detail'	=> $v['detail']
            );
        }

        return $result;
    }


    //账户充值
    public function topup(){

        if(!(Request::instance()->has('openid','post'))){
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];

        if(($sOpenId == '')) return;

        /*$result = Db::table('Receivables')
            ->field('id,vrid,uselen,starttime,endtime,amountmoney')
            ->where('WxOpenID', $sOpenId)->select();*/
        $result = Db::table('customerinformation')
            ->where('WxOpenID', $sOpenId)->select();
        return $result;
    }


    //租赁记录
    public function watchs(){
        if(!(Request::instance()->has('openid','post'))){
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];

        if(($sOpenId == '')) return;

        $result = Db::table('Receivables')
            ->field('id,vrid,uselen,starttime,endtime,amountmoney')
            ->where('WxOpenID', $sOpenId)->select();

//        return array(
//            'result'    => 'SUCCESS',
//            'data'       => $result
//        );
        return $result;
    }

    public function consume(){
        if(!(Request::instance()->has('openid','post'))){
            return;
        }

        $aPost = Request::instance()->post();
        $sOpenId = $aPost['openid'];

        if(($sOpenId == '')) return;

        $result = array();

        //读取未支付订单
        $res0 = Db::table('Receivables')
            ->field('amountmoney,endtime,messageid')
            ->where('WxOpenID', $sOpenId)
            ->where('PaymentSituation', 0)
            ->where('EndTime','NOT NULL')
            ->select();
        if(count($res0)){
            $result[] = array(
                'body'			=> '使用消费',
                'out_trade_no'	=> '[未支付]',
                'total_fee'		=> $res0[0]['amountmoney'],
                'time_end'		=> $res0[0]['endtime'],
                'pay'			=> false,
                'messageid'		=> $res0[0]['messageid'],
                'type'			=> 0,
                'refund_state'	=> 0,
                'detail'		=> ''

            );
        }

        //读取已支付订单
        $res = Db::table('Wxpayorder')
            ->field('id,order_type, messageid, body,out_trade_no,total_fee,time_end,refund_state,detail')
            ->where('openid', $sOpenId)
            ->where('state', 1)
            ->order('time_end desc')
            ->select();
        //dump($result);
        foreach($res as $v){
            $result[] = array(
                'body'			=> $v['body'],
                'out_trade_no'	=> $v['out_trade_no'],
                'total_fee'		=> $v['total_fee'],
                'time_end'		=> $v['time_end'],
                'pay'			=> true,
                'messageid'		=> $v['messageid'],
                'type'			=> $v['order_type'],
                'refund_state'	=> $v['refund_state'],
                'detail'	=> $v['detail']
            );
        }

        return $result;

    }

    function checkState($sOpenId){
        if($sOpenId == '') return false;

        $sState = '1';

        //检测是否有未支付订单
        $res = Db::table('Receivables')
        ->field('messageid')
        ->where('WxOpenID', $sOpenId)
        ->where('PaymentSituation', 0)
        ->select();
        if(count($res)) {
            $sState = '2';
        }

        return $sState;
    }

    //获取结算扣费表设置信息
    function getConfiguration(){
        $aConfig = array();

        $res = Db::table('Configuration')->field('key,value')->select();
        for($i=0; $i<count($res); $i++){
            $aConfig[$res[$i]['key']] = $res[$i]['value'];
        }
        return $aConfig;
    }

    //常见问题获取
    function getProblems(){

        $data = Db::table('Comproblems')->where('isShow',0)->select();

        if(!$data){
            $data = array(
                'code'			=> '404',
                'data'=>'',
                'msg'			=> 'FAIL'
            );
        }
        return $data;
    }
}

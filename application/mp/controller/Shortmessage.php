<?php
namespace app\mp\controller;
use \think\Request;
use \think\Db;
use \AliSMS\Sms;

class Shortmessage{

    public function index()
    {
        echo '<style type="text/css">*{ padding: 0; margin: 0; } .think_default_text{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>HELLO!</h1><p>Welcome to TaiYuWeiShi!<br/><span style="font-size:30px">wx.taiyuweishiali.cn</span></p>';
    }

    public function getsmscode(){
        if(!(Request::instance()->has('openid','post'))){
            return;
        }

        if(!(Request::instance()->has('phone','post'))){
            return;
        }

        $aPost = Request::instance()->post();
        $sPhone = $aPost['phone'];
        $sOpenid = $aPost['openid'];

        if($sPhone == '') return ;
        if($sOpenid == '') return;

        $code = rand(100000, 999999);

        $oSms = new Sms();

        $result = $oSms->sendSms(
            "泰宇威视", // 短信签名
            "SMS_141705062", // 短信模板编号
            $sPhone, // 短信接收者
            Array(  // 短信模板中字段的值
                "code"=>$code,
                "product"=>"VR"
            ),
            "9527"   // 流水号,选填
        );



        /*if($result->Code == 'OK'){
            $number='0';
            $this->updatesend($sPhone,$number);
			
        }*/
		
		if($result->Code == 'OK'){
            $this->insertsmscode($sOpenid, $sPhone, $code);
        }


        return $result;
    }
function updatesend($sPhone, $number){
        $source['send'] = $number;
        Db::table('remindersms')->where('phone',$sPhone)->update($source);

    }
    //校验手机验证码
    public function checksmscode(){
        if(!(Request::instance()->has('openid','post'))){
            return;
        }
        if(!(Request::instance()->has('phone','post'))){
            return;
        }
        if(!(Request::instance()->has('code','post'))){
            return;
        }

        $aPost = Request::instance()->post();
        $sPhone = $aPost['phone'];
        $sOpenid = $aPost['openid'];
        $sCode = $aPost['code'];

        if($sPhone == '') return ;
        if($sOpenid == '') return;
        if($sCode == '') return;


        $where['wx_openid'] = $sOpenid;
        $where['phone_number'] = $sPhone;
        $where['auth_code'] = $sCode;
        $res = Db::table('smscode')->where($where)->select();
        if(count($res)){
            $result = array(
                'msg' 		=> '验证通过！',
                'result' 	=> 'SUCCESS'
            );
            //更新手机号码
            $this->updatephonenumber($sOpenid, $sPhone);
            //删除验证码
            $this->deletesmscode($sOpenid);
        } else {
            $result = array(
                'msg' 		=> '无效的验证码！',
                'result' 	=> 'FAIL'
            );
        }

        return $result;
    }

    //保存验证码用于校验
    function insertsmscode($openid, $phone, $code){
        $this->deletesmscode($openid);

        $data=array(
            'wx_openid'     => $openid,
            'phone_number'     => $phone,
            'auth_code'     => $code,
            'insert_time'     => date('Y-m-d H:i:s')

        );

        Db::table('smscode')->insert($data);
    }

    //删除验证码
    function deletesmscode($openid){
        Db::table('smscode')->where('wx_openid',$openid)->delete();
    }

    //更新用户手机号码
    function updatephonenumber($openid, $phonenumber){
        if($phonenumber == '') return false;
        if($openid == '') return false;
        $source['PhoneNumber'] = $phonenumber;
        $source['RegistrationTime'] = date('Y-m-d H:i:s');
        $where['WxOpenID'] = $openid;
        Db::table('customerinformation')->where($where)->update($source);



    }
}
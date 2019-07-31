<?php
namespace app\mp\controller;
use AliSMS\Sms;
use think\Db;
use think\Request;

class Timesms
{
    public function index()
    {
        echo "11111111";
    }
    public function getsmscode(){
            echo "泰宇威视欢迎您";
            $res = Db::query('select id,phone,duedate,usertime,rent,notpaid from remindersms where state=0');     //查询未发送状态的信息进行遍历
            dump($res);
            foreach ($res as $key => $value) {
                # code...
                $id = $value['id'];
                $phone = $value['phone'];
                $duedate = $value['duedate'];
                $usertime = $value['usertime'];
                $rent = $value['rent'];
                $notpaid = $value['notpaid'];
                
                $oSms = new Sms();
                $result = $oSms->sendSms(
                    "泰宇威视", // 短信签名
                    "SMS_158948787", // 短信模板编号
                    $phone, // 短信接收者
                    Array(  // 短信模板中字段的值
                        "date" => $duedate,     //截止日期
                        "usertime"=>$usertime,  //使用时长
                        "rent" => $rent,        //总租金
                        "notpaid" => $notpaid,  //未结算资金
                        "telnumber" => "4006566017" //客服电话
                        
                    ),
                    "9527"   // 流水号,选填
                 );
                if($result->Code == 'OK'){
                    echo "短信发送成功";
                    DB::name('remindersms')->where(
                            ['id' => $id]
                        )->update(['state' => '1']);  //将状态置为1，表示已发送
                }else{
                    echo "短信发送失败";
                }
                 return $result;
            }

    }
}


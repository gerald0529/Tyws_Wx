<?php
namespace app\mp\controller;
use \think\Request;
use \think\Db;
use \think\Config;

class Pay
{

    public function index()
    {
        echo '<style type="text/css">*{ padding: 0; margin: 0; } .think_default_text{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>HELLO!</h1><p>Welcome to TaiYuWeiShi!<br/><span style="font-size:30px">wx.taiyuweishiali.cn</span></p>';
    }

    public function wxpay(){
        $sOpenId = Request::instance()->post('openid');
        if($sOpenId == '') return false;

        \think\Loader::import('Wxpay.Autoloader');

        $sNow = "'".strtotime(date('Y-m-d H:i:s'))."'";

        if(Request::instance()->post('total_fee')*100 <= 0){
            //金额0，直接支付成功
            //订单信息
            $aOrder = array(
                'appid'				=> Config::get('appid'),
                'openid'			=> $sOpenId,
                'mch_id'			=> Config::get('mchid'),
                'device_info'		=> Request::instance()->post('device_info'),
                'body'				=> Request::instance()->post('body'),
                'detail'			=> Request::instance()->post('detail'),
                'order_type'		=> Request::instance()->post('order_type'),
                'attach'			=> Request::instance()->post('attach'),
                'messageid'			=> Request::instance()->post('messageid'),
                'out_trade_no'		=> 'VRBOXNOPAY'.date('YmdHis').rand(100000,999999),
                'total_fee'			=> Request::instance()->post('total_fee'),
                'time_start'		=> date('YmdHis'),
                'time_expire'		=> date('YmdHis', time() + 600),
                'goods_tag'			=> '',
                'spbill_create_ip'	=> Request::instance()->ip(),
                'notify_url'		=> \think\Config::get('WXPAY_NOTIFY_URL'),
                'state' 			=> 1,
                'time_end' 			=> date('YmdHis'),
                'trade_type'		=> 'JSAPI'
            );
            //把订单写进数据库
            $this->insertOrder($aOrder);

            //直接更新支付结果
            $aCustomer1['state'] = 1;
            Db::table('Customerinformation')
                ->where('WxOpenID', $aOrder['openid'])
                ->update($aCustomer1);

            //更新观看表
            $aCustomer2['PaymentSituation'] = 1;
            $aCustomer2['InPayment'] = 2;
            Db::table('Receivables')
                ->where('WxOpenID', $aOrder['openid'])
                ->where('PaymentSituation', 0)
                ->update($aCustomer2);

            //返回给手机端
            $result = array(
                'timeStamp'		=> $sNow,
                'nonceStr'		=> '',
                'package'		=> 'prepay_id=-',
                'signType'		=> 'MD5',
                'paySign'		=> '',
                'returnCode'	=> 'SUCCESS',
                'totalFee'		=> 0

            );
        } else {
            //订单信息
            $aOrder = array(
                'appid'				=> \think\Config::get('appid'),
                'openid'			=> $sOpenId,
                'mch_id'			=> \think\Config::get('mchid'),
                'device_info'		=> Request::instance()->post('device_info'),
                'body'				=> Request::instance()->post('body'),
                'detail'			=> Request::instance()->post('detail'),
                'order_type'		=> Request::instance()->post('order_type'),
                'attach'			=> Request::instance()->post('attach'),
                'messageid'			=> Request::instance()->post('messageid'),
                'out_trade_no'		=> 'VRBOXPAY'.date('YmdHis').rand(100000,999999),
                'total_fee'			=> Request::instance()->post('total_fee'),
                'time_start'		=> date('YmdHis'),
                'time_expire'		=> date('YmdHis', time() + 600),
                'goods_tag'			=> '',
                'spbill_create_ip'	=> Request::instance()->ip(),
                'notify_url'		=> \think\Config::get('wxpay_notify_url'),
                'trade_type'		=> 'JSAPI'
            );

            //print_r($aOrder);
            //把订单写进数据库
            $this->insertOrder($aOrder);
            $sNow = "'".strtotime(date('Y-m-d H:i:s'))."'";
            //统一下单
            $input = new \WxPayUnifiedOrder();
            $input->SetBody($aOrder['body']);
            $input->SetAttach($aOrder['attach']);
            $input->SetOut_trade_no($aOrder['out_trade_no']);
            $input->SetTotal_fee($aOrder['total_fee']*100);
            $input->SetTime_start($aOrder['time_start']);
            $input->SetTime_expire($aOrder['time_expire']);
            $input->SetGoods_tag($aOrder['goods_tag']);
            $input->SetNotify_url($aOrder['notify_url']);
            $input->SetTrade_type($aOrder['trade_type']);
            $input->SetOpenid($aOrder['openid']);
            $wxOrder = new \WxPayApi();
            $order = $wxOrder->unifiedOrder($input);
            //dump($order);

            //待签名内容
            $sSign = 'appId='.\think\Config::get('appid')
                .'&nonceStr='.$order['nonce_str']
                .'&package=prepay_id='.$order['prepay_id']
                .'&signType=MD5'
                .'&timeStamp='.$sNow
                .'&key='.\think\Config::get('key');
            //重新签名
            $paySign = strtoupper(md5($sSign));
            $xml = "<xml>";
            foreach ($order as $key=>$val)
            {
                if (is_numeric($val)){
                    $xml.="<".$key.">".$val."</".$key.">";
                }else{
                    $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
                }
            }
            $xml.="</xml>";
            //echo $xml;
            \Think\Log::write('微信支付统一下单'.$order['prepay_id'], 'INFO');

            //更新观看表
            $aCustomer3['InPayment'] = 1;
            Db::table('Receivables')
                ->where('messageid', $aOrder['messageid'])
                ->update($aCustomer3);

            //返回给手机端
            $result = array(
                'timeStamp'		=> $sNow,
                'nonceStr'		=> $order['nonce_str'],
                'package'		=> 'prepay_id='.$order['prepay_id'],
                'signType'		=> 'MD5',
                'paySign'		=> $paySign,
                'returnCode'	=> 'SUCCESS',
                'totalFee'		=> $aOrder['total_fee']
            );
        }

        return $result;
    }


    public function notify(){
        //响应微信支付平台回调

        $raw_xml = file_get_contents("php://input");
        //$numbytes = file_put_contents(time().'.txt', 'aaa');

        \Think\Log::write('微信APP支付成功:'.$raw_xml, 'INFO');
        $aNotify = json_decode(json_encode(simplexml_load_string($raw_xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

        $aOrder['openid']           = $aNotify['openid'];
        $aOrder['out_trade_no']     = $aNotify['out_trade_no'];
        $aOrder['attach']           = $aNotify['attach'];
        $aOrder['total_fee']        = $aNotify['total_fee'];
        $aOrder['bank_type']        = $aNotify['bank_type'];
        $aOrder['fee_type']         = $aNotify['fee_type'];
        $aOrder['is_subscribe']     = $aNotify['is_subscribe'];
        $aOrder['time_end']         = $aNotify['time_end'];
        $aOrder['transaction_id']   = $aNotify['transaction_id'];
        $aOrder['result_code']      = $aNotify['result_code'];

        if($this->updateOrder($aOrder)){
            $aResult = array(
                'return_code'	=> 'SUCCESS',
                'return_msg'	=> 'OK'
            );
        } else {
            $aResult = array(
                'return_code'	=> 'FAIL',
                'return_msg'	=> ''
            );
        }


        //处理后同步返回给微信支付
        return xml($aResult);
        //echo "success";

    }
    //创建预支付信息
    function insertOrder($aOrder){
        Db::table('Wxpayorder')->insert($aOrder);
        return true;
    }

    function updateOrder($aOrder){

        $aOrder['state'] = 1;
        $aOrder['total_fee'] = $aOrder['total_fee']/100;
        //print_r($aOrder);
        $res = Db::table('Wxpayorder')
            ->where('state', 0)
            ->where('out_trade_no', $aOrder['out_trade_no'])
            ->update($aOrder);
        \Think\Log::write('UPDATE结果:'.$res, 'INFO');
        if($res===false){
            //更新失败
            return false;
        }
        else{
            $numbytes = file_put_contents(time().'.txt', $res);
            if($res>0){

                //更新成功
                if($aOrder['attach']=='用户主动充押金'){

                    //如果是充押金，同步更新用户账户余额
                    \Think\Log::write('aOrder[attach]:'.$aOrder['attach'].($aOrder['attach']=='用户主动充押金'), 'INFO');
                    $this->updateDeposit($aOrder['openid'], $aOrder['total_fee'], $aOrder['out_trade_no']);
                    return true;
                } elseif($aOrder['attach']=='用户余额充值'){

                    \Think\Log::write('aOrder[attach]:'.$aOrder['attach'].($aOrder['attach']=='用户余额充值'), 'INFO');
                    $this->updateDeposit2($aOrder['openid'], $aOrder['total_fee'], $aOrder['out_trade_no']);
                    return true;
                }else {

                    //消费付款
                    //更新用户表
                    $aCustomer1['state'] = 1;
                    Db::table('Customerinformation')
                        ->where('WxOpenID', $aOrder['openid'])
                        ->update($aCustomer1);

                    //更新观看表
                    $aCustomer2['PaymentSituation'] = 1;
                    $aCustomer2['InPayment'] = 2;
                    Db::table('Receivables')
                        ->where('WxOpenID', $aOrder['openid'])
                        ->where('PaymentSituation', 0)
                        ->update($aCustomer2);

                    return true;
                }
            }
            else{

                //未更新
                return false;
            }
        }

        return true;
    }

    function updateDeposit($sOpenId, $iDeposit, $sOutTradeNo){
        //获取当前用户账户余额
        //$deposit = $Data->where($aWhere)->getField('deposit');

        //累加余额
        $aCustomer['deposit'] = $iDeposit;

        //状态
        $aCustomer['state'] = 1;
        $aCustomer['PaymentType'] = 1;
        $aCustomer['PaymentID'] = $sOutTradeNo;
        $aCustomer['PaymentTotalFee'] = $iDeposit;
        $aCustomer['PaymentTime'] = date('Y-m-d H:i:s');

        Db::table('Customerinformation')
            ->where('WxOpenID', $sOpenId)
            ->update($aCustomer);
    }

    //用户余额充值
    function updateDeposit2($sOpenId, $iDeposit, $sOutTradeNo){
        //获取当前用户账户余额
        //$deposit = $Data->where($aWhere)->getField('deposit');

        $balance = Db::table('Customerinformation')
            ->where('WxOpenID', $sOpenId)
            ->value('balance');

        //累加余额
        $aCustomer['balance'] = $balance+$iDeposit;
        $aCustomer['balancetime'] = date('Y-m-d H:i:s');

        //状态
        $aCustomer['state'] = 1;
        $aCustomer['PaymentType'] = 1;
        $aCustomer['PaymentID'] = $sOutTradeNo;
        $aCustomer['PaymentTotalFee'] = $aCustomer['balance'];
        $aCustomer['PaymentTime'] = date('Y-m-d H:i:s');
        //$numbytes = file_put_contents("./".time().'.txt', $aCustomer);
        $v=Db::table('Customerinformation')
            ->where('WxOpenID', $sOpenId)
            ->update($aCustomer);

    }

    //检测是否允许退款
    public function checkwxrefund(){
        $sOpenId = Request::instance()->post('openid');
        if($sOpenId == '') return;
        $aConfig = $this->getConfiguration();
        $sLastPayTime = $this->getLastPayTime($sOpenId);
        if($sLastPayTime == false){
            $aReturn['refundenable'] = 0;
        } else {
            //dump($aConfig);
            //dump((strtotime('now') - strtotime($sLastPayTime))/3600);
            if(((strtotime('now') - strtotime($sLastPayTime))/3600) > $aConfig['deposit_lock_time']){;
                $aReturn['refundenable'] = 1;
            }else{
                $aReturn['refundenable'] = 0;;
            }
        }

        return $aReturn;
    }

    /*
     * 申请退款
     */
    public function wxrefund(){

        $sOpenId = Request::instance()->post('openid');
        if($sOpenId == '') return false;

        \think\Loader::import('Wxpay.Autoloader');
        //$this->ajaxReturn(array('out_refund_no'		=> 'TEST'),'JSON');

        //订单信息
        $aOrder = $this->getDepositOrder($sOpenId);
        //dump($oPost->openid);
        //dump($aOrder);
        //$aOrder['out_trade_no'] = '';
        \Think\Log::write('提交申请退款wxrefund(out_trade_no):'.$aOrder['out_trade_no'], 'INFO');

        if($aOrder['out_trade_no'] == ''){
            //没有查到充值订单，返回错误
            \Think\Log::write('提交申请退款wxrefund:没有查到充值订单', 'INFO');

            $result = array(
                'return_code'			=> 'FAIL',
                'out_refund_no'			=> ''
            );

            return $result;
        }



        $sOutRefundNo = 'VRBOXREFUND'.date('YmdHis').rand(100000,999999);

        //提交退款
        $input = new \WxPayRefund();
        $input->SetOut_trade_no($aOrder['out_trade_no']);
        $input->SetOut_refund_no($sOutRefundNo);
        $input->SetTotal_fee($aOrder['total_fee']*100);
        $input->SetRefund_fee($aOrder['total_fee']*100);
        $input->SetOp_user_id(\think\Config::get('mchid'));
        //$input->SetData('refund_account	','REFUND_SOURCE_RECHARGE_FUNDS');
        //dump($aOrder['total_fee']);
        //dump($input);

        //print_r($input);

        $order = \WxPayApi::refund($input);

        //dump($order);
        $xml = "<xml>";
        foreach ($order as $key=>$val)
        {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            }else if(is_array($val)){

            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        //echo $xml;

        \Think\Log::write('提交申请退款wxrefund($order):'.$xml, 'INFO');
        //dump($order);

        //print_r($order);
        /*
        Array
        (
            [appid] => wx25259ec1e2c178d6
            [cash_fee] => 200
            [cash_refund_fee] => 200
            [coupon_refund_count] => 0
            [coupon_refund_fee] => 0
            [mch_id] => 1489910402
            [nonce_str] => KVzPwqcANDhYoVb2
            [out_refund_no] => VRBOXREFUND148991040220171120112130
            [out_trade_no] => VRBOXPAY148991040220171120085910
            [refund_channel] => Array
                (
                )

            [refund_fee] => 200
            [refund_id] => 50000305042017112002412686247
            [result_code] => SUCCESS
            [return_code] => SUCCESS
            [return_msg] => OK
            [sign] => FEDBF5842788A288AE999E647CA35DEB
            [total_fee] => 200
            [transaction_id] => 4200000035201711205826689843
        )
        */

        if($order['return_code'] == 'SUCCESS'){
            \Think\Log::write('提交申请退款成功', 'INFO');

            //清除用户押金余额
            $this->clearDeposit($sOpenId, $sOutRefundNo, $aOrder['total_fee']);

            //写进微信订单表
            $aRefundOrder = array(
                'appid'				=> \think\Config::get('appid'),
                'openid'			=> $sOpenId,
                'mch_id'			=> \think\Config::get('mchid'),
                'device_info'		=> 'WeChat',
                'body'				=> '退押金',
                'detail'			=> $aOrder['out_trade_no'],
                'state'				=> 1,
                'refund_state'		=> 1,
                'order_type'		=> Request::instance()->post('order_type'),
                'attach'			=> '用户申请退押金',
                'out_trade_no'		=> $sOutRefundNo,
                'total_fee'			=> $aOrder['total_fee'],
                'time_start'		=> date('YmdHis'),
                'time_expire'		=> date('YmdHis', time() + 600),
                'time_end'			=> date('YmdHis'),
                'goods_tag'			=> '',
                'spbill_create_ip'	=> Request::instance()->ip(),
                'notify_url'		=> '',
                'transaction_id'	=> $order['transaction_id'],
                'result_code'		=> $order['result_code'],
                'remark'			=> $order['return_msg'],
                'trade_type'		=> 'JSAPI'
            );

            //dump($aOrder);
            //把订单写进数据库
            $this->insertOrder($aRefundOrder);
        }

        //返回给手机端
        $result = array(
            'return_code'			=> $order['return_code'],
            'out_refund_no'			=> $sOutRefundNo
        );

        return $result;

    }

    //微信退款回调
    public function refundnotify(){

        $raw_xml = file_get_contents("php://input");

        $aRefund = json_decode(json_encode(simplexml_load_string($raw_xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        \Think\Log::write('微信退款回调:'.$raw_xml, 'INFO');


        if($aRefund['return_code'] == 'SUCCESS'){

            //解密req_info
            $key = strtolower(md5(\think\Config::get('key')));
            $str = $aRefund['req_info'];
            $str = base64_decode($str);
            $str = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $str, MCRYPT_MODE_ECB);
            $block = mcrypt_get_block_size('rijndael_128', 'ecb');
            $pad = ord($str[($len = strlen($str)) - 1]);
            $len = strlen($str);
            $pad = ord($str[$len - 1]);
            $req = (substr($str, 0, strlen($str) - $pad));

            $aReq = json_decode(json_encode(simplexml_load_string($req, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

            //\think\Config::get('KEY');
            $sData = mcrypt_decrypt(MCRYPT_CAST_256, strtolower(md5(\think\Config::get('KEY'))), base64_decode($aRefund['req_info']), 'ecb');
            //\Think\Log::write('微信退款回调:'.$aRefund['req_info'], 'INFO');
            \Think\Log::write('微信退款回调(req_info)'.$req, 'INFO');
			
            if($aReq['out_trade_no'] != ''){
                //更新充值订单退款状态
                $aWxpayorder['refund_state'] = 2;
                Db::table('Wxpayorder')
                    ->where('out_trade_no', $aReq['out_trade_no'])
                    ->update($aWxpayorder);


                //更新退款订单退款状态
                $aWxpayorder1['refund_state'] = 2;
                Db::table('Wxpayorder')
                    ->where('out_trade_no', $aReq['out_refund_no'])
                    ->update($aWxpayorder);

            }

        } else {

        }


        $aResult = array(
            'return_code'	=> 'SUCCESS',
            'return_msg'	=> 'OK'
        );

        //处理后同步返回给微信支付
        return xml($aResult);

    }

    //获取最近一次的支付完成的时间
    function getLastPayTime($sOpenId){
        if($sOpenId == '') return false;

        $res = Db::table('Wxpayorder')
            ->field('time_end')
            ->where('openid', $sOpenId)
            ->where('state', 1)
            ->order('time_end DESC')
            ->select();

        if(count($res)){
            return $res[0]['time_end'];
        } else {
            return false;
        }
    }

    function getDepositOrder($sOpenId){
        if($sOpenId == ''){
            return false;
        }

        $result = array();
        //查询（已支付的、未申请退款的、充押金、最新的）的预支付信息的订单号和金额
        $res = Db::table('Wxpayorder')->field('out_trade_no,total_fee,time_end')
            ->where('openid', $sOpenId)
            ->where('state', 1)
            ->where('refund_state', 0)
            ->where('order_type', 0)
            ->order('time_start DESC')
            ->select();
        //dump($Data->getLastSql());
        //dump($res);
        if(count($res))	{
            $result = array(
                'out_trade_no'	=> $res[0]['out_trade_no'],
                'total_fee'		=> $res[0]['total_fee']

            );
        }

        return $result;
    }

    //清除押金
    function clearDeposit($sOpenId, $sOutRefundNo, $sTotalFee){
        if($sOpenId == ''){
            return false;
        }

        //状态
        $aCustomer['state'] = 0;   //无押金
        $aCustomer['deposit'] = 0;  //押金金额
        $aCustomer['RefundType'] = 1;   //退款方式
        $aCustomer['RefundID'] = $sOutRefundNo;
        $aCustomer['RefundTotalFee'] = $sTotalFee;
        $aCustomer['RefundTime'] = date('Y-m-d H:i:s');

        Db::table('Customerinformation')
            ->where('WxOpenID', $sOpenId)
            ->update($aCustomer);
    }

    //获取Configuration表信息
    function getConfiguration(){
        $aConfig = array();

        $res = Db::table('Configuration')->field('key,value')->select();
        for($i=0; $i<count($res); $i++){
            $aConfig[$res[$i]['key']] = $res[$i]['value'];
        }
        return $aConfig;
    }

}
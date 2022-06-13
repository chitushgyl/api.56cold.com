<?php
namespace App\Http\Api\Pay;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsLittleOrder;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderCost;
use App\Models\Tms\TmsOrderDispatch;
use App\Models\Tms\TmsPayment;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use EasyWeChat\Foundation\Application;




class PayController extends Controller{

    /**
     * 极速版下单支付宝支付
     * */
    public function fastOrderAlipay(Request $request){
        $config    = config('tms.alipay_config');//引入配置文件参数
        $input     = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $type      = $request->input('type'); // 1  2  3 4
        $pay_type  = array_column(config('tms.fast_alipay_notify'),'notify','key');
        $self_id   = $request->input('self_id');// 订单ID
        $price     = $request->input('price');// 支付金额
        $price     = 0.01;
        if (!$user_info){
            $msg['code'] = 401;
            $msg['msg']  = '未登录，请完成登录！';
            return $msg;
        }
        /**虚拟数据
        $user_id = 'user_15615612312454564';
        $price = 0.01;
        $type = 1;
        $self_id = 'order_202103090937308279552773';
         * */
        if ($user_info->type == 'user'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
        if($type == 3){
            $payment = TmsPayment::where('order_id',$self_id)->where('order_id','!=','')->select('order_id','pay_result','paytype','state')->first();
            if($payment){
                $msg['code'] = 302;
                $msg['msg']  = '此订单不能重复上线';
                return $msg;
            }
        }
        include_once base_path( '/vendor/alipay/aop/AopClient.php');
        include_once base_path( '/vendor/alipay/aop/request/AlipayTradeAppPayRequest.php');
        $aop = new \AopClient();
        $request = new \AlipayTradeAppPayRequest();
        $aop->gatewayUrl = $config['gatewayUrl'];
        $aop->appId = $config['app_id'];
        $aop->rsaPrivateKey = $config['merchant_private_key'];
        $aop->format = $config['format'];
        $aop->charset = $config['charset'];
        $aop->signType = $config['sign_type'];
        //运单支付
        $subject = '订单支付';
//        $notifyurl = "http://api.56cold.com/alipay/appAlipay_notify";
        $notifyurl = $pay_type[$type];
        $aop->alipayrsaPublicKey = $config['alipay_public_key'];
        $bizcontent = json_encode([
            'body' => '支付宝支付',
            'subject' => $subject,
            'out_trade_no' => $self_id,//此订单号为商户唯一订单号
            'total_amount' => $price,//保留两位小数
            'product_code' => 'QUICK_MSECURITY_PAY',
            'passback_params' => $user_id
        ]);
        $request->setNotifyUrl($notifyurl);
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        $response = $aop->sdkExecute($request);
        return $response;
    }

    /**
     * 极速版支付宝下单支付回调
     * */
    public function fastOrderAlipayNotify(Request $request){
        include_once base_path( '/vendor/alipay/aop/AopClient.php');
        $aop = new \AopClient();
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuQzIBEB5B/JBGh4mqr2uJp6NplptuW7p7ZZ+uGeC8TZtGpjWi7WIuI+pTYKM4XUM4HuwdyfuAqvePjM2ch/dw4JW/XOC/3Ww4QY2OvisiTwqziArBFze+ehgCXjiWVyMUmUf12/qkGnf4fHlKC9NqVQewhLcfPa2kpQVXokx3l0tuclDo1t5+1qi1b33dgscyQ+Xg/4fI/G41kwvfIU+t9unMqP6mbXcBec7z5EDAJNmDU5zGgRaQgupSY35BBjW8YVYFxMXL4VnNX1r5wW90ALB288e+4/WDrjTz5nu5yeRUqBEAto3xDb5evhxXHliGJMqwd7zqXQv7Q+iVIPpXQIDAQAB';
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
            $now_time = date('Y-m-d H:i:s',time());
            $pay['order_id'] = $_POST['out_trade_no'];
            $pay['pay_number'] = $_POST['total_amount'] * 100;
            $pay['platformorderid'] = $_POST['trade_no'];
            $pay['create_time'] = $pay['update_time'] = $now_time;
            $pay['payname'] = $_POST['buyer_logon_id'];
            $pay['paytype'] = 'ALIPAY';//
            $pay['pay_result'] = 'SU';//
            $pay['state'] = 'in';//支付状态
            $pay['self_id'] = generate_id('pay_');
            file_put_contents(base_path('/vendor/alipay.txt'),$pay);
            $order = TmsLittleOrder::where('self_id',$_POST['out_trade_no'])->select(['self_id','total_user_id','group_code','order_status','group_name','order_type','send_shi_name','gather_shi_name'])->first();
            if ($order->order_status == 2 || $order->order_status == 3){
                echo 'success';
                return false;
            }
            $payment_info = TmsPayment::where('order_id',$_POST['out_trade_no'])->select(['pay_result','state','order_id','dispatch_id'])->first();
            if ($payment_info){
                echo 'success';
                return false;
            }
            if ($order->total_user_id){
                $pay['total_user_id'] = $_POST['passback_params'];
                $wallet['total_user_id'] = $_POST['passback_params'];
                $where['total_user_id'] = $_POST['passback_params'];
            }else{
                $pay['group_code'] = $_POST['passback_params'];
                $pay['group_code'] = $_POST['passback_params'];
                $wallet['group_code'] = $_POST['passback_params'];
                $wallet['group_name'] = $order->group_name;
                $where['group_code'] = $_POST['passback_params'];
            }
            TmsPayment::insert($pay);
            $capital = UserCapital::where($where)->first();
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'out';
            $wallet['capital_type'] = 'wallet';
            $wallet['money'] = $_POST['total_amount'] * 100;
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['now_money'] = $capital->money;
            $wallet['now_money_md'] = get_md5($capital->money);
            $wallet['wallet_status'] = 'SU';
            UserWallet::insert($wallet);
            file_put_contents(base_path('/vendor/alipay1.txt'),$wallet);
            if ($order->order_type == 'line'){
                $order_update['order_status'] = 2;
            }else{
                $order_update['order_status'] = 2;
            }
            $order_update['on_line_flag'] = 'Y';
            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $id = TmsLittleOrder::where('self_id',$_POST['out_trade_no'])->update($order_update);
            /**修改费用数据为可用**/
//            $money['delete_flag']                = 'Y';
//            $money['settle_flag']                = 'W';
//            $tmsOrderCost = TmsOrderCost::where('order_id',$_POST['out_trade_no'])->select('self_id')->get();
//            file_put_contents(base_path('/vendor/alipay2.txt'),$tmsOrderCost);
//            if ($tmsOrderCost){
//                $money_list = array_column($tmsOrderCost->toArray(),'self_id');
//                TmsOrderCost::whereIn('self_id',$money_list)->update($money);
//                file_put_contents(base_path('/vendor/alipay3.txt'),'123');
//            }

            /**推送**/
//            $center_list = '有从'. $order['send_shi_name'].'发往'.$order['gather_shi_name'].'的整车订单';
//            $push_contnect = array('title' => "赤途承运端",'content' => $center_list , 'payload' => "订单信息");
////                        $A = $this->send_push_message($push_contnect,$data['send_shi_name']);
//            if ($order->order_type == 'vehicle'){
//                if($order->group_code){
//                    $group = SystemGroup::where('self_id',$order->group_code)->select('self_id','group_name','company_type')->first();
//                    if($group->company_type != 'TMS3PL'){
//                        $A = $this->send_push_msg('订单信息','有新订单',$center_list);
//                    }
//                }else{
//                    $A = $this->send_push_msg('订单信息','有新订单',$center_list);
//                }
//            }

            if ($id){
                echo 'success';
            }else{
                echo 'fail';
            }

        } else {
            echo 'fail';
        }

    }

    /*
     * 极速版货到付款支付
     * */
    public function fastPaymentAlipayNotify(Request $request){

    }

    /**
     * 快捷下单余额支付
     * */
    public function fastOrderBalancePay(Request $request){
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数

        if (!$user_info){
            $msg['code'] = 401;
            $msg['msg']  = '未登录，请完成登录！';
            return $msg;
        }
        // 订单ID
        $self_id = $request->input('self_id');
        // 支付金额
        $price = $request->input('price');
        $type  = $request->input('type'); // 支付宝 alipay  微信 wechat
        /**虚拟数据
        //        $price   = 0.01;
        //        $self_id = 'order_202103121712041799645968';
         * */
        $now_time = date('Y-m-d H:i:s',time());
        $pay['order_id'] = $self_id;
        $pay['pay_number'] = $price*100;
        $pay['platformorderid'] = generate_id('');
        $pay['create_time'] = $pay['update_time'] = $now_time;
        $pay['payname'] = $user_info->tel;
        $pay['paytype'] = 'BALANCE';//
        $pay['pay_result'] = 'SU';//
        $pay['state'] = 'in';//支付状态
        $pay['self_id'] = generate_id('pay_');
        $order = TmsLittleOrder::where('self_id',$self_id)->select(['total_user_id','group_code','order_status','group_name','order_type','send_shi_name','gather_shi_name'])->first();

        if ($user_info->type == 'user'){
            $pay['total_user_id'] = $user_info->total_user_id;
            $wallet['total_user_id'] = $user_info->total_user_id;
            $capital_where['total_user_id'] = $user_info->total_user_id;
        }else{
            $pay['group_code'] = $user_info->group_code;
            $pay['group_name'] = $user_info->group_name;
            $wallet['group_code'] = $user_info->group_code;
            $wallet['group_name'] = $user_info->group_name;
            $capital_where['group_code'] = $user_info->group_code;
        }
        $userCapital = UserCapital::where($capital_where)->first();
        if ($userCapital->money < $price){
            $msg['code'] = 302;
            $msg['msg']  = '余额不足';
            return $msg;
        }
        $capital['money'] = $userCapital->money - $price*100;
        $capital['update_time'] = $now_time;
        UserCapital::where($capital_where)->update($capital);
        $wallet['self_id'] = generate_id('wallet_');
        $wallet['produce_type'] = 'out';
        $wallet['capital_type'] = 'wallet';
        $wallet['create_time'] = $now_time;
        $wallet['update_time'] = $now_time;
        $wallet['money']       = $price*100;
        $wallet['now_money'] = $capital['money'];
        $wallet['now_money_md'] = get_md5($capital['money']);
        $wallet['wallet_status'] = 'SU';
        UserWallet::insert($wallet);
        TmsPayment::insert($pay);
        if ($order->order_type == 'line'){
            $order_update['order_status'] = 2;
        }else{
            $order_update['order_status'] = 2;
        }
        $order_update['on_line_flag'] = 'Y';
        $order_update['update_time'] = date('Y-m-d H:i:s',time());
        $id = TmsLittleOrder::where('self_id',$self_id)->update($order_update);
        /**修改费用数据为可用**/
//        $money['delete_flag']                = 'Y';
//        $money['settle_flag']                = 'W';
//        $tmsOrderCost = TmsOrderCost::where('order_id',$self_id)->select('self_id')->get();
//        if ($tmsOrderCost){
//            $money_list = array_column($tmsOrderCost->toArray(),'self_id');
//            TmsOrderCost::whereIn('self_id',$money_list)->update($money);
//        }
//        if($userCapital->money >= $price){
//            $tmsOrderDispatch = TmsOrderDispatch::where('order_id',$self_id)->select('self_id')->get();
//            if ($tmsOrderDispatch){
//                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
//                $orderStatus = TmsOrderDispatch::whereIn('self_id',$dispatch_list)->update($order_update);
//            }
        /**推送**/
//            $center_list = '有从'. $order['send_shi_name'].'发往'.$order['gather_shi_name'].'的整车订单';
//            $push_contnect = array('title' => "赤途承运端",'content' => $center_list , 'payload' => "订单信息");
////                        $A = $this->send_push_message($push_contnect,$data['send_shi_name']);
//            if($order->order_type == 'vehicle'){
//                if($order->group_code){
//                    $group = SystemGroup::where('self_id',$order->group_code)->select('self_id','group_name','company_type')->first();
//                    if($group->company_type != 'TMS3PL'){
//                        $A = $this->send_push_msg('订单信息','有新订单',$center_list);
//                    }
//                }else{
//                    $A = $this->send_push_msg('订单信息','有新订单',$center_list);
//                }
//            }
//        }

        if ($id){
            $msg['code'] = 200;
            $msg['msg']  = '支付成功！';
            return $msg;
        }else{
            $msg['code'] = 303;
            $msg['msg']  = '支付失败！';
            return $msg;
        }
    }


    /*
     * 极速版微信支付
     * */
    public function wechat(Request $request){
        $pay_type  = array_column(config('tms.wechat_notify'),'notify','key');
        $input = $request->all();
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $self_id = $request->input('self_id');//订单ID
        $price = $request->input('price');//支付金额
        $type  = $request->input('type');
        $user_type  = $request->input('user_type');
        if (empty($type)){
            $msg['code'] = 303;
            $msg['msg']  = '请选择支付类型';
            return $msg;
        }
//        $price = 0.01;
        /**虚拟数据
        $user_id = 'user_15615612312454564';
        $price = 0.01;
        $type = 1;
        $self_id = 'order_202103090937308279552773';
         * */
        if($type == 3){
            $payment = TmsPayment::where('dispatch_id',$self_id)->select('dispatch_id','pay_result','paytype','state')->first();
            if($payment){
                $msg['code'] = 302;
                $msg['msg']  = '此订单不能重复上线';
                return $msg;
            }
        }
        if ($user_info->type == 'user'){
            $user_id = $user_info->total_user_id;
        }else{
            $user_id = $user_info->group_code;
        }
        include_once base_path( '/vendor/wxAppPay/weixin.php');
        $out_trade_no = $self_id;
        $noturl = $pay_type[$type];
        if ($user_type == 'user'){
            $config    = config('tms.fast_wechat_config_user');//引入配置文件参数
        }else{
            $config    = config('tms.fast_wechat_config_driver');//引入配置文件参数
        }

        $appid  = $config['appid'];
        $mch_id = $config['mch_id'];
        $key    = $config['key'];

        $notify_url = $noturl;
        $wechatAppPay = new \wxAppPay($appid,$mch_id,$notify_url,$key);
        $params['body'] = '订单支付';                       //商品描述
        $params['out_trade_no'] = $out_trade_no;    //自定义的订单号
        $params['total_fee'] = $price*100;                       //订单金额 只能为整数 单位为分
        $params['trade_type'] = 'APP';                      //交易类型 JSAPI | NATIVE | APP | WAP
        $params['attach'] = $user_id;                      //附加参数（用户ID）
        $result = $wechatAppPay->unifiedOrder($params);
        // print_r($result); // result中就是返回的各种信息信息，成功的情况下也包含很重要的prepay_id
        //2.创建APP端预支付参数
        /** @var TYPE_NAME $result */
        $data = @$wechatAppPay->getAppPayParams($result['prepay_id']);
        return json_encode($data);
    }

    /*** 微信支付回调**/
    public function wechatNotify(Request $request){
        ini_set('date.timezone','Asia/Shanghai');
        error_reporting(E_ERROR);
        $result = file_get_contents('php://input', 'r');
        $array_data = json_decode(json_encode(simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if ($array_data['return_code'] == 'SUCCESS') {
            $now_time = date('Y-m-d H:i:s',time());
            $pay['order_id'] = $array_data['out_trade_no'];//订单号
            $pay['pay_number'] = $array_data['total_fee'];//价格
            $pay['platformorderid'] = $array_data['transaction_id'];//微信交易号
            $pay['create_time']  = $pay['update_time'] = $now_time;
            $pay['payname'] = $array_data['openid'];//微信账号
            $pay['paytype'] = 'WECHAT';//微信账号
            $pay['pay_result'] = 'SU';//微信账号
            $pay['state'] = 'in';//支付状态
            $pay['self_id'] = generate_id('pay_');//微信账号
            $order = TmsLittleOrder::where('self_id',$array_data['out_trade_no'])->select(['self_id','total_user_id','group_code','order_status','group_name','order_type','send_shi_name','gather_shi_name'])->first();
            if ($order->order_status == 2 || $order->order_status == 3){
                echo 'success';
                return false;
            }
            $payment_info = TmsPayment::where('order_id',$array_data['out_trade_no'])->select(['pay_result','state','order_id','dispatch_id'])->first();
            if ($payment_info){
                echo 'success';
                return false;
            }
            file_put_contents(base_path('/vendor/alipay123444.txt'),$order);
            if ($order->total_user_id){
                $pay['total_user_id'] = $array_data['attach'];
                $wallet['total_user_id'] = $array_data['attach'];
                $where['total_user_id'] = $array_data['attach'];
            }else{
                $pay['group_code'] = $array_data['attach'];
                $pay['group_name'] = $order->group_name;
                $wallet['group_code'] = $array_data['attach'];
                $wallet['group_name'] = $order->group_name;
                $where['group_code'] = $array_data['attach'];
            }
            TmsPayment::insert($pay);
            $capital = UserCapital::where($where)->first();
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'out';
            $wallet['capital_type'] = 'wallet';
            $wallet['money'] = $array_data['total_fee'];
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['now_money'] = $capital->money;
            $wallet['now_money_md'] = get_md5($capital->money);
            $wallet['wallet_status'] = 'SU';
            $wallet11 = UserWallet::insert($wallet);
            file_put_contents(base_path('/vendor/alipay123444.txt'),$wallet11);
            if ($order->order_type == 'line'){
                $order_update['order_status'] = 3;
            }else{
                $order_update['order_status'] = 2;
            }
            $order_update['on_line_flag'] = 'Y';
            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            file_put_contents(base_path('/vendor/alipay123.txt'),$order_update);
            file_put_contents(base_path('/vendor/alipay1234.txt'),$array_data['out_trade_no']);
            $id = TmsLittleOrder::where('self_id',$array_data['out_trade_no'])->update($order_update);
            file_put_contents(base_path('/vendor/alipay123.txt'),$id);
            /**修改费用数据为可用**/

//            /**推送**/
//            $center_list = '有从'. $order['send_shi_name'].'发往'.$order['gather_shi_name'].'的整车订单';
//            $push_contnect = array('title' => "赤途承运端",'content' => $center_list , 'payload' => "订单信息");
////                        $A = $this->send_push_message($push_contnect,$data['send_shi_name']);
//            if ($order->order_type == 'vehicle') {
//                if ($order->group_code) {
//                    $group = SystemGroup::where('self_id', $order->group_code)->select('self_id', 'group_name', 'company_type')->first();
//                    if ($group->company_type != 'TMS3PL') {
//                        $A = $this->send_push_msg('订单信息', '有新订单', $center_list);
//                    }
//                } else {
//                    $A = $this->send_push_msg('订单信息', '有新订单', $center_list);
//                }
//            }
            if ($id){
                echo 'success';
            }else{
                echo 'fail';
            }
        }else{
            echo 'fail';
        }
    }

    /*
     * 货到付款微信支付回调
     * */
    public function paymentWechatNotify(Request $request){
        ini_set('date.timezone','Asia/Shanghai');
        error_reporting(E_ERROR);
        $result = file_get_contents('php://input', 'r');
        $array_data = json_decode(json_encode(simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if ($array_data['return_code'] == 'SUCCESS') {
            $now_time = date('Y-m-d H:i:s',time());
            $pay['order_id'] = $array_data['out_trade_no'];//订单号
            $pay['pay_number'] = $array_data['total_fee'];//价格
            $pay['platformorderid'] = $array_data['transaction_id'];//微信交易号
            $pay['create_time']  = $pay['update_time'] = $now_time;
            $pay['payname'] = $array_data['openid'];//微信账号
            $pay['paytype'] = 'WECHAT';//微信账号
            $pay['pay_result'] = 'SU';//微信账号
            $pay['state'] = 'in';//支付状态
            $pay['self_id'] = generate_id('pay_');//微信账号
            $order = TmsOrder::where('self_id',$array_data['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type','pay_state'])->first();
            if ($order->pay_state == 'Y'){
                echo 'success';
                return false;
            }
            $payment_info = TmsPayment::where('order_id',$array_data['out_trade_no'])->select(['pay_result','state','order_id','dispatch_id'])->first();
            if ($payment_info){
                echo 'success';
                return false;
            }
            if ($order->total_user_id){
                $pay['total_user_id'] = $array_data['attach'];
                $wallet['total_user_id'] = $array_data['attach'];
                $where['total_user_id'] = $array_data['attach'];
            }else{
                $pay['group_code'] = $array_data['attach'];
                $pay['group_name'] = $order->group_name;
                $wallet['group_code'] = $array_data['attach'];
                $wallet['group_name'] = $order->group_name;
                $where['group_code'] = $array_data['attach'];
            }
            TmsPayment::insert($pay);
            $capital = UserCapital::where($where)->first();
            $wallet['self_id'] = generate_id('wallet_');
            $wallet['produce_type'] = 'out';
            $wallet['capital_type'] = 'wallet';
            $wallet['money'] = $array_data['total_fee'];
            $wallet['create_time'] = $now_time;
            $wallet['update_time'] = $now_time;
            $wallet['now_money'] = $capital->money;
            $wallet['now_money_md'] = get_md5($capital->money);
            $wallet['wallet_status'] = 'SU';
//            $order_update['order_status'] = 6;
            $order_update['pay_state'] = 'Y';
            $order_update['update_time'] = date('Y-m-d H:i:s',time());
            $id = TmsOrder::where('self_id',$array_data['out_trade_no'])->update($order_update);
            if($order->order_type == 'vehicle'){
                $dispatch_where['pay_status'] = 'Y';
                $dispatch_where['update_time'] = $now_time;
                TmsOrderDispatch::where('order_id',$array_data['out_trade_no'])->update($dispatch_where);
            }
            /**修改费用数据为可用**/
            $money['delete_flag']                = 'Y';
            $money['settle_flag']                = 'W';
            $tmsOrderCost = TmsOrderCost::where('order_id',$array_data['out_trade_no'])->select('self_id')->get();
            if ($tmsOrderCost){
                $money_list = array_column($tmsOrderCost->toArray(),'self_id');
                TmsOrderCost::whereIn('self_id',$money_list)->update($money);
            }
            if ($id){
                echo 'success';
            }else{
                echo 'fail';
            }
        }else{
            echo 'fail';
        }
    }


       /**** 货到付款余额支付*/
       public function paymentBalancePay(Request $request){

       }

}
?>

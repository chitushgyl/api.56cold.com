<?php
namespace App\Http\Api\Crondtab;


use App\Http\Controllers\Controller;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderDispatch;
use App\Models\Tms\TmsPayment;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use EasyWeChat\Foundation\Application;
use WxPayApi as WxPayQ;

class CrondtabController extends Controller {


    /**
     *定时完成订单 /api/crondtab/order_done
     */
    public function order_done(){
        $now_time  = time();
        $where = [
            ['order_status','=',5]
        ];
        $select = ['self_id','order_status','total_money','pay_type','group_code','group_name','total_user_id','order_type'];
        $order_list = TmsOrder::where($where)->select($select)->get();
//        dump($order_list->toArray());
        foreach ($order_list as $k => $v) {
            if ($now_time - strtotime($v->create_time) >= 6 * 24 * 3600) {

                $update['update_time'] = date('Y-m-d H:i:s',time());
                $update['order_status'] = 6;
                $id = TmsOrder::where('self_id',$v->self_id)->update($update);
                /** 查找所有的运输单 修改运输状态**/
                $TmsOrderDispatch = TmsOrderDispatch::where('order_id', $v->self_id)->select('self_id')->get();

                if ($TmsOrderDispatch) {
                    $dispatch_list = array_column($TmsOrderDispatch->toArray(), 'self_id');
//                dump($dispatch_list);
                    $orderStatus = TmsOrderDispatch::where('delete_flag','=','Y')->whereIn('self_id',$dispatch_list)->update($update);

                    /*** 订单完成后，如果订单是在线支付，添加运费到承接司机或3pl公司余额 **/
                    if ($orderStatus && $v->pay_type){
                        foreach ($dispatch_list as $key => $value) {
//                    dd($value);
                            $carriage_order = TmsOrderDispatch::where('self_id', '=', $value)->first();
                            $idit = substr($carriage_order->receiver_id, 0, 5);
                            if ($idit == 'user_') {
                                $wallet_where = [
                                    ['total_user_id', '=', $carriage_order->receiver_id]
                                ];
                                $data['wallet_type'] = 'user';
                                $data['total_user_id'] = $carriage_order->receiver_id;
                            } else {
                                $wallet_where = [
                                    ['group_code', '=', $carriage_order->receiver_id]
                                ];
                                $data['wallet_type'] = '3PLTMS';
                                $data['group_code'] = $carriage_order->receiver_id;
                            }

                            $wallet = UserCapital::where($wallet_where)->select(['self_id', 'money'])->first();

                            $money['money'] = $wallet->money + $carriage_order->on_line_money;
                            $data['money'] = $carriage_order->on_line_money;
                            if ($carriage_order->group_code == $carriage_order->receiver_id) {
                                $money['money'] = $wallet->money + $carriage_order->total_money;
                                $data['money'] = $carriage_order->total_money;
                            }

                            $money['update_time'] = date('Y-m-d H:i:s',time());
                            UserCapital::where($wallet_where)->update($money);

                            $data['self_id'] = generate_id('wallet_');
                            $data['produce_type'] = 'in';
                            $data['capital_type'] = 'wallet';
                            $data['create_time'] = date('Y-m-d H:i:s',time());
                            $data['update_time'] = date('Y-m-d H:i:s',time());
                            $data['now_money'] = $money['money'];
                            $data['now_money_md'] = get_md5($money['money']);
                            $data['wallet_status'] = 'SU';

                            UserWallet::insert($data);
                        }

                    }
                }
            }
        }
    }


    /**
     *定时取消订单  /api/crondtab/order_unline
     */
    public function order_unline(){
        $now_time  = time();
        $where = [
            ['order_status','=',2],
            ['order_type','=','vehicle'],
            ['on_line_flag','=','Y']
        ];
        $select = ['self_id','order_status','total_money','pay_type','group_code','group_name','total_user_id','order_type','on_line_flag','gather_time','order_id',];
        $select1 = ['self_id','order_status','total_money','pay_type','group_code','group_name','total_user_id','order_type','gather_time','total_money'];
        $order_list = TmsOrderDispatch::where($where)->select($select)->get();
        foreach ($order_list as $k => $v) {
            if ($now_time > strtotime($v->gather_time)) {
                $order = TmsOrder::where('self_id',$v->order_id)->select($select1)->first();
                $update['order_status'] = 7;
                $update['update_time']  = date('Y-m-d H:i:s',time());
                TmsOrderDispatch::where('self_id',$v->self_id)->update($update);
                TmsOrder::where('self_id',$v->order_id)->update($update);
                if ($order->pay_type == 'online'){
                    if ($order->total_user_id){
                        $wallet = UserCapital::where('total_user_id',$order->total_user_id)->select(['self_id','money'])->first();
                        $payment = TmsPayment::where('order_id',$v->order_id)->select('pay_number','order_id','dispatch_id')->first();
                        $wallet_update['money'] = $payment->pay_number + $wallet->money;
                        $wallet_update['update_time'] = date('Y-m-d H:i:s',time());
                        UserCapital::where('total_user_id',$order->total_user_id)->update($wallet_update);
                        $data['wallet_type'] = 'user';
                        $data['total_user_id'] = $order->total_user_id;
                        UserWallet::insert($data);
                    }else{
                        $wallet = UserCapital::where('group_code',$order->group_code)->select(['self_id','money'])->first();
                        $payment = TmsPayment::where('order_id',$v->order_id)->select('pay_number','order_id','dispatch_id')->first();
                        $wallet_update['money'] = $payment->pay_number + $wallet->money;
                        $wallet_update['update_time'] = date('Y-m-d H:i:s',time());
                        UserCapital::where('group_code',$order->group_code)->update($wallet_update);
                        $data['group_code'] = $order->group_code;
                        $data['wallet_type'] = 'company';
                    }
                    $data['self_id'] = generate_id('wallet_');
                    $data['produce_type'] = 'refund';
                    $data['capital_type'] = 'wallet';
                    $data['money'] = $payment->pay_number;
                    $data['create_time'] = $now_time;
                    $data['update_time'] = $now_time;
                    $data['now_money'] = $wallet_update['money'];
                    $data['now_money_md'] = get_md5($wallet_update['money']);
                    $data['wallet_status'] = 'SU';
                    UserWallet::insert($data);

                }
            }
        }
    }

    /**
     * 查询订单是否支付宝支付
     * */
    public function queryAliapy(){
        include_once base_path('/vendor/alipay/pagepay/service/AlipayTradeService.php');
        include_once base_path('/vendor/alipay/pagepay/buildermodel/AlipayTradeQueryContentBuilder.php');
        $config    = config('tms.alipay_config');//引入配置文件参数
        $now_time  = date('Y-m-d H:i:s',time());
        $where = [
            ['order_status','=',1],
            ['pay_type','=','online'],
            ['create_time','>','2021-09-26 12:00:00']
        ];
        $select = ['self_id','order_status','total_money','pay_type','group_code','group_name','total_user_id','order_type','create_time'];
        $order_list = TmsOrder::where($where)->select($select)->get();

        foreach ($order_list as $k => $v) {
            //商户订单号，商户网站订单系统中唯一订单号
            $out_trade_no = trim($v->self_id);

            //支付宝交易号
//        $trade_no = trim($_POST['WIDTQtrade_no']);
            //请二选一设置
            //构造参数
            $RequestBuilder = new \AlipayTradeQueryContentBuilder();
            $RequestBuilder->setOutTradeNo($out_trade_no);
//        $RequestBuilder->setTradeNo($trade_no);

            $aop = new \AlipayTradeService($config);

            /**
             * alipay.trade.query (统一收单线下交易查询)
             * @param $builder 业务参数，使用buildmodel中的对象生成。
             * @return $response 支付宝返回的信息
             */
            $response = $aop->Query($RequestBuilder);
            if ($response->code == 10000 && $response->msg == 'Success' && $response->trade_status == 'TRADE_SUCCESS'){
                $now_time = date('Y-m-d H:i:s',time());
                $pay['order_id'] = $response->out_trade_no;
                $pay['pay_number'] = $response->total_amount;
                $pay['platformorderid'] = $response->trade_no;
                $pay['create_time'] = $pay['update_time'] = $now_time;
                $pay['payname'] = $response->buyer_logon_id;
                $pay['paytype'] = 'ALIPAY';//
                $pay['pay_result'] = 'SU';//
                $pay['state'] = 'in';//支付状态
                $pay['self_id'] = generate_id('pay_');
                file_put_contents(base_path('/vendor/alipay.txt'),$pay);
                $order = TmsOrder::where('self_id',$response->out_trade_no)->select(['total_user_id','group_code','order_status','group_name','order_type','send_shi_name','gather_shi_name'])->first();
                if ($order->order_status == 2 || $order->order_status == 3){
                    echo 'success';
                    return false;
                }
                if ($order->total_user_id){
                    $pay['total_user_id'] = $order->total_user_id;
                    $wallet['total_user_id'] = $order->total_user_id;
                    $where = [
                        ['total_user_id','=',$order->total_user_id]
                    ];
                }else{
                    $pay['group_code'] = $order->group_code;
                    $pay['group_code'] = $order->group_code;
                    $wallet['group_code'] = $order->group_code;
//                $wallet['group_name'] = $order->group_name;
                    $where = [
                        ['group_code','=',$order->group_code]
                    ];
                }
                TmsPayment::insert($pay);
                $capital = UserCapital::where($where)->first();
                $wallet['self_id'] = generate_id('wallet_');
                $wallet['produce_type'] = 'out';
                $wallet['capital_type'] = 'wallet';
                $wallet['money'] = $response->total_amount;
                $wallet['create_time'] = $now_time;
                $wallet['update_time'] = $now_time;
                $wallet['now_money'] = $capital->money;
                $wallet['now_money_md'] = get_md5($capital->money);
                $wallet['wallet_status'] = 'SU';
                UserWallet::insert($wallet);

                if ($order->order_type == 'line'){
                    $order_update['order_status'] = 3;
                }else{
                    $order_update['order_status'] = 2;
                }
                $order_update['update_time'] = date('Y-m-d H:i:s',time());
                $id = TmsOrder::where('self_id',$response->out_trade_no)->update($order_update);
                /**修改费用数据为可用**/
                $money['delete_flag']                = 'Y';
                $money['settle_flag']                = 'W';
                $tmsOrderCost = TmsOrderCost::where('order_id',$response->out_trade_no)->select('self_id')->get();
                if ($tmsOrderCost){
                    $money_list = array_column($tmsOrderCost->toArray(),'self_id');
                    TmsOrderCost::whereIn('self_id',$money_list)->update($money);
                }
                $tmsOrderDispatch = TmsOrderDispatch::where('order_id',$response->out_trade_no)->select('self_id','dispatch_flag')->get();
                if ($tmsOrderDispatch){
//                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
                    foreach ($tmsOrderDispatch as $key =>$value){
                        if ($value->dispatch_flag != 'N'){
                            $orderStatus = TmsOrderDispatch::where('self_id',$value->self_id)->update($order_update);
                        }
                    }

                }
            }
        }

    }

    /**
     * 查询订单是否微信支付
     * */
    public function queryWechat(){
        include_once base_path('/vendor/wxpay/lib/WxPay.Api.php');
        $config    = config('tms.alipay_config');//引入配置文件参数
        $now_time  = date('Y-m-d H:i:s',time());
        $where = [
            ['order_status','=',1],
            ['pay_type','=','online'],
            ['pay_state','=','N'],
            ['create_time','>','2021-09-26 12:00:00']
        ];
        $select = ['self_id','order_status','total_money','pay_type','group_code','group_name','total_user_id','order_type','create_time'];
        $order_list = TmsOrder::where($where)->select($select)->get();
        foreach ($order_list as $k => $v) {
            $input = new \WxPayOrderQuery();
            $input->SetOut_trade_no($v->self_id);
            $result = WxPayQ::orderQuery($input);
            if($result['result_code'] == 'SUCCESS' && $result['return_code']=='SUCCESS' && $result['return_msg'] == 'OK'){
                $now_time = date('Y-m-d H:i:s',time());
                $pay['order_id'] = $result['out_trade_no'];//订单号
                $pay['pay_number'] = $result['total_fee'];//价格
                $pay['platformorderid'] = $result['transaction_id'];//微信交易号
                $pay['create_time']  = $pay['update_time'] = $now_time;
                $pay['payname'] = $result['openid'];//微信账号
                $pay['paytype'] = 'WECHAT';//微信账号
                $pay['pay_result'] = 'SU';//微信账号
                $pay['state'] = 'in';//支付状态
                $pay['self_id'] = generate_id('pay_');//微信账号
                $order = TmsOrder::where('self_id',$result['out_trade_no'])->select(['total_user_id','group_code','order_status','group_name','order_type','send_shi_name','gather_shi_name'])->first();
                if ($order->order_status == 2 || $order->order_status == 3){
                    echo 'success';
                    return false;
                }
                $payment_info = TmsPayment::where('order_id',$result['out_trade_no'])->select(['pay_result','state','order_id','dispatch_id'])->first();
                if ($payment_info){
                    echo 'success';
                    return false;
                }
                if ($order->total_user_id){
                    $pay['total_user_id'] = $result['attach'];
                    $wallet['total_user_id'] = $result['attach'];
                    $where = [
                        ['total_user_id','=',$result['attach']]
                    ];
                }else{
                    $pay['group_code'] = $result['attach'];
                    $pay['group_name'] = $order->group_name;
                    $wallet['group_code'] = $result['attach'];
                    $wallet['group_name'] = $order->group_name;
                    $where = [
                        ['group_code','=',$result['attach']]
                    ];
                }
                TmsPayment::insert($pay);
                $capital = UserCapital::where($where)->first();
                $wallet['self_id'] = generate_id('wallet_');
                $wallet['produce_type'] = 'out';
                $wallet['capital_type'] = 'wallet';
                $wallet['money'] = $result['total_fee'];
                $wallet['create_time'] = $now_time;
                $wallet['update_time'] = $now_time;
                $wallet['now_money'] = $capital->money;
                $wallet['now_money_md'] = get_md5($capital->money);
                $wallet['wallet_status'] = 'SU';
                UserWallet::insert($wallet);
                if ($order->order_type == 'line'){
                    $order_update['order_status'] = 3;
                }else{
                    $order_update['order_status'] = 2;
                }
                $order_update['update_time'] = date('Y-m-d H:i:s',time());
                $id = TmsOrder::where('self_id',$result['out_trade_no'])->update($order_update);
                /**修改费用数据为可用**/
                $money['delete_flag']                = 'Y';
                $money['settle_flag']                = 'W';
                $tmsOrderCost = TmsOrderCost::where('order_id',$result['out_trade_no'])->select('self_id')->get();
                if ($tmsOrderCost){
                    $money_list = array_column($tmsOrderCost->toArray(),'self_id');
                    TmsOrderCost::whereIn('self_id',$money_list)->update($money);
                }
                $tmsOrderDispatch = TmsOrderDispatch::where('order_id',$result['out_trade_no'])->select('self_id')->get();
                if ($tmsOrderDispatch){
                    $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
                    $orderStatus = TmsOrderDispatch::whereIn('self_id',$dispatch_list)->update($order_update);
                }
            }else{
                $msg['code'] = 301;
                $msg['msg']  = $result['err_code_des'];
                return $msg;
            }
        }
    }
































}

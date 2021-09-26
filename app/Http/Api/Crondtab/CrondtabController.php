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
        $now_time  = time();
        $where = [
            ['order_status','=',1],
            ['pay_type','=','online']
        ];
        $select = ['self_id','order_status','total_money','pay_type','group_code','group_name','total_user_id','order_type'];
        $order_list = TmsOrder::where($where)->select($select)->get();
        $self_id = 'order_202109251115091435951796';
//        foreach ($order_list as $k => $v) {
//            $aop                        = new \AopClient ();
//            $aop->appId                 = $config['app_id'];
//            $aop->rsaPrivateKeyFilePath = $config['merchant_private_key'];//RSA私钥
//            $aop->alipayPublicKey       = $config['alipay_public_key'];//支付宝公钥
//            $request                    = new AlipayTradeQueryRequest ();
//            $paramArray                 = array();
//            $paramArray['out_trade_no'] = $v->self_id;
////        $paramArray['trade_no']     ='2016031421007864720242676619';
//            $request->biz_content       =json_encode($paramArray);
//            $result                     = $aop->execute ($request, NULL );
//            dd($result);
//        }

        //商户订单号，商户网站订单系统中唯一订单号
        $out_trade_no = trim($self_id);

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
        var_dump($response);


    }

    /**
     * 微信查询订单是否支付  alipay/queryWechat
     * */
    public function queryWechat(Request $request){
        $self_id = $request->input('self_id');//订单ID
//        $self_id = 'order_202109151834352525376788';
        include_once base_path('/vendor/wxpay/lib/WxPay.Api.php');
        $input = new \WxPayOrderQuery();
        $input->SetOut_trade_no($self_id);
        $result = WxPayQ::orderQuery($input);
        if($result['result_code'] == 'SUCCESS' && $result['return_code']=='SUCCESS' && $result['return_msg'] == 'OK'){
            $msg['code'] = 200;
            $msg['msg']  = '支付成功';
            return $msg;
        }else{
            $msg['code'] = 301;
            $msg['msg']  = $result['err_code_des'];
            return $msg;
        }
    }
































}

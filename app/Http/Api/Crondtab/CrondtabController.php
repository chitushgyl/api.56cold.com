<?php
namespace App\Http\Api\Crondtab;


use App\Http\Controllers\Controller;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderDispatch;
use Illuminate\Support\Facades\Validator;

class CrondtabController extends Controller {


    /**
     *定时完成订单
     */
    public function order_done(){
        $now_time  = time();
        $where = [
            ['order_status','=',5]
        ];
        $select = ['self_id','order_status','total_money','pay_type','group_code','group_name','total_user_id','order_type'];
        $order_list = TmsOrder::where($where)->select($select)->get();
        dump($order_list->toArray());
        foreach ($order_list as $k => $v) {
            if ($now_time - strtotime($v->create_time) >= 6 * 24 * 3600) {

            $update['update_time'] = $now_time;
            $update['order_status'] = 6;
//            $id = TmsOrder::where('self_id',$v->self_id)->update($update);
            /** 查找所有的运输单 修改运输状态**/
            $TmsOrderDispatch = TmsOrderDispatch::where('order_id', $v->self_id)->select('self_id')->get();

            if ($TmsOrderDispatch) {
                $dispatch_list = array_column($TmsOrderDispatch->toArray(), 'self_id');
                dump($dispatch_list);
//                $orderStatus = TmsOrderDispatch::where('delete_flag','=','Y')->whereIn('self_id',$dispatch_list)->update($update);

                /*** 订单完成后，如果订单是在线支付，添加运费到承接司机或3pl公司余额 **/
//                if ($orderStatus && $v->pay_type){
                foreach ($dispatch_list as $key => $value) {
                    dd($value);
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

                    $money['update_time'] = $now_time;
                    UserCapital::where($wallet_where)->update($money);

                    $data['self_id'] = generate_id('wallet_');
                    $data['produce_type'] = 'in';
                    $data['capital_type'] = 'wallet';
                    $data['create_time'] = $now_time;
                    $data['update_time'] = $now_time;
                    $data['now_money'] = $money['money'];
                    $data['now_money_md'] = get_md5($money['money']);
                    $data['wallet_status'] = 'SU';

                    UserWallet::insert($data);
                }

//                }
             }
           }
        }
    }


    /**
     *定时取消订单
     */
    public function order_unline(){

    }
































}

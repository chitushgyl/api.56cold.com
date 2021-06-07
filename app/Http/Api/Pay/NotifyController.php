<?php
namespace App\Http\Api\Pay;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
//use EasyWeChat\Factory;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Kernel\Support\Collection;
use EasyWeChat\Kernel\Support\XML;
//use EasyWeChat\Payment\Order;
// use Illuminate\Support\Facades\Schema;
use App\Models\User\UserTotal;
//use App\Models\User\UserWallet;
//use App\Models\User\UserCapital;
use App\Http\Controllers\ComputeController as Compute;
use App\Models\Pay\Pay;
use App\Models\Shop\ShopOrder;
use App\Models\Shop\ShopOrderList;




class NotifyController extends Controller{
    /**	平台方的支付回调控制器		/notify/notify  */
    public function notify(Request $request,Compute $compute){
        $request = Request::createFromGlobals();
        try {
            $xml = XML::parse(strval($request->getContent()));
        } catch (\Throwable $e) {
            //Log::info($e);  Log::info(44444);
            throw new Exception('Invalid request XML: ' . $e->getMessage(), 400);
        }
        if (!is_array($xml) || empty($xml)) {
            //Log::info('没有走这里');
            throw new Exception('Invalid request XML.', 400);
        }

        $message=new Collection($xml);

        $order_where=[
            ['self_id','=',$message['out_trade_no']],
        ];
        $select=['pay_status','total_user_id','pay_money','pay_wallet_money'];
        $order_info=Pay::where($order_where)->select($select)->first();

        if (!$order_info) {
            $fail('Order not exist.');
        }

        if ($order_info->pay_status  != '1') {
            return true;
        }

        if($message['result_code'] === 'SUCCESS'){
            //到公用方法中去处理商品数据，在order方法中
            $now_time=date('Y-m-d H:i:s',time());
            $order_true=$this->process($message,$order_info,$now_time,$compute);
            return $order_true;
        }

    }

    //订单处理
    public function process($parameter,$order_info,$now_time,$compute) {
        //查出所有的商品的数据出来,并且查出这个商品要给与多少的积分比例，优惠券的ID出来


        //$compute= new Compute;
        $order_list=[
            ['pay_order_sn','=',$parameter['out_trade_no']],
        ];

        //将付款的时间处理一下，20200421203911   要变成       2020-03-31 17:08:14
        $ahiui=[];
        for ($x=0; $x<=12; $x+=2) {
            $j=2;
            $ahiui[]=substr($parameter['time_end'],$x,$j);
        }
        $pay_time=$ahiui[0].$ahiui[1].'-'.$ahiui[2].'-'.$ahiui[3].' '.$ahiui[4].':'.$ahiui[5].':'.$ahiui[6];
        //上面是处理时间的


        /** 开始处理业务逻辑   */
        //第一步，修改支付订单的情况
        $data_info["pay_status"]                =2;
        $data_info["pay_way"]                   ="H5";
        $data_info["pay_time"]                  =$pay_time;
        $data_info["pay_mode"]                  ="WeChat";
        $data_info["pay_true"]                  =$parameter['total_fee'];
        $data_info["pay_msg_info"]              =json_encode($parameter);
        $data_info["mch_id"]                    =$parameter['mch_id'];
        $data_info["pay_message"]               =$parameter['transaction_id'];
        $data_info["update_time"]               =$now_time;
        $data_info['pay_mode']                  ='WeChat';//支付方式，ALIPAY，WeChat，yuePay
        $data_info['pay_way']                   ='H5';//微信H5，PC端PC，安卓ANDROID，ISO

        $where2['self_id']=$parameter['out_trade_no'];

        $order_true=Pay::where($where2)->update($data_info);           //这个地方处理支付订单的状态

        $where3['pay_order_sn']=$parameter['out_trade_no'];
        $shop_order_data["pay_status"]=2;
        $shop_order_data["update_time"]=$now_time;
        ShopOrder::where($where3)->update($shop_order_data);           //这个地方处理支付订单的状态

        //exit;
        //$number=DB::table('shop_order_list')->where($order_list)->value('number');           //这个地方处理支付订单的状态



        $where=[
            ['self_id','=',$order_info->total_user_id],
        ];


        $select=['self_id','tel','true_name','grade_id','father_user_id1','father_user_id2','father_user_id3','father_user_id4','father_user_id5','father_user_id6','father_user_id7','father_user_id8'];
        $selectCapital=['total_user_id','performance','performance_share','share','leiji5','leiji10','leiji20','leiji','money'];

        //把他的所有的上级抓出来看看
        $user=UserTotal::with(['userCapital' => function($query) use($selectCapital){
            $query->select($selectCapital);
        }])->where($where)->select($select)->first();


        if($order_true){
            return true;
        }
    }

}
?>

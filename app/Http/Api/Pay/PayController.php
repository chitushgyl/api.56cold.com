<?php
namespace App\Http\Api\Pay;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use EasyWeChat\Factory;
use EasyWeChat\Foundation\Application;
use App\Models\Pay\Pay;

//use App\Http\Controllers\ComputeController as Compute;
//use App\Http\Api\Pay\NotifyController as Notify;
// use Illuminate\Support\Facades\Schema;

class PayController extends Controller{
	/**
     * 微信支付回调      /pay/get_pay_info
     *前端传递非必须参数：
     *
     * 回调结果：200  数据拉取成功
     *
     *回调数据：
     */
	 public function get_pay_info(Request $request){

			$out_trade_no=$request->input('order_id');
			$order_where=[
                ['self_id','=',$out_trade_no],
            ];

         $data['hosturl']=Pay::where($order_where)->value('hosturl');

         $msg['code']=200;
         $msg['data']=(object)$data;
         //dd(1111);
         return $msg;

	 }


    /**
     * 微信支付回调      /pay/wx_pay
	 /pay/wx_pay22
     *前端传递非必须参数：
     *
     * 回调结果：200  数据拉取成功
     *
     *回调数据：
     */
    public function wx_pay(Request $request){
		$out_trade_no=$request->input('order_id');

		$out_trade_no='O202012131552118445922637';
		$order_where=[
                ['self_id','=',$out_trade_no],
            ];
		//通过订单号去拿去支付的配置信息，如果拿不到，或者中间有数据是空的，都去拿1234的配置文件和信息发起支付
        $select=['self_id','total_user_id','token_id','pay_status','show_group_name','pay_money','show_group_code'];
        $selectList=['father_group_code','wx_pay_info','group_code'];
        $order_info=Pay::with(['systemGroup' => function($query)use($selectList) {
            $query->select($selectList);
        }])->where($order_where)->select($select)->first();



//        dump($order_info->toArray());



		if($order_info){

			//print_r($order_info);

			if($order_info->pay_status == '1'){

                $pay_app_info=json_decode($order_info->systemGroup->wx_pay_info);

//DD($pay_app_info);
                $options = [
                    'app_id'        =>$pay_app_info->pay_app_id,
                    'mch_id' 		=> $pay_app_info->mch_id,
                    'key' 			=> $pay_app_info->key,
                    'notify_url' 	=> config('page.platform.notify_url'),
					//'notify_url' 	=> 'http://loveapi.zhaodaolo.com/notify/notify',
                ];


//                DD($options);
                //dump($options);
                //dump($pay_app_info);

				$payment = Factory::payment($options);
				//dump($payment);
				$jssdk = $payment->jssdk;


				$attributes=[
					'trade_type'        => 'JSAPI', 									// JSAPI，NATIVE，APP...  	wxdc3dff8cfd3db5cb
					'body'				=>$order_info->show_group_name,
					'out_trade_no'		=>$out_trade_no,
					'total_fee'			=>$order_info->pay_money,
					//'total_fee'			=>1,
					'openid'		    => $order_info->token_id,
					//'openid'=>'o_nbmwcD3ZhtpSYJMLhJKOnNH3GM',
				];

				$result = $payment->order->unify($attributes);
				//dd($result);

				if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
					$prepayId = $result['prepay_id'];
					$config = $jssdk->sdkConfig($prepayId);

					$config['timeStamp']=strval(time());
					//dd($config);
					$msg['code']=200;
					$msg['config']=$config;
					return $msg;
					//return response($config);
				}

				if ($result['return_code'] == 'FAIL' && array_key_exists('return_msg', $result)) {
					//dd($result['return_msg']);
					return $this->responseError(-1, $result['return_msg']);
				}


				//dd($result['err_code_des']);
				return $this->responseError(-1, $result['err_code_des']);



				//$order = new Order($attributes);

				//dd($order);
			}else{
				$msg['code']=302;
				$msg['msg']='订单状态不能发起支付';
			}
		}else{
			$msg['code']=301;
            $msg['msg']='没有这个订单';
		}



        return $msg;
    }



}
?>

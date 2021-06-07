<?php
namespace App\Http\Api\Tms;
use App\Models\Tms\TmsCarriage;
use App\Models\Tms\TmsCarriageDispatch;
use App\Models\Tms\TmsCarriageDriver;
use App\Models\Tms\TmsReceipt;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderDispatch;

class CarriageController extends Controller{

    /**
     * 承运商接单列表  /api/carriage/carriageOrderPage
     * */
    public function carriageOrderPage(Request $request){
        /** 接收中间件参数**/
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $order_status     = $request->post('status');//
        $buttonInfo    = $request->get('buttonInfo');
//        dd($user_info);
        $carriage_flag    =array_column(config('tms.carriage_flag'),'name','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        $tms_control_type = array_column(config('tms.tms_control_type'),'name','key');
        $tms_line_type    = array_column(config('tms.tms_order_type'),'name','key');
        $pay_status     =config('tms.tms_order_status');
        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
//        $user_info->company_id = 'company_202103190956305854596277';
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $where=[
            ['company_id','=',$user_info->company_id],
            ['delete_flag','=','Y']
        ];
        $select=['self_id','company_id','total_money','order_status','order_type'];
        $select1 = ['self_id','order_dispatch_id','carriage_id'];
        $select2 = ['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
            ,'send_qu_name','send_address','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name','line_gather_address','line_send_sheng_name','line_send_shi_name','receipt_flag',
            'line_send_qu_name','receiver_id','line_send_address','info','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','order_id','clod'];

        $data['items']=TmsCarriage::with(['tmsCarriageDispatch'=>function($query)use($select1,$select2){
            $query->select($select1);
            $query->with(['tmsOrderDispatch'=>function($query)use($select2){
                $query->select($select2);
            }]);
        }])
            ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
            ->select($select)
            ->where($where);

        if ($order_status){
            if ($order_status == 1){
                $data['items'] = $data['items']->where('order_status',1);
            }elseif($order_status == 2){
                $data['items'] = $data['items']->whereIn('order_status',[2,3]);
            }else{
                $data['items'] = $data['items']->where('order_status',4);
            }
        }
        $data['items'] = $data['items']
            ->get();

//        dd($data['items']->toArray());
        foreach ($data['items'] as $kay => $value) {
            $order = $value->tmsCarriageDispatch[0]->tmsOrderDispatch[0];
            $value->order_type_show=$tms_order_type[$order->order_type]??null;
            $value->total_money = number_format($value->total_money/100,2);
            $value->self_id_show = substr($value->self_id,15);

            $temperture = json_decode($order ->clod);
            foreach ($temperture as $kkkk => $vvvv){
                $temperture[$kkkk]    = $tms_control_type[$vvvv] ?? null;
            }
            $value ->clod = implode(',',$temperture);
            $value ->order_type_show       = $tms_line_type[$value->order_type] ?? null;
            if ($order->group_code == $order->receiver_id){
                $value ->gather_sheng_name       = $order->gather_sheng_name;
                $value ->gather_shi_name       = $order->gather_shi_name;
                $value ->gather_qu_name       = $order->gather_qu_name;
                $value ->gather_address       = $order->gather_address;
                $value ->send_sheng_name       = $order->send_sheng_name;
                $value ->send_shi_name       = $order->send_shi_name;
                $value ->send_qu_name       = $order->send_qu_name;
                $value ->send_address       = $order->send_address;
            }else{
                $value ->gather_sheng_name       = $order->line_gather_sheng_name;
                $value ->gather_shi_name       = $order->line_gather_shi_name;
                $value ->gather_qu_name       = $order->line_gather_qu_name;
                $value ->gather_address       = $order->line_gather_address;
                $value ->send_sheng_name       = $order->line_send_sheng_name;
                $value ->send_shi_name       = $order->line_send_shi_name;
                $value ->send_qu_name       = $order->line_send_qu_name;
                $value ->send_address       = $order->line_send_address;
            }
            $value->pay_status_color=$pay_status[$value->order_status-1]['pay_status_color']??null;
            $value->order_status_show=$pay_status[$value->order_status-1]['pay_status_text']??null;

            $button1 = [];
            $button2 = [];
            $button3 = [];

            foreach ($buttonInfo as $k => $v){
                if ($v->id == 130 ){
                    $button1[] = $v;
                }
                if ($v->id == 131){
                    $button2[] = $v;
                }
                if ($v->id == 141){
                    $button3[] = $v;
                }
//                dd($button3);
                if ($value->order_status == 2 || $value->order_status == 3){
                    $value->button  = $button1;
                }
                if ($value->order_status == 4){
                    $value->button  = $button2;
                }
//                if ($order->receipt_flag == 'Y'){
//                    $value->button  = $button3;
//                }
                if ($order->receipt_flag == 'Y'){
                    $value->button = [];
                }

            }

        }
//        dd($data['items']->toArray());
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }


    /**
     * 接单详情 /api/carriage/details
     * */
    public function details(Request $request){
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        $tms_control_type = array_column(config('tms.tms_control_type'),'name','key');
        $tms_line_type    = array_column(config('tms.tms_order_type'),'name','key');
        $pay_status     =config('tms.tms_order_status_type');
        $self_id    = $request->input('self_id');//运单self_id
//        $self_id = 'carriage_202103231357070162574276';
        $where=[
            ['self_id','=',$self_id]
        ];
        $select = ['self_id','company_id','total_money','order_status','order_type'];
        $select1 = ['self_id','order_dispatch_id','carriage_id'];
        $select2 = ['self_id','receipt_flag','remark','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name','line_gather_address','line_gather_name','line_gather_tel','line_send_sheng_name','line_send_shi_name'
            ,'line_send_qu_name','line_send_address','line_send_name','line_send_tel','info','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','order_id','clod','receiver_id','gather_time','send_time','pick_flag','send_flag'];
        $select3 = ['self_id','company_id','company_name','carriage_flag','total_money'];
        $select4 = ['carriage_id','car_number','contacts','tel','price','car_id'];
        $selectList = ['self_id','receipt','order_id','total_user_id','group_code','group_name'];
        $info=TmsCarriage::with(['tmsCarriageDispatch'=>function($query)use($select1,$select2,$select3,$select4,$selectList){
            $query->where('delete_flag','=','Y');
            $query->select($select1);
            $query->with(['tmsOrderDispatch'=>function($query)use($select2){
                $query->select($select2);
            }]);
            $query->with(['tmsReceipt'=>function($query)use($selectList){
                $query->where('delete_flag','=','Y');
                $query->select($selectList);
            }]);
        }])
            ->with(['tmsDriver'=>function($query)use($select4){
                $query->where('delete_flag','=','Y');
                $query->select($select4);
        }])
            ->select($select)
            ->where($where)
            ->first();
//        dump($info->toArray());
        if($info) {
                $order = $info->tmsCarriageDispatch[0]->tmsOrderDispatch[0];
                $info->order_type_show=$tms_order_type[$order->order_type]??null;
                $info->total_money = number_format($info->total_money/100,2);
                $info->self_id_show = substr($info->self_id,15);
                $list_cd  = $info->toArray();
                $car_list = [];
                if ($info->tmsDriver){
                    foreach ($info->tmsDriver as $kk => $vv){
                        $carList['car_number'] = $vv->car_number;
                        $carList['tel'] = $vv->tel;
                        $carList['contacts'] = $vv->contacts;
                        $car_list[] = $carList;
                    }
                    $info->car_info = $car_list;
                }
                $list_cd_dispatch = $list_cd['tms_carriage_dispatch'];
                $dispatch_arr = [];
                $receipt_info_list = [];
                foreach($list_cd_dispatch as $k => $v){
                    if ($v['tms_receipt']){
                        foreach ($v['tms_receipt'] as $kk => $vv){
                            $receipt_info = img_for($vv['receipt'],'more');
                            $receipt_info_list[] = $receipt_info;
                        }
                    }
                    $goodInfo = [];
                    foreach ($v['tms_order_dispatch'] as $key => $value) {
//                        dd($value);
                        $info->remark = $value['remark'];
                        $info->receipt_flag = $value['receipt_flag'];
                        $for_info = json_decode($value['info']);
                        $goodInfo = array_merge($goodInfo,json_decode($value['good_info'],true));
                        foreach ($goodInfo as $kkk =>$vvv){
                            $goodInfo[$kkk]['clod'] = $tms_control_type[$vvv['clod']];
                        }
                        if ($value['group_code'] == $value['receiver_id']) {//内部
                            $for_data = $for_info;
                        } else {//线上
                            if ($value['order_type'] == 'vehicle') {
                                $for_data = $for_info;
                            } else {

                                $clod_arr = json_decode($value['clod']);
                                $clod_name_arr = [];
                                foreach ($clod_arr as $name) {
                                    $clod_name_arr[] = $tms_control_type[$name];
                                }
                                $good_info = json_decode($value['good_info'],true);
                                $for_data[] = [
                                    'gather_sheng_name' => $value['line_gather_sheng_name'],
                                    'gather_shi_name'   => $value['line_gather_shi_name'],
                                    'gather_qu_name'    => $value['line_gather_qu_name'],
                                    'gather_address'    => $value['line_gather_address'],
                                    'gather_contacts_name' => $value['line_gather_name'],
                                    'gather_contacts_tel'  => $value['line_gather_tel'],
                                    'send_sheng_name'   => $value['line_send_sheng_name'],
                                    'send_shi_name'     => $value['line_send_shi_name'],
                                    'send_qu_name'      => $value['line_send_qu_name'],
                                    'send_address'      => $value['line_send_address'],
                                    'send_contacts_name'=> $value['line_send_name'],
                                    'send_contacts_tel' => $value['line_send_tel'],
                                    'good_number'       => $value['good_number'],
                                    'good_weight'       => $value['good_weight'],
                                    'good_volume'       => $value['good_volume'],
                                    'gather_time'       => $value['gather_time'],
                                    'send_time'         => $value['send_time'],
                                    'good_name'         => $good_info[0]['good_name'],
                                    'clod_name'         => implode($clod_name_arr)
                                ];
                            }
                        }
                        $dispatch_arr[] = [
                            'info' => $for_data,
                            'order_type' => $value['order_type'],
                            'gather_time' => $value['gather_time'],
                            'send_time' => $value['send_time'],
                            'pick_flag' => $value['pick_flag'],
                            'send_flag' => $value['send_flag']
                        ];
                    }
                    $info->good_info = $goodInfo;
                    $info->receipt = $receipt_info_list;
                }

            $info->arr = $dispatch_arr;
//            dd($info->toArray());
            $data['info'] = $info;
            $msg['code']  = 200;
            $msg['msg']   = "数据拉取成功";
            $msg['data']  = $data;
            return $msg;
        } else {
            $msg['code']  = 300;
            $msg['msg']   = "没有查询到数据";
            return $msg;
        }
    }

    /**
     * 承运商接单  /api/carriage/carriageTake
     * */
    public function carriageTake(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $carriage_id       = $request->input('carriage_id');
        $car_number       = $request->input('car_number');
        $contacts       = $request->input('contacts');
        $tel            = $request->input('tel');

        /*** 虚拟数据
        $input['carriage_id']           =$carriage_id='dispatch_202101282034423534882996';
        $input['car_number']                =$car_number='car_202101281717140184141326';
        $input['contacts']              =$contacts='李白';
        $input['tel']                   =$tel='13256454879';
         **/
        $rules = [
            'carriage_id'=>'required',
        ];
        $message = [
            'carriage_id.required'=>'请选择接取的订单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {

            $carriage_update['order_status'] = 3;
            $carriage_update['update_time'] = $now_time;
            TmsCarriage::where('self_id',$carriage_id)->update($carriage_update);

            $order_list['self_id']            =generate_id('driver_');
            $order_list['carriage_id']        = $carriage_id;
            $order_list['total_user_id']         = $user_info->total_user_id;
            $order_list['create_user_id']     = $user_info->total_user_id;
//            $order_list['create_user_name']   = $user_info->name;
            $order_list['create_time']        =$order_list['update_time']=$now_time;
            $order_list['car_number']   =  $car_number;
            $order_list['contacts']   =  $contacts;
            $order_list['tel']   = $tel;


            $id=TmsCarriageDriver::insert($order_list);

            if($id){
                $msg['code'] = 200;
                $msg['msg'] = "操作成功";
                return $msg;
            }else{
                $msg['code'] = 302;
                $msg['msg'] = "操作失败";
                return $msg;
            }
        }else{
                //前端用户验证没有通过
                $erro = $validator->errors()->all();
                $msg['code'] = 300;
                $msg['msg']  = null;
                foreach ($erro as $k => $v) {
                    $kk = $k+1;
                    $msg['msg'].=$kk.'：'.$v.'</br>';
                }
                return $msg;
            }
    }

    /**
     * 确认送达 /api/carriage/dispatch_done
     * */
    public function dispatch_done(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $dispatch_id       = $request->input('order_id');
//        /*** 虚拟数据
//        $input['order_id']           =$dispatch_id='carriage_202102040951034997882718';
//         **/
        $rules = [
            'order_id'=>'required',
        ];
        $message = [
            'order_id.required'=>'请选择调度订单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['self_id','=',$dispatch_id]
            ];
            $select = ['self_id','company_id','total_money','order_status','order_type'];
            $select1 = ['self_id','order_dispatch_id','carriage_id'];
            $select2 = ['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name','line_gather_address','line_gather_name','line_gather_tel','line_send_sheng_name','line_send_shi_name'
                ,'receiver_id','line_send_qu_name','line_send_address','line_send_name','line_send_tel','info','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','order_id','clod','receiver_id','gather_time','send_time','pick_flag','send_flag'];

            $info=TmsCarriage::with(['tmsCarriageDispatch'=>function($query)use($select1,$select2){
                $query->select($select1);
                $query->with(['tmsOrderDispatch'=>function($query)use($select2){
                    $query->select($select2);
                }]);
            }])
                ->select($select)
                ->where($where)
                ->first();
            if ($info->order_status != 3){
                $msg['code'] = 301;
                $msg['msg'] = "请先安排车辆";
                return $msg;
            }
//            dump($info->toArray());
            /*** 修改运输单状态**/
            $order_where = [
                ['self_id','=',$dispatch_id]
            ];
            $carriage_update['order_status'] = 4;
            $carriage_update['update_time']  = $now_time;
            $id = TmsCarriage::where($order_where)->update($carriage_update);
            //判断是否所有的运输单状态是否都为调度，是修改订单状态为已接单
            foreach($info->tmsCarriageDispatch as $key =>$value){
                $dispatch_where = [
                    ['self_id','=',$value->order_dispatch_id]
                ];
                $dispatch_order['order_status']  = 5;
                $dispatch_order['update_time']   = $now_time;
                $id = TmsOrderDispatch::where($dispatch_where)->update($dispatch_order);
                $tmsOrderDispatch = TmsOrderDispatch::where($dispatch_where)->select(['order_id'])->first();
                $tmsOrderDispatchList = TmsOrderDispatch::where('order_id',$tmsOrderDispatch->order_id)->select(['self_id'])->get();
                if ($tmsOrderDispatchList){
                    $dispatch_list = array_column($tmsOrderDispatchList->toArray(),'self_id');
                    $orderStatus = TmsOrderDispatch::where('self_id','!=',$dispatch_id)->whereIn('self_id',$dispatch_list)->select(['order_status'])->get();
                    $arr = array_unique(array_column($orderStatus->toArray(),'order_status'));

                    if (count($arr) >= 1){
                        if (implode('',$arr) == 5){
                            $order_update['order_status'] = 5;
                            $order_update['update_time']  = $now_time;
                            $order = TmsOrder::where('self_id',$tmsOrderDispatch->order_id)->update($order_update);
                        }
                    }else{
                        $order_update['order_status'] = 5;
                        $order_update['update_time']  = $now_time;
                        $order = TmsOrder::where('self_id',$tmsOrderDispatch->order_id)->update($order_update);
                    }
                }

            }

            if($id){
                $msg['code'] = 200;
                $msg['msg'] = "操作成功";
                return $msg;
            }else{
                $msg['code'] = 302;
                $msg['msg'] = "操作失败";
                return $msg;
            }
        }else{
            //前端用户验证没有通过
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg']  = null;
            foreach ($erro as $k => $v) {
                $kk = $k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }

    /**
     * 上传回单 /api/carriage/up_receipt
     * */
    public function up_receipt(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $order_id       = $request->input('order_id');
        $receipt       = $request->input('receipt');

        /*** 虚拟数据
        $input['order_id']           =$order_id='driver_202103191037157239619646';
         **/
        $rules = [
            'order_id'=>'required',
            'receipt'=>'required',
        ];
        $message = [
            'order_id.required'=>'请选择调度订单',
            'receipt.required'=>'请选择要上传的回单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['self_id','=',$order_id],
            ];

            $select = ['self_id','company_id','total_money','order_status','order_type'];
            $select1 = ['self_id','order_dispatch_id','carriage_id'];
            $select2 = ['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name','line_gather_address','line_gather_name','line_gather_tel','line_send_sheng_name','line_send_shi_name'
                ,'receiver_id','line_send_qu_name','line_send_address','line_send_name','line_send_tel','info','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','order_id','clod','receiver_id','gather_time','send_time','pick_flag','send_flag'];

            $info=TmsCarriage::with(['tmsCarriageDispatch'=>function($query)use($select1,$select2){
                $query->select($select1);
                $query->with(['tmsOrderDispatch'=>function($query)use($select2){
                    $query->select($select2);
                }]);
            }])
                ->select($select)
                ->where($where)
                ->first();
            $wait_info = $info->tmsCarriageDispatch[0]->tmsOrderDispatch[0];
            foreach ($info->tmsCarriageDispatch as $key => $value){
                $dispatch_update['receipt_flag'] = 'Y';
                $dispatch_update['update_time']  = $now_time;
                TmsOrderDispatch::where('self_id','=',$value->order_dispatch_id)->update($dispatch_update);

            }

            if(!in_array($wait_info->order_status,[5,6])){
                $msg['code']=301;
                $msg['msg']='请确认订单已送达';
                return $msg;
            }
            $data['self_id'] = generate_id('receipt_');
            $data['receipt'] = img_for($receipt,'in');
            $data['order_id'] = $wait_info->self_id;
            $data['create_time'] = $data['update_time'] = $now_time;
            $data['total_user_id']  = $user_info->total_user_id;

            $id=TmsReceipt::insert($data);

            if($id){
                $msg['code'] = 200;
                $msg['msg'] = "上传成功";
                return $msg;
            }else{
                $msg['code'] = 302;
                $msg['msg'] = "上传失败";
                return $msg;
            }
        }else{
            //前端用户验证没有通过
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg']  = null;
            foreach ($erro as $k => $v) {
                $kk = $k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }



}
?>

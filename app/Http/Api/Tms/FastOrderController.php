<?php
namespace App\Http\Api\Tms;
use App\Http\Controllers\TmsController as Tms;
use App\Models\Group\SystemGroup;
use App\Models\Tms\AppSettingParam;
use App\Models\Tms\TmsLittleOrder;
use App\Models\Tms\TmsParam;
use App\Models\Tms\TmsTypeCar;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;
use App\Models\Tms\TmsCar;

use App\Models\Tms\TmsCarType;


class FastOrderController extends Controller{
    /**
     * 快捷下单
     * */
    public function addFastOrder(Request $request,TMS $tms){
        $project_type       =$request->get('project_type');
        $now_time   = date('Y-m-d H:i:s',time());
        $table_name = 'tms_order';
        $user_info  = $request->get('user_info');//接收中间件产生的参数
//         $project_type = 'user';
        $total_user_id  = $user_info->total_user_id;
//        $token_name     = $user_info->token_name;
        $input      =$request->all();

        /** 接收数据*/
        $self_id       = $request->input('self_id');
        $order_type    = $request->input('order_type');//订单类型 vehicle  lcl   line
        $price         = $request->input('price');//运输费
        $total_money   = $request->input('total_money');//总计费用
        $good_name_n   = $request->input('good_name');
        $good_number_n = $request->input('good_number');
        $good_weight_n = $request->input('good_weight');
        $good_volume_n = $request->input('good_volume');
        $dispatcher    = $request->input('dispatcher') ?? [];
        $clod          = $request->input('clod');
        $gather_time   = $request->input('gather_time')??null;
        $send_time     = $request->input('send_time')??null;
        $pay_type      = $request->input('pay_type');
        $remark        = $request->input('remark')??''; //备注
        $kilo         = $request->input('kilometre');//付款方：发货人 consignor  收货人receiver

        $rules = [
            'order_type'=>'required',
        ];
        $message = [
            'order_type.required'=>'必须选择',
        ];

        switch ($project_type){
            case 'user':
                $group_code     = null;
                $group_name     =null;
                $receiver_id    = null;
                break;
            case 'company':
                $group_code     = $user_info->group_code;
                $group_name     =$user_info->group_name;
                $total_user_id = null;
                $receiver_id = null;
                break;
            default:
                $group_code     = null;
                $group_name     =null;
                $receiver_id = null;
                $total_user_id = null;
                break;
        }
        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            if($project_type == 'company'){
                $where_group=[
                    ['delete_flag','=','Y'],
                    ['self_id','=',$group_code],
                ];
                $group_info    =SystemGroup::where($where_group)->select('group_code','group_name')->first();
            }
            /***开始做二次效验**/

            if ($order_type == 'line'){
                if (empty($good_weight_n) || $good_weight_n <= 0) {
                    $msg['code'] = 308;
                    $msg['msg'] = '货物重量错误！';
                    return $msg;
                }
                if (empty($good_name_n)) {
                    $msg['code'] = 306;
                    $msg['msg'] = '货物名称不能为空！';
                    return $msg;
                }
            }

            if (empty($clod)) {
                $msg['code'] = 309;
                $msg['msg'] = '请选择温度！';
                return $msg;
            }


            $send_t = '提货';
            $pick_t = '配送';

            /** 处理一下发货地址  及联系人**/
            foreach ($dispatcher as $k => $v){
                if ($project_type == 'company'){
                    $gather_address = $tms->address_contact($v['gather_address_id'],$v['gather_qu'],$v['gather_address'],$v['gather_name'],$v['gather_tel'],$group_info,$user_info,$now_time);
                }else{
                    $gather_address = $tms->address_contact($v['gather_address_id'],$v['gather_qu'],$v['gather_address'],$v['gather_name'],$v['gather_tel'],'',$user_info,$now_time);
                }

                if(empty($gather_address)){
                    $msg['code'] = 303;
                    $msg['msg'] = $pick_t.'地址不存在';
                    return $msg;
                }

                if ($project_type == 'company'){
                    $send_address=$tms->address_contact($v['send_address_id'],$v['send_qu'],$v['send_address'],$v['send_name'],$v['send_tel'],$group_info,$user_info,$now_time);
                }else{
                    $send_address=$tms->address_contact($v['send_address_id'],$v['send_qu'],$v['send_address'],$v['send_name'],$v['send_tel'],'',$user_info,$now_time);
                }
                if(empty($send_address)){
                    $msg['code'] = 303;
                    $msg['msg'] = $send_t.'地址不存在';
                    return $msg;
                }
                if ($order_type == 'line'){
                    if (empty($v['good_name'])) {
                        $msg['code'] = 306;
                        $msg['msg']  = '货物名称不能为空！';
                        return $msg;
                    }

                    if (empty($v['good_weight']) || $v['good_weight'] <= 0) {
                        $msg['code'] = 308;
                        $msg['msg']  = '货物重量错误！';
                        return $msg;
                    }
                    if (empty($v['clod'])) {
                        $msg['code'] = 309;
                        $msg['msg']  = '请选择温度！';
                        return $msg;
                    }
                }


                $dispatcher[$k]['send_address_id']        = $send_address->self_id;
                $dispatcher[$k]['send_sheng']             = $send_address->sheng;
                $dispatcher[$k]['send_sheng_name']        = $send_address->sheng_name;
                $dispatcher[$k]['send_shi']               = $send_address->shi;
                $dispatcher[$k]['send_shi_name']          = $send_address->shi_name;
                $dispatcher[$k]['send_qu']                = $send_address->qu;
                $dispatcher[$k]['send_qu_name']           = $send_address->qu_name;
                $dispatcher[$k]['send_address']           = $send_address->address;
                $dispatcher[$k]['send_address_longitude'] = $send_address->longitude;
                $dispatcher[$k]['send_address_latitude']  = $send_address->dimensionality;
//                $dispatcher[$k]['send_contacts_id']       = $send_contacts->self_id;
                $dispatcher[$k]['send_contacts_name']     = $send_address->contacts;
                $dispatcher[$k]['send_contacts_tel']      = $send_address->tel;

                $dispatcher[$k]['gather_address_id']        = $gather_address->self_id;
                $dispatcher[$k]['gather_sheng']             = $gather_address->sheng;
                $dispatcher[$k]['gather_sheng_name']        = $gather_address->sheng_name;
                $dispatcher[$k]['gather_shi']               = $gather_address->shi;
                $dispatcher[$k]['gather_shi_name']          = $gather_address->shi_name;
                $dispatcher[$k]['gather_qu']                = $gather_address->qu;
                $dispatcher[$k]['gather_qu_name']           = $gather_address->qu_name;
                $dispatcher[$k]['gather_address']           = $gather_address->address;
                $dispatcher[$k]['gather_address_longitude'] = $gather_address->longitude;
                $dispatcher[$k]['gather_address_latitude']  = $gather_address->dimensionality;
//                $dispatcher[$k]['gather_contacts_id']       = $gather_contacts->self_id;
                $dispatcher[$k]['gather_contacts_name']     = $gather_address->contacts;
                $dispatcher[$k]['gather_contacts_tel']      = $gather_address->tel;
            }

            $order_id = generate_id('order_');
            $wheres['self_id'] = $self_id;
            $old_info=TmsLittleOrder::where($wheres)->first();



            $data['order_type']                 = $order_type;
            $data['gather_time']                = $gather_time;
            $data['gather_address_id']          = $dispatcher[0]['gather_address_id'];

            $data['gather_name']                = $dispatcher[0]['gather_contacts_name'];
            $data['gather_tel']                 = $dispatcher[0]['gather_contacts_tel'];
            $data['gather_sheng']               = $dispatcher[0]['gather_sheng'];
            $data['gather_sheng_name']          = $dispatcher[0]['gather_sheng_name'];
            $data['gather_shi']                 = $dispatcher[0]['gather_shi'];
            $data['gather_shi_name']            = $dispatcher[0]['gather_shi_name'];
            $data['gather_qu']                  = $dispatcher[0]['gather_qu'];
            $data['gather_qu_name']             = $dispatcher[0]['gather_qu_name'];
            $data['gather_address']             = $dispatcher[0]['gather_address'];
            $data['gather_address_longitude']   = $dispatcher[0]['gather_address_longitude'];
            $data['gather_address_latitude']    = $dispatcher[0]['gather_address_latitude'];
            $data['send_time']                  = $send_time;
            $data['send_address_id']            = $dispatcher[0]['send_address_id'];

            $data['send_name']                  = $dispatcher[0]['send_contacts_name'];
            $data['send_tel']                   = $dispatcher[0]['send_contacts_tel'];
            $data['send_sheng']                 = $dispatcher[0]['send_sheng'];
            $data['send_sheng_name']            = $dispatcher[0]['send_sheng_name'];
            $data['send_shi']                   = $dispatcher[0]['send_shi'];
            $data['send_shi_name']              = $dispatcher[0]['send_shi_name'];
            $data['send_qu']                    = $dispatcher[0]['send_qu'];
            $data['send_qu_name']               = $dispatcher[0]['send_qu_name'];
            $data['send_address']               = $dispatcher[0]['send_address'];
            $data['send_address_longitude']     = $dispatcher[0]['send_address_longitude'];
            $data['send_address_latitude']      = $dispatcher[0]['send_address_latitude'];
            $data['good_number']                = $good_number_n;
            $data['good_weight']                = $good_weight_n;
            $data['good_volume']                = $good_volume_n;
            $data['total_money']                = ($total_money - 0) * 100;
            $data['info']                       = json_encode($dispatcher,JSON_UNESCAPED_UNICODE);
            $data['good_info']                  = $good_name_n;
            $data['clod']                       = $clod;
            $data['price']                      = ($price - 0)*100;
            $data['pay_type']                   = $pay_type;
            $data['remark']                     = $remark;
            $data['kilometre']                  = $kilo;


            if($old_info){
                $orderid = $self_id;
                $data['update_time'] = $now_time;
                $id = TmsLittleOrder::where($wheres)->update($data);
                // $operationing->access_cause='修改订单';
                // $operationing->operation_type='update';
            }else{
                $data['self_id']          = $order_id;
                $data['group_code']       = $group_code;
                $data['group_name']       = $group_name;
                $data['total_user_id']   = $total_user_id;
                $data['create_time']      = $data['update_time'] = $now_time;
                $orderid = $data['self_id'];
                if ($pay_type == 'offline'){
                    $data['order_status'] = 2;
                    $data['on_line_flag'] = 'Y';
                }

                DB::beginTransaction();
                try{
                    $id = TmsLittleOrder::insert($data);
                    DB::commit();
//                    if ($data['pay_type'] == 'offline'){
//                        $center_list = '有从'. $data['send_shi_name'].'发往'.$data['gather_shi_name'].'的整车订单';
//                        $push_contnect = array('title' => "赤途承运端",'content' => $center_list , 'payload' => "订单信息");
//                        $this->sendPushMessage('订单信息','有新订单',$center_list);
//                    }
                }catch(\Exception $e){
                    DB::rollBack();
                    $msg['code'] = 302;
                    $msg['msg']  = "操作失败";
                    return $msg;
                }
            }

            if($id){
                $msg['code'] = 200;
                $msg['msg']  = "操作成功";
                $msg['order_id'] = $orderid;
                $msg['order_id_show'] = substr($orderid,15);
                return $msg;
            }else{
                $msg['code'] = 302;
                $msg['msg']  = "操作失败";
                return $msg;
            }
        }
    }


    public function fastOrderPage(Request $request){
        $pay_status     =config('tms.tms_order_status_type');
        $tms_order_type        = array_column(config('tms.tms_order_type'),'name','key');
        $project_type       =$request->get('project_type');
        /** 接收中间件参数**/
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $order_status     = $request->post('status');//接收中间件产生的参数
        $button_info =     $request->get('buttonInfo');
        $tms_control_type        =array_column(config('tms.tms_control_type'),'name','key');
        $order_state_type        =config('tms.order_state_type');
        $total_user_id = $user_info->total_user_id;
        /**接收数据*/
        $num           = $request->input('num')??10;
        $page          = $request->input('page')??1;
        $order_type    = $request->input('order_type') ?? '';//vehicle  lcl   line
        $listrows      = $num;
        $firstrow      = ($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
        ];

        switch ($project_type){
            case 'user':
                $search[] =['type'=>'=','name'=>'delete_flag','value'=>'Y'];
                $search[] =    ['type'=>'=','name'=>'total_user_id','value'=>$user_info->total_user_id];
                break;
            case 'customer':
                $search[] =  ['type'=>'=','name'=>'delete_flag','value'=>'Y'];
                $search[] =    ['type'=>'=','name'=>'company_id','value'=>$user_info->userIdentity->company_id];
                break;
            case 'company':
                $search[] =  ['type'=>'=','name'=>'delete_flag','value'=>'Y'];
                $search[] =    ['type'=>'=','name'=>'group_code','value'=>$user_info->group_code];
                break;
        }
        if ($order_type) {
            $search[] = ['type'=>'=','name'=>'order_type','value'=>$order_type];
        }
        $where  = get_list_where($search);
        $select = ['self_id','group_name','create_user_name','create_time','use_flag','order_type','order_status','clod','good_info',
            'gather_address_id','gather_contacts_id','gather_name','gather_tel','gather_sheng','gather_shi','gather_qu','gather_qu_name','gather_address',
            'send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_shi','send_qu','send_qu_name','send_address','total_money','pay_type',
            'good_name','good_number','good_weight','good_volume','gather_shi_name','send_shi_name','gather_time','send_time'];
        $data['info'] = TmsLittleOrder::where($where);

        switch ($project_type){
            case 'user':
                if ($order_status){
                    if ($order_status == 1){
                        $data['info'] = $data['info']->where('order_status',1);
                    }elseif($order_status == 2){
                        $data['info'] = $data['info']->where('order_status',2);
                    }elseif($order_status == 3){
                        $data['info'] = $data['info']->whereIn('order_status',[3,4,5]);
                    }elseif($order_status == 4){
                        $data['info'] = $data['info']->where('order_status',6);
                    }else{
                        $data['info'] = $data['info']->where('order_status',7);
                    }
                }
                break;
            case 'customer':
                if ($order_status){
                    if ($order_status == 2){
                        $data['info'] = $data['info']->where('order_status',3);
                    }elseif($order_status == 3){
                        $data['info'] = $data['info']->whereIn('order_status',[4,5]);
                    }elseif($order_status == 4){
                        $data['info'] = $data['info']->where('order_status',6);
                    }else{
                        $data['info'] = $data['info']->where('order_status',7);
                    }
                }
                break;
            case 'company':
                if ($order_status){
                    if ($order_status == 1){
                        $data['info'] = $data['info']->where('order_status',1);
                    }elseif($order_status == 2){
                        $data['info'] = $data['info']->where('order_status',2);
                    }elseif($order_status == 3){
                        $data['info'] = $data['info']->whereIn('order_status',[3,4,5]);
                    }elseif($order_status == 4){
                        $data['info'] = $data['info']->where('order_status',6);
                    }else{
                        $data['info'] = $data['info']->where('order_status',7);
                    }
                }
                break;
            case 'business':

                break;
        }

        $data['info'] = $data['info']->offset($firstrow)
            ->limit($listrows)
            ->orderBy('update_time', 'desc')
            ->select($select)
            ->get();

        foreach ($data['info'] as $k=>$v) {
            $v->total_money       = number_format($v->total_money/100, 2);
            $v->good_weight       = floor($v->good_weight);
            $v->good_volume       = floor($v->good_volume);
            $v->good_name         = implode(',',json_decode($v->good_info,true));
            $v->pay_status_color=$pay_status[$v->order_status-1]['pay_status_color']??null;
            $v->order_status_show=$pay_status[$v->order_status-1]['pay_status_text']??null;
            $v->order_type_show   = $tms_order_type[$v->order_type] ?? null;
            $v->self_id_show = substr($v->self_id,15);
            $v->clod=json_decode($v->clod,true);
            $v->send_time = date('m-d H:i',strtotime($v->send_time));
            $info_clod = $v->clod;
            foreach ($info_clod as $key => $value){
                $info_clod[$key]=$tms_control_type[$value];
            }
            $v->clod = $info_clod;
            $temperture = $v->clod;
            foreach ($temperture as $key => $value){
                $temperture[$key] = $value;
            }
            $v->temperture = implode(',',$temperture);

            $v->picktime_show = '装车时间 '.$v->send_time;
            $v->temperture_show ='温度 '.$v->clod[0];
            $v->order_id_show = '订单编号'.substr($v->self_id,15);
            if ($v->order_status == 1){
                $v->state_font_color = '#333';
            }elseif($v->order_status == 2){
                $v->state_font_color = '#333';
            }elseif($v->order_status == 3){
                $v->state_font_color = '#0088F4';
            }elseif($v->order_status == 4){
                $v->state_font_color = '#35B85F';
            }elseif($v->order_status == 5){
                $v->state_font_color = '#35B85F';
            }elseif($v->order_status == 6){
                $v->state_font_color = '#FF9400';
            }else{
                $v->state_font_color = '#FF807D';
            }
            if($v->order_type == 'vehicle' || $v->order_type == 'lcl' || $v->order_type == 'lift'){
                $v->order_type_color = '#E4F3FF';
                $v->order_type_font_color = '#0088F4';
                if ($v->order_type == 'vehicle'){
                    $v->order_type_color = '#0088F4';
                    $v->order_type_font_color = '#FFFFFF';
                }

            }else{
                $v->good_info_show = '货物 '.$v->good_number.'件'.$v->good_weight.'kg'.$v->good_volume.'方';
                $v->order_type_color = '#E4F3FF';
                $v->order_type_font_color = '#0088F4';
            }

            $button1 = [];
            $button2 = [];
            $button3 = [];
            $button4 = [];
            $button5 = [];
            $button6 = [];
            switch ($project_type){
                case 'user':
                    foreach ($button_info as $key => $value){
                        if ($value->id == 118 ){
                            $button1[] = $value;
                        }
                        if ($value->id == 182){
                            $button3[] = $value;
                            $button4[] = $value;
                        }
                        if ($value->id == 119){
                            $button2[] = $value;
                            $button3[] = $value;
                        }
                        if ($value->id == 234){
                            $button5[] = $value;
                        }
                        if ($value->id == 237){
                            $button6[] = $value;
                        }
                        if ($v->order_status == 2){
                            $v->button  = $button1;
                        }
                        if ($v->order_status == 5){
                            $v->button  = $button2;
                        }
                        if ($v->order_status  == 5 && $v->pay_type == 'offline' && $v->pay_state == 'N'){
                            $v->button  = $button3;
                        }
                        if($v->order_status == 6 && $v->discuss_flag == 'N'){
                            $v->button = $button5;
                        }
                        if($v->order_status == 6 && $v->discuss_flag == 'Y' && $v->follow_flag == 'N'){
                            $v->button = $button6;
                        }
                        if ($v->order_status  == 6 && $v->pay_type == 'offline' && $v->pay_state == 'N'){
                            $v->button  = $button4;
                        }
                    }
                    break;
                case 'company':
                    foreach ($button_info as $key => $value){
                        if ($value->id == 161){
                            $button1[] = $value;
                        }
                        if ($value->id == 183){
                            $button3[] = $value;
                            $button4[] = $value;
                        }
                        if ($value->id == 162){
                            $button2[] = $value;
                            $button3[] = $value;
                        }
                        if ($value->id == 235){
                            $button5[] = $value;
                        }
                        if ($value->id == 238){
                            $button6[] = $value;
                        }
                        if ($v->order_status == 2){
                            $v->button  = $button1;
                        }
                        if ($v->order_status == 3){
                            $v->button  = $button1;
                        }
                        if ($v->order_status == 5){
                            $v->button  = $button2;
                        }
                        if ($v->order_status  == 5 && $v->pay_type == 'offline' && $v->pay_state == 'N'){
                            $v->button  = $button3;
                        }
                        if($v->order_status == 6 && $v->discuss_flag == 'N'){
                            $v->button = $button5;
                        }
                        if($v->order_status == 6 && $v->discuss_flag == 'Y' && $v->follow_flag == 'N'){
                            $v->button = $button6;
                        }
                        if ($v->order_status  == 6 && $v->pay_type == 'offline' && $v->pay_state == 'N'){
                            $v->button  = $button4;
                        }
                    }
                    break;
            }

        }
        $data['list'] = $order_state_type;
//        dd($data['info']->toArray());
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }

    public function orderDetails(Request $request){
        $tms_money_type    = array_column(config('tms.tms_money_type'),'name','key');
        $tms_order_status_type = array_column(config('tms.tms_order_status_type'),'pay_status_text','key');
        $tms_order_type        = array_column(config('tms.tms_order_type'),'name','key');
        $tms_pay_type    = array_column(config('tms.pay_type'),'name','key');
        $tms_control_type        =array_column(config('tms.tms_control_type'),'name','key');
        $self_id = $request->input('self_id');
//         $self_id = 'order_202206080955520179364806';
        $table_name = 'tms_little_order';
        $select = ['self_id','group_code','group_name','create_user_name','create_time','use_flag','order_type','order_status','gather_address_id',
            'gather_contacts_id','gather_name','gather_tel','gather_sheng','gather_shi','gather_qu','gather_time','send_time',
            'gather_address','send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_shi','send_qu','send_address',
            'remark','total_money','price','good_name','good_number','good_weight','good_volume','info','good_info','clod','pay_type'];
        $select1 = ['self_id','order_id','receipt','group_code','group_name','total_user_id'];
        $select2 = ['self_id','order_id','carriage_id'];
        $select3 = ['self_id'];
        $select4 = ['self_id','carriage_id','use_flag','delete_flag','group_code','group_name','car_id','car_number','contacts','tel','price'];

        $where = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];

        $info = TmsLittleOrder::with(['tmsFastDispatch'=>function($query)use($select1,$select2,$select3,$select4){
            $query->select($select2);
            $query->with(['tmsFastCarriage'=>function($query)use($select3,$select4){
                $query->select($select3);
            }]);
            $query->with(['tmsFastCarriageDriver'=>function($query)use($select4){
                $query->select($select4);
            }]);
        }])
            ->with(['TmsReceipt' => function($query) use($select1){
                $query->select($select1);
            }])->where($where)->select($select)->first();
//        dd($info->toArray());
        if($info) {
            $info->order_status_show = $tms_order_status_type[$info->order_status] ?? null;
            $info->order_type_show   = $tms_order_type[$info->order_type] ??null;
            $info->pay_status = $tms_pay_type[$info->pay_type];
            $receipt_info = [];
            $receipt_info_list= [];
            if ($info->tmsReceipt){
                $receipt_info = img_for($info->tmsReceipt->receipt,'more');
                $receipt_info_list[] = $receipt_info;

            }
            $info->receipt = $receipt_info_list;
            $order_info              = json_decode($info->info,true);
            $send_info = [];
            $gather_info = [];

            $info->info = $order_info;

            $info->send_info = $send_info;
            $info->gather_info = $gather_info;
            $info->self_id_show = substr($info->self_id,15);
            $info->good_info         = json_decode($info->good_info,true);
            $info->clod              = json_decode($info->clod,true);
            /** 如果需要对数据进行处理，请自行在下面对 $info 进行处理工作*/
            $info->total_money = number_format($info->total_money/100, 2);
            $info->price       = number_format($info->price/100, 2);
            if ($info->order_type == 'vehicle' || $info->order_type == 'lift'){
                $info->good_weight = ($info->good_weight/1000).'吨';
            }
            $info->color = '#FF7A1A';
            $info->order_id_show = '订单编号'.$info->self_id_show;
            $order_details = [];
            $receipt_list = [];

            $car_list = [];
//            dd($info->tmsFastDispatch->toArray());
            if($info->tmsFastDispatch->tmsFastCarriageDriver){
                foreach ($info->tmsFastDispatch->tmsFastCarriageDriver as $kk => $vv) {
                    $carList['car_id'] = $vv->car_id;
                    $carList['car_number'] = $vv->car_number;
                    $carList['tel'] = $vv->tel;
                    $carList['contacts'] = $vv->contacts;
                    $car_list[] = $carList;
                }
                $info->car_info = $car_list;
            }


            $order_details1['name'] = '订单金额';
            $order_details1['value'] = '¥'.$info->total_money;
            $order_details1['color'] = '#FF7A1A';


            $order_details4['name'] = '收货时间';
            $order_details4['value'] = $info->gather_time;
            $order_details4['color'] = '#000000';
            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl' || $info->order_type == 'lift'){
                $order_details3['name'] = '装车时间';
                $order_details3['value'] = $info->send_time;
                $order_details3['color'] = '#000000';
            }else{
                $order_details3['name'] = '提货时间';
                $order_details3['value'] = $info->send_time;
                $order_details3['color'] = '#000000';
            }
            $order_details6['name'] = '订单备注';
            $order_details6['value'] = $info->remark;
            $order_details6['color'] = '#000000';

//            $order_details8['name'] = '时效';
//            $order_details8['value'] = $info->trunking;
//            $order_details8['color'] = '#000000';

            $order_details9['name'] = '运输信息';
            $order_details9['value'] = $info->car_info;

            $order_details10['name'] = '回单信息';
            $order_details10['value'] = $info->receipt;

            $order_details[] = $order_details1;
            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl' || $info->order_type == 'lift'){
                $order_details[] = $order_details3;
                $order_details[]= $order_details4;
                $order_details[]= $order_details6;
            }else{
                $order_details[]= $order_details3;
                $order_details[]= $order_details4;
                $order_details[]= $order_details6;
            }
            if(!empty($info->car_info)){
                $car_info[] = $order_details9;
            }
            if (!empty($info->receipt)){
                $receipt_list[] = $order_details10;
            }

//            dd($info->toArray());
            $data['info'] = $info;
            $data['order_details'] = $order_details;
            $data['receipt_list'] = $receipt_list;
            $data['car_info'] = $car_info;
            $msg['code'] = 200;
            $msg['msg']  = "数据拉取成功";
            $msg['data'] = $data;
            return $msg;
        } else {
            $msg['code'] = 300;
            $msg['msg']  = "没有查询到数据";
            return $msg;
        }
    }

    /**
     * 快捷订单取消下单
     * */
    public function fastOrderCancel(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $order_id       = $request->input('order_id');

        /*** 虚拟数据
        $input['order_id']           =$order_id='order_202103061730081629411962';
         **/
        $rules = [
            'order_id'=>'required',
        ];
        $message = [
            'order_id.required'=>'请取消的订单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$order_id],
            ];
            $select=['self_id','order_status','pay_type','total_money'];
            $wait_info=TmsLittleOrder::where($where)->select($select)->first();
            if ($wait_info->order_status == 3){
                $msg['code'] = 305;
                $msg['msg'] = '该订单已被承接，取消请联系客服';
                return $msg;
            }

            if ($wait_info->order_status == 4 || $wait_info->order_status == 5){
                $msg['code'] = 305;
                $msg['msg'] = '此订单运输中不可以取消';
                return $msg;
            }
            if ($wait_info->order_status == 6 ){
                $msg['code'] = 305;
                $msg['msg'] = '此订单已完成不可以取消';
                return $msg;
            }
            if ($wait_info->order_status == 7 ){
                $msg['code'] = 305;
                $msg['msg'] = '此订单已取消';
                return $msg;
            }
            DB::beginTransaction();
            try {
                /***判断订单时线上支付还是货到付款，线上支付要退款 **/
                if($wait_info->pay_type == 'online'){
                    $wallet = UserCapital::where('total_user_id',$user_info->total_user_id)->select(['self_id','money'])->first();
                    $wallet_update['money'] = $wait_info->total_money + $wallet->money;
                    $wallet_update['update_time'] = $now_time;
                    UserCapital::where('total_user_id',$user_info->total_user_id)->update($wallet_update);
                    $data['self_id'] = generate_id('wallet_');
                    $data['produce_type'] = 'refund';
                    $data['capital_type'] = 'wallet';
                    $data['money'] = $wait_info->total_money;
                    $data['create_time'] = $now_time;
                    $data['update_time'] = $now_time;
                    $data['now_money'] = $wallet_update['money'];
                    $data['now_money_md'] = get_md5($wallet_update['money']);
                    $data['wallet_status'] = 'SU';
                    $data['wallet_type'] = 'user';
                    $data['total_user_id'] = $user_info->total_user_id;
                    UserWallet::insert($data);
                }
                /** 修改订单状态**/
                $update['order_status']      = 7;
                $update['update_time']        =$now_time;
                $id = TmsLittleOrder::where($where)->update($update);

                /** 取消订单应该删除应付费用**/
//                $money_where = [
//                    ['order_id','=',$order_id],
//                ];
//                $money_update['delete_flag'] = 'N';
//                $money_update['update_time'] = $now_time;
//                $money_list = TmsOrderCost::where($money_where)->update($money_update);

                //如果订单已被承接，通知司机订单已取消
                DB::commit();
                $msg['code'] = 200;
                $msg['msg'] = "操作成功";
                return $msg;
            }catch(\Exception $e){
                DB::rollBack();
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
     * 快捷订单确认完成
     * */
    public function fastOrderDone(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $input         = $request->all();
        $now_time    = date('Y-m-d H:i:s',time());
        $self_id     = $request->input('self_id');
        $rules = [
            'self_id'=>'required',
        ];
        $message = [
            'self_id.required'=>'请选择订单',
        ];
        /**虚拟数据
        $input['self_id']       = $self_id       = 'order_202104101356286664799683';
         **/

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where = [
                ['self_id','=',$self_id],
                ['order_status','!=',7]
            ];
            $select = ['self_id','order_status','total_money','pay_type'];
            $order = TmsLittleOrder::where($where)->select($select)->first();
            if ($order->order_status == 6){
                $msg['code'] = 301;
                $msg['msg'] = '订单已完成';
                return $msg;
            }
            $update['update_time'] = $now_time;
            $update['order_status'] = 6;
            $id = TmsLittleOrder::where($where)->update($update);

            /** 查找所有的运输单 修改运输状态**/
            /*** 订单完成后，如果订单是在线支付，添加运费到承接司机或3pl公司余额 **/

            $idit = substr($order->receiver_id,0,5);
            if ($idit == 'user_'){
                $wallet_where = [
                    ['total_user_id','=',$order->receiver_id]
                ];
                $data['wallet_type'] = 'user';
                $data['total_user_id'] = $order->receiver_id;
            }else{
                $wallet_where = [
                    ['group_code','=',$order->receiver_id]
                ];
                $data['wallet_type'] = '3PLTMS';
                $data['group_code'] = $order->receiver_id;
            }
            $wallet = UserCapital::where($wallet_where)->select(['self_id','money'])->first();

            $money['money'] = $wallet->money + $order->total_money;
            $data['money'] = $order->total_money;
            if ($order->group_code == $order->receiver_id){
                $money['money'] = $wallet->money + $order->total_money;
                $data['money'] = $order->total_money;
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
     * 极速版预估价格
     * */
    public function predictPrice(Request $request){
        $input         = $request->all();
        $gather_address= $request->input('gather_address');
        $send_address = $request->input('send_address');
        $weight = $request->input('weight');

        /**虚拟数据
        $input['weight'] = $weight = 300;
        $input['gather_address'] = $gather_address = [["area"=>"东城区","city"=> "北京市","info"=> "123123","pro"=> "北京市"],["area"=>"房山区","city"=> "北京市","info"=> "星光路12号","pro"=> "北京市"]];
        $input['send_address'] = $send_address = [['area'=>'嘉定区',"city"=> "上海市","info"=>"江桥镇","pro"=> "上海市"],['area'=>'松江区',"city"=> "上海市","info"=>"佘山","pro"=> "上海市"]];
         **/
        $rules = [
            'gather_address'=>'required',
            'send_address'=>'required',
            'weight'=>'required',
        ];
        $message = [
            'gather_address.required'=>'请选择发货地址',
            'send_address.required'=>'请选择收货地址',
            'weight.required'=>'请填写货物重量',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $price_info = AppSettingParam::get();
            // 获取整车公里数
            $kilo = $this->countKlio(2,$gather_address,$send_address);
            $price_info = AppSettingParam::get();
            $price = 0;
            foreach($price_info as $key => $value){
                if($kilo >= $value->param1 && $kilo<= $value->param2){
                    $price = $value->start_price + $value->weight_price*$weight;
                    $prescription = $value->prescription;
                }
            }
            $msg['info'] = round($price);
            $msg['kilo'] = round($kilo);
            $msg['prescription'] = $prescription.'天';
            $msg['code'] = 200;
            $msg['msg']  = "数据拉取成功";
            return $msg;
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

    public function countKlio($type,$startcity_str,$endcity_str){
        if (count($startcity_str) == 1 && count($endcity_str) == 1){
            // 起点城市经纬度
            $start_action = bd_location($type,'',$startcity_str[0]['city'],$startcity_str[0]['area'],$startcity_str[0]['info']);//经纬度
            // 终点城市经纬度
            $end_action = bd_location($type,'',$endcity_str[0]['city'],$endcity_str[0]['area'],$endcity_str[0]['info']);//经纬度
            $list = direction($start_action['lat'], $start_action['lng'], $end_action['lat'], $end_action['lng']);
            $finally = $list['distance']/1000;
            $kilo = $this->mileage_interval(2,(int)$finally);
        }elseif(count($startcity_str) >1 && count($endcity_str) ==1){
            $km =0;
            for ($i=1;$i<count($startcity_str);$i++){
                $start_action = bd_location($type,'',$startcity_str[$i-1]['city'],$startcity_str[$i-1]['area'],$startcity_str[$i-1]['info']);//经纬度
                $start_action1= bd_location($type,'',$startcity_str[$i]['city'],$startcity_str[$i]['area'],$startcity_str[$i]['info']);
                // 获取百度返回的结果
                $list = direction($start_action['lat'], $start_action['lng'], $start_action1['lat'], $start_action1['lng']);
                $finally = $list['distance']/1000;
                $km1 = $this->mileage_interval(2,(int)$finally);
                $km += $km1;
            }
            $start_action2 = bd_location($type,'',end($startcity_str)['city'],end($startcity_str)['area'],end($startcity_str)['info']);
            $end_action = bd_location($type,'',$endcity_str[0]['city'],$endcity_str[0]['area'],$endcity_str[0]['info']);//经纬度
            $list2 = direction($start_action2['lat'], $start_action2['lng'], $end_action['lat'], $end_action['lng']);
            $finally1 = $list2['distance']/1000;
            $kilo1 = $this->mileage_interval(2,(int)$finally1);
            $kilo = $kilo1 + $km;
        }elseif(count($startcity_str) ==1 && count($endcity_str)>1){
            $km = 0;
            for ($i=1;$i<count($endcity_str);$i++){
                $end_action = bd_location($type,'',$endcity_str[$i-1]['city'],$endcity_str[$i-1]['area'],$endcity_str[$i-1]['info']);
                $end_action1 = bd_location($type,'',$endcity_str[$i]['city'],$endcity_str[$i]['area'],$endcity_str[$i]['info']);
                $list = direction($end_action['lat'], $end_action['lng'], $end_action1['lat'], $end_action1['lng']);
                $finally = $list['distance']/1000;
                $km1 = $this->mileage_interval(2,(int)$finally);
                $km += $km1;
            }
            $end_action2 = bd_location($type,'',$endcity_str[0]['city'],$endcity_str[0]['area'],$endcity_str[0]['info']);
            $start_action = bd_location($type,'',$startcity_str[0]['city'],$startcity_str[0]['area'],$startcity_str[0]['info']);//经纬度
            $list2 = direction($start_action['lat'], $start_action['lng'], $end_action2['lat'], $end_action2['lng']);
            $finally1 = $list2['distance']/1000;
            $kilo1 = $this->mileage_interval(2,(int)$finally1);
            $kilo = $kilo1 + $km;
        }else{
            $km =0;
            for ($i=1;$i<count($startcity_str);$i++){
                $start_action = bd_location($type,'',$startcity_str[$i-1]['city'],$startcity_str[$i-1]['area'],$startcity_str[$i-1]['info']);//经纬度
                $start_action1= bd_location($type,'',$startcity_str[$i]['city'],$startcity_str[$i]['area'],$startcity_str[$i]['info']);
                // 获取百度返回的结果
                $list = direction($start_action['lat'], $start_action['lng'], $start_action1['lat'], $start_action1['lng']);
                $finally = $list['distance']/1000;
                $km1 = $this->mileage_interval(2,(int)$finally);
                $km += $km1;
            }
            $km2 = 0;
            for ($j=1;$j<count($endcity_str);$j++){
                $end_action = bd_location($type,'',$endcity_str[$j-1]['city'],$endcity_str[$j-1]['area'],$endcity_str[$j-1]['info']);
                $end_action1 = bd_location($type,'',$endcity_str[$j]['city'],$endcity_str[$j]['area'],$endcity_str[$j]['info']);
                $list1 = direction($end_action['lat'],$end_action['lng'],$end_action1['lat'],$end_action1['lng']);
                $finally1 = $list1['distance']/1000;
                $km3 = $this->mileage_interval(2,(int)$finally1);
                $km2 += $km3;
            }
            $end_action3 = bd_location($type,'',$endcity_str[0]['city'],$endcity_str[0]['area'],$endcity_str[0]['info']);
            $start_action3 = bd_location($type,'',end($startcity_str)['city'],end($startcity_str)['area'],end($startcity_str)['info']);//经纬度
            $list2 = direction($start_action3['lat'], $start_action3['lng'], $end_action3['lat'], $end_action3['lng']);
            $finally2 = $list2['distance']/1000;
            $kilo1 = $this->mileage_interval(2,(int)$finally2);

            $kilo = $km + $km2 + $kilo1;

        }
        return $kilo;
    }

    /*
     * 计算公里数
     * */
    function mileage_interval($type,$km){
        // 查询里程系数标准
        $result = TmsParam::where(['type'=>$type])->select('scale_km','scale_km_two','scale_km_three','scale_km_four')->first()->toArray();
        // 默认计算后的里程数
        $finally = $km;
        // 获取0-100里程系数
        $scale_km = $result['scale_km'] == '' ? 1 : $result['scale_km'];
        // 获取100-300里程系数
        $scale_km_two = $result['scale_km_two'] == '' ? 1 : $result['scale_km_two'];
        // 获取300-1000里程系数
        $scale_km_three = $result['scale_km_three'] == '' ? 1 : $result['scale_km_three'];
        // 获取1000以上里程系数
        $scale_km_four = $result['scale_km_four'] == '' ? 1 : $result['scale_km_four'];
        // 判断0-100里程数所在范围并返回相应的里程数
        if($km >=0 && $km<= 100){
            $finally = $km*$scale_km;
            return $finally;
        }
        // 判断100-300里程数所在范围并返回相应的里程数
        if($km > 100 && $km<= 300){
            $finally = $km*$scale_km_two;
            return $finally;
        }
        // 判断300-1000里程数所在范围并返回相应的里程数
        if($km > 300 && $km<= 1000){
            $finally = $km*$scale_km_three;
            return $finally;
        }
        // 判断1000以上里程数所在范围并返回相应的里程数
        if($km > 1000){
            $finally = $km*$scale_km_four;
            return $finally;
        }
    }

    /***    拿去车辆类型数据     /api/fastOrder/getType
     */
    public function  getType(Request $request){
        $where=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
        ];
        $select=['self_id','parame_name','dimensions','allweight','allvolume','img'];
        //dd($where);
        $data['info']=TmsTypeCar::where($where)->select($select)->get();
        if ($data['info']){
            foreach ($data['info'] as $key =>$value){
                $data['info'][$key]['weight'] = $value['allweight']/1000;
                $data['info'][$key]['volume'] = $value['allvolume'];
                $data['info'][$key]['img'] = img_for($value['img'],'no_json');
                $data['info'][$key]['select_show'] = $value['parame_name'].'*核载：'.($value['allweight']/100).'吨/'.$value['allvolume'].'方';
                $data['info'][$key]['allweight'] = ($value['allweight']/1000).'吨';
                $data['info'][$key]['allvolume'] = $value['allvolume'].'方';
                $data['info'][$key]['dimensions'] = $value['dimensions'].'米';

            }
        }
//        dd($data['info']->toArray());
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;

        //dd($msg);
        return $msg;
    }

    /**
     * 预估价格
     * */
    public function count_klio(Request $request){
        $input         = $request->all();
        $car_type = $request->input('car_type');
        $gather_address= $request->input('gather_address');
        $send_address = $request->input('send_address');

        /**虚拟数据
        $input['gather_address']      = $gather_address      = [["area"=>"东城区","city"=> "北京市","info"=> "123123","pro"=> "北京市"]];
        $input['send_address']        = $send_address        = [['area'=>'城关区',"city"=> "拉萨市","info"=>"1234区","pro"=> "西藏"]];
        $input['car_type']            = $car_type           = 'type_202102051755118039490396';
         * **/
        $rules = [
            'car_type'=>'required',
            'gather_address'=>'required',
            'send_address'=>'required',
        ];
        $message = [
            'car_type.required'=>'请选择车辆类型',
            'gather_address.required'=>'请选择发货地址',
            'send_address.required'=>'请选择收货地址',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $startcity_str = $gather_address;
            $endcity_str  = $send_address;
            // 查询系数类型 2 整车
            $type = 2;
            // 起步价系数
            $scale_startprice = 1;
            // 里程偏离系数
            $scale_km = 1;
            // 单公里价格系数
            $scale_price_km = 1;
            // 查找选定的车型0
            $car_type = TmsTypeCar::where('self_id','=',$car_type)->select('self_id','low_price','costkm_price','parame_name')->first();
            // 查找系数比例
            $scale = TmsParam::where('type','=',$type)->select('scale_startprice','scale_km','scale_price_km','type')->first();
            if($scale->type){
                $scale_startprice = $scale->scale_startprice;
                $scale_km = $scale->scale_km;
                $scale_price_km = $scale->scale_price_km;
            }
            $kilo = $this->countKlio(2,$startcity_str,$endcity_str);
            // 计算起步价
            $startPrice = $car_type->low_price*$scale_startprice/100;
            // 运费 公里数*单价
            $freight = $kilo*$car_type->costkm_price*$scale_price_km/100;
            $allmoney = $startPrice+$freight;
            $kilo1 = round($kilo);
            // 总运费

            $re_data['km'] = $kilo1;//预计里程数
            $re_data['countprice'] = round($allmoney/100)*100;  //预计费用
            $re_data['maxprice'] = round($allmoney*1.1/100)*100;//预计最大价格
            if ($kilo1){
                $msg['data'] = $re_data;
                $msg['code'] = 200;
                $msg['msg']  = "数据拉取成功";
                return $msg;
            }else{
                $msg['code'] = 300;
                $msg['msg']  = "查不到数据";
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

    /***整车预估价格  /api/fastOrder/count_price

     **/
    public function count_price(Request $request){
        $input         = $request->all();
        $car_type = $request->input('car_type');
        $gather_address= $request->input('gather_address');
        $send_address = $request->input('send_address');
        $picktype = $request->input('pick_flag')??null;
        $sendtype = $request->input('send_flag')??null;
        $order_type = $request->input('order_type');

        /**虚拟数据
        $input['pick_flag'] = $picktype = 2;  // 'Y' 提货  'N' 自送
        $input['send_flag'] = $sendtype = 2;  // 'Y' 配送  'N' 自提
        $input['volume'] = $volume = 2;
        $input['weight'] = $weight = 1000;
        $input['car_type'] = $car_type = 'type_202102051755118039490396';
        $input['gather_address'] = $gather_address = [["area"=>"东城区","city"=> "北京市","info"=> "123123","pro"=> "北京市"],["area"=>"房山区","city"=> "北京市","info"=> "星光路12号","pro"=> "北京市"]];
        $input['send_address'] = $send_address = [['area'=>'嘉定区',"city"=> "上海市","info"=>"江桥镇","pro"=> "上海市"],['area'=>'松江区',"city"=> "上海市","info"=>"佘山","pro"=> "上海市"]];
         **/
        $rules = [
            'car_type'=>'required',
            'gather_address'=>'required',
            'send_address'=>'required',
        ];
        $message = [
            'car_type.required'=>'请选择车辆类型',
            'gather_address.required'=>'请选择发货地址',
            'send_address.required'=>'请选择收货地址',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $type = 2;
            // 装货费
            $pickPrice = 0;
            // 卸货费
            $sendPrice = 0;
            // 起步价系数
            $scale_startprice = 1;
            // 里程偏离系数
            $scale_km = 1;
            // 单公里价格系数
            $scale_price_km =1;
            // 装货费用系数
            $scale_pickgood = 1;
            // 卸货费用系数
            $scale_sendgood = 1;
            // 多点提配系数
            $scale_multistore = 1;
            // 促销优惠折扣系数 折扣为 总价格乘以折扣
            $scale_discount = 1;
            // 查找选定的车型
            $car_type = TmsTypeCar::where('self_id',$car_type)->select('self_id','low_price','costkm_price','parame_name','pickup_price','unload_price','morepickup_price')->first()->toArray();
            if (empty($car_type)){
                $msg['code'] = 301;
                $msg['msg']  = "请选择正确的车型";
                return $msg;
            }
            // 查找系数比例
            $scale = TmsParam::where('type',$type)->select('scale_startprice','scale_multistore','scale_km','scale_price_km','type','scale_pickgood','scale_sendgood','scale_discount')->first()->toArray();
            // 如果有系数值则写入
            if($scale['type']){
                $scale_startprice = $scale['scale_startprice'];
                $scale_km = $scale['scale_km'];
                $scale_price_km = $scale['scale_price_km'];
                $scale_pickgood = $scale['scale_pickgood'];
                $scale_sendgood = $scale['scale_sendgood'];
                $scale_multistore = $scale['scale_multistore'];
                $scale_discount = $scale['scale_discount'];
            }
            // 获取整车公里数
            $km = $this->countKlio(2,$gather_address,$send_address);
            // 里程费 公里数*单价
            $freight = $km*$car_type['costkm_price']*$scale_price_km/100;

            // 装货费
            if ($picktype == 2) {
                $pickPrice = $car_type['pickup_price']*$scale_pickgood/100;
            }
            // 卸货费
            if ($sendtype == 2) {
                $sendPrice = $car_type['unload_price']*$scale_sendgood/100;
            }
            // 装卸费
            $psPrice = $pickPrice+$sendPrice;

            // 计算起步价
            $startPrice = $car_type['low_price']*$scale_startprice/100;

            $startstr=[];
            $startstr_count = $endstr_count = 0;
            if ($gather_address){
                $pick_info=$gather_address;
                foreach ($pick_info as $k=> $v){
                    $startstr[]=$v['area'].$v['city'].$v['info'].$v['pro'];
                }
                $startstr_count = count(array_unique($startstr));
            }
            $endstr = [];
            if ($send_address){
                $send_info=$send_address;
                foreach ($send_info as $k=> $v){
                    $endstr[]=$v['area'].$v['city'].$v['info'].$v['pro'];
                }
                $endstr_count = count(array_unique($endstr));
            }
            // 多点提配费用
            $multistorePrice = ($startstr_count+$endstr_count-2)*$car_type['morepickup_price']*$scale_multistore/100;
            // 起步价费用取整
            $startPrice = round($startPrice,2);
            // 里程费费用取整
            $freight = round($freight,2);
            // 装卸费费用取整
            $psPrice = round($psPrice,2);
            // 多点提配费费用取整
            $multistorePrice = round($multistorePrice,2);
            //获取当天时间戳
            $nowday = mktime(23, 59, 59, date('m'), date('d'), date('Y'))*1000;
            //获取第二天时间戳
            $seconday = $nowday+24*60*60*1000;
            // 总运费 = 起步价 + 里程费 + 装卸费 + 多点提配费

            $allmoney = $startPrice+$freight+$multistorePrice;
            $freight1 = floor($freight+$startPrice);
            // 折扣价
            $discount = $allmoney*$scale_discount;
            $price['singleprice'] = round($allmoney/100)*100;
            $price['allmoney'] = round($allmoney/100)*100; // 总费用
            $price['discount'] = round($discount); // 优惠价
            $price['kilometre'] = round($km); // 公里数
            $price['freight'] = $freight1; // 里程费
            $price['psPrice'] = $psPrice; // 装卸费
            $price['pickprice'] = $pickPrice;//装货费
            $price['sendprice'] = $sendPrice;//卸货费
            $price['multistorePrice'] = $multistorePrice; // 多点提配费
            $price['maxprice'] = round($allmoney*1.1/100)*100;//预计最大费用
            if ($order_type == 'lift'){
                $price['allmoney'] = round($allmoney*0.7/100)*100; // 总费用
                $price['maxprice'] = round($allmoney*1.1*0.7/100)*100;//预计最大费用
            }
            $msg['info'] = $price;
            $msg['code'] = 200;
            $msg['msg']  = "数据拉取成功";
            return $msg;
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


<?php
namespace App\Http\Admin\Tms;
use App\Http\Controllers\FileController as File;
use App\Http\Controllers\TmsController as Tms;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsFastDispatch;
use App\Models\Tms\TmsLittleOrder;
use App\Models\Tms\TmsOrder;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;


class FastOrderController extends CommonController{
    /**
     * 快捷下单
     * */
    public function addFastOrder(Request $request,TMS $tms){
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_order';

        $operationing->access_cause     ='创建/修改订单';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';
        $user_info  = $request->get('user_info');//接收中间件产生的参数
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $project_type       =$user_info->type;
        $input      =$request->all();
        $total_user_id  = $user_info->total_user_id;

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
            case 'TMS3PL':
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
            if (empty($good_name_n)) {
                $msg['code'] = 306;
                $msg['msg'] = '货物名称不能为空！';
                return $msg;
            }
            if (empty($good_weight_n) || $good_weight_n <= 0) {
                $msg['code'] = 308;
                $msg['msg'] = '货物重量错误！';
                return $msg;
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
                if ($project_type == 'TMS3PL'){
                    $gather_address = $tms->address_contact($v['gather_address_id'],$v['gather_qu'],$v['gather_address'],$v['gather_name'],$v['gather_tel'],$group_info,$user_info,$now_time);
                }else{
                    $gather_address = $tms->address_contact($v['gather_address_id'],$v['gather_qu'],$v['gather_address'],$v['gather_name'],$v['gather_tel'],'',$user_info,$now_time);
                }

                if(empty($gather_address)){
                    $msg['code'] = 303;
                    $msg['msg'] = $pick_t.'地址不存在';
                    return $msg;
                }

                if ($project_type == 'TMS3PL'){
                    $send_address=$tms->address_contact($v['send_address_id'],$v['send_qu'],$v['send_address'],$v['send_name'],$v['send_tel'],$group_info,$user_info,$now_time);
                }else{
                    $send_address=$tms->address_contact($v['send_address_id'],$v['send_qu'],$v['send_address'],$v['send_name'],$v['send_tel'],'',$user_info,$now_time);
                }
                if(empty($send_address)){
                    $msg['code'] = 303;
                    $msg['msg'] = $send_t.'地址不存在';
                    return $msg;
                }

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
        $tms_order_status_type    =array_column(config('tms.tms_order_status_type'),'pay_status_text','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        $tms_control_type         =array_column(config('tms.tms_control_type'),'name','key');
        $tms_order_inco_type         =array_column(config('tms.tms_order_inco_type'),'icon','key');
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数
        $buttonInfo     = $request->get('buttonInfo');

        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $group_code     =$request->input('group_code');
        $company_id     =$request->input('company_id');
        $type           =$request->input('type');
        $state          =$request->input('order_status');
        $order_status   =$request->input('status') ?? null;
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'all','name'=>'use_flag','value'=>$use_flag],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
            ['type'=>'=','name'=>'type','value'=>$type],
            ['type'=>'=','name'=>'order_status','value'=>$state],
        ];


        $where=get_list_where($search);

        $select=['self_id','group_name','create_user_name','create_time','use_flag','order_type','order_status','clod','good_info','pay_status','discuss_flag','follow_discuss',
            'gather_address_id','gather_contacts_id','gather_name','gather_tel','gather_sheng','gather_shi','gather_qu','gather_qu_name','gather_address','follow_flag',
            'send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_shi','send_qu','send_qu_name','send_address','total_money','pay_type',
            'good_name','good_number','good_weight','good_volume','gather_shi_name','send_shi_name','gather_time','send_time'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsLittleOrder::where($where)->count(); //总的数据量
                $data['items']=TmsLittleOrder::where($where);
                if ($order_status){
                    if ($order_status == 1){
                        $data['items'] = $data['items']->where('order_status',3);
                    }elseif($order_status == 2){
                        $data['items'] = $data['items']->whereIn('order_status',[4,5]);
                    }elseif($order_status == 3){
                        $data['items'] = $data['items']->where('order_status',6);
                    }elseif($order_status == 6){
                        $data['items'] = $data['items']->where('order_status',2);
                    }else{
                        $data['items'] = $data['items']->where('order_status',7);
                    }
                }
                $data['items'] = $data['items']
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=TmsLittleOrder::where($where)->count(); //总的数据量
                $data['items']=TmsLittleOrder::where($where);
                if ($order_status){
                    if ($order_status == 1){
                        $data['items'] = $data['items']->where('order_status',3);
                    }elseif($order_status == 2){
                        $data['items'] = $data['items']->whereIn('order_status',[4,5]);
                    }elseif($order_status == 3){
                        $data['items'] = $data['items']->where('order_status',6);
                    }elseif($order_status == 6){
                        $data['items'] = $data['items']->where('order_status',2);
                    }else{
                        $data['items'] = $data['items']->where('order_status',7);
                    }
                }
                $data['items'] = $data['items']
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsOrder::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsOrder::where($where);
                if ($order_status){
                    if ($order_status == 1){
                        $data['items'] = $data['items']->where('order_status',3);
                    }elseif($order_status == 2){
                        $data['items'] = $data['items']->whereIn('order_status',[4,5]);
                    }elseif($order_status == 3){
                        $data['items'] = $data['items']->where('order_status',6);
                    }elseif($order_status == 6){
                        $data['items'] = $data['items']->where('order_status',2);
                    }else{
                        $data['items'] = $data['items']->where('order_status',7);
                    }
                }
                $data['items'] = $data['items']
                    ->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }

        $button_info1=[];
        $button_info2=[];
        $button_info3 = [];
        $button_info4 = [];
        $button_info5 = [];
        $button_info6 = [];
        $button_info7 = [];
        $button_info8 = [];
        foreach ($button_info as $k => $v){
            if($v->id == 647){
                $button_info1[]=$v;
                $button_info4[]=$v;
                $button_info5[]=$v;
                $button_info6[]=$v;
                $button_info7[]=$v;
                $button_info8[]=$v;
            }
            if($v->id == 272){
                $button_info2[] = $v;
                $button_info5[] = $v;
                $button_info8[] = $v;
            }
            if($v->id == 745){
                $button_info3[]=$v;
                $button_info4[]=$v;
            }
            if($v->id == 757){
                $button_info6[]=$v;
            }
            if($v->id == 758){
                $button_info7[]=$v;
            }
            if($v->id == 781){
                $button_info1[]=$v;
                $button_info6[]=$v;
                $button_info7[]=$v;
            }
            if($v->id == 782){
                $button_info1[]=$v;
            }
        }
        foreach ($data['items'] as $k=>$v) {
            $v->total_money = number_format($v->total_money/100, 2);
            $v->order_status_show=$tms_order_status_type[$v->order_status]??null;
            $v->order_type_show=$tms_order_type[$v->order_type]??null;
            $v->type_inco = img_for($tms_order_inco_type[$v->order_type],'no_json')??null;
            $v->button_info=$button_info;
            if ($order_status == 1 || $order_status==2){
                $v->button_info=$button_info5;
            }else if($v->order_status == 5){
                $v->button_info=$button_info4;
            }else{
                $v->button_info=$button_info1;
            }
            $v->self_id_show = substr($v->self_id,15);
            $v->send_time    = date('m-d H:i',strtotime($v->send_time));

            $v->clod=json_decode($v->clod,true);

            $cold = $v->clod;
            foreach ($cold as $key => $value){
                $cold[$key] =$tms_control_type[$value];
            }
            $v->clod= $cold;
            if($v->order_type == 'vehicle' || $v->order_type == 'lift'){
                $v->picktime_show = '装车时间 '.$v->send_time;
            }else{
                $v->picktime_show = '提货时间 '.$v->send_time;
            }

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
            $button7 = [];
            $button8 = [];
            foreach ($buttonInfo as $key =>$value){
                if ($value->id == 124 ){
                    $button1[] = $value;
                }
                if ($value->id == 144){
                    $button2[] = $value;
                    $button4[] = $value;
                }
                if($value->id == 161){
                    $button3[] = $value;
                }
                if ($value->id == 183){
                    $button4[] = $value;
                    $button5[] = $value;
                }
                if ($value->id == 162){
                    $button6[] = $value;
                }
                if ($value->id == 235){
                    $button7[] = $value;
                }
                if ($value->id == 238){
                    $button8[] = $value;
                }
                if ($v->order_status == 3 || $v->order_status == 2){
                    $v->button  = $button1;
                }
                if ($v->order_status == 5){
                    $v->button  = $button2;
                }
                if ($v->order_status == 2){
                    $v->button = $button3;
                }
                if ($v->order_status == 5 && $v->pay_type == 'offline' && $v->pay_status == 'N' && $v->app_flag == 1){
                    $v->button  = $button4;
                }
                if ($v->order_status == 6 && $v->pay_type == 'offline' && $v->pay_status == 'N' && $v->app_flag == 1){
                    $v->button  = $button5;
                }
                if($v->order_status == 6 && $v->discuss_flag == 'N'){
                    $v->button = $button7;
                }
                if($v->order_status == 6 && $v->discuss_flag == 'Y' && $v->follow_flag == 'N'){
                    $v->button = $button8;
                }
                if ($v->order_status  == 6 && $v->pay_type == 'offline' && $v->pay_status == 'N'){
                    $v->button  = $button5;
                }


            }
            if (empty($v->total_user_id)){
                $v->object_show = $v->group_name;
            }else{
                $v->object_show = $v->userReg->tel;

            }

        }

//        dd($data['items']);
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 极速版货主公司取消订单
     * */
    public function fastOrderCancel(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_order';
//        dd($user_info);
        $operationing->access_cause     ='取消订单';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';

        $input              =$request->all();

        /** 接收数据*/
        $order_id         = $request->input('order_id'); //调度单ID
        /*** 虚拟数据
        $input['order_id']     =$order_id='order_202105081609390186653744';
         * ***/
        $rules=[
            'order_id'=>'required',
        ];
        $message=[
            'order_id.required'=>'请选择要取消的订单',
        ];
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            //二次验证
            $order = TmsLittleOrder::where('self_id',$order_id)->select(['self_id','order_status','total_money','pay_type','group_code'])->first();
            if($user_info->group_code != '1234'){
                if ($order->order_status == 3){
                    $msg['code'] = 303;
                    $msg['msg'] = '该订单已被承接，取消请联系客服';
                    return $msg;
                }
            }

            if ($order->order_status == 6 ){
                $msg['code'] = 304;
                $msg['msg'] = '此订单已完成不可以取消';
                return $msg;
            }
            if ($order->order_status == 7 ){
                $msg['code'] = 305;
                $msg['msg'] = '此订单已取消';
                return $msg;
            }
            /** 修改订单状态为已取消 **/
            $order_update['order_status'] = 7;
            $order_update['update_time'] = $now_time;
            $id = TmsLittleOrder::where('self_id',$order_id)->update($order_update);
            /*** 修改可调度订单为已取消**/

            /** 判断是在线支付还是货到付款,在线支付应退还支付费用**/
            if ($order->pay_type == 'online'){
                $wallet = UserCapital::where('group_code',$order->group_code)->select(['self_id','money'])->first();
                $wallet_update['money'] = $order->total_money + $wallet->money;
                $wallet_update['update_time'] = $now_time;
                UserCapital::where('group_code',$order->group_code)->update($wallet_update);
                $data['self_id'] = generate_id('wallet_');
                $data['produce_type'] = 'refund';
                $data['capital_type'] = 'wallet';
                $data['money'] = $order->total_money;
                $data['create_time'] = $now_time;
                $data['update_time'] = $now_time;
                $data['now_money'] = $wallet_update['money'];
                $data['now_money_md'] = get_md5($wallet_update['money']);
                $data['wallet_status'] = 'SU';
                $data['wallet_type'] = 'user';
                $data['group_code'] = $order->group_code;
                UserWallet::insert($data);
            }
            /** 取消订单应该删除应付费用**/
            $money_where = [
//                ['total_user_id','=',$user_info->total_user_id],
                ['type','=','in'],

            ];
//            $money_update['delete_flag'] = 'N';
//            $money_update['update_time'] = $now_time;
//            $money_list = TmsOrderCost::where('order_id',$order_id)->select('self_id')->get();
//            $money_id_list = array_column($money_list->toArray(),'self_id');
//            TmsOrderCost::where($money_where)->whereIn('self_id',$money_id_list)->select(['self_id','money','delete_flag'])->update($money_update);

            /** 订单如果被承接应通知承运方订单已取消 **/

            $operationing->old_info = (object)$order;
            $operationing->table_id = $order_id;
            $operationing->new_info=$order_update;

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
            $erro=$validator->errors()->all();
            $msg['code']=300;
            $msg['msg']=null;
            foreach ($erro as $k => $v){
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }

    /**
     * 极速下单完成
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
        $input['self_id']       = $self_id       = 'order_202206101133087638395345';
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
            $TmsOrderDispatch = TmsFastDispatch::where('order_id',$self_id)->select('self_id')->get();
            if ($TmsOrderDispatch){
                $dispatch_list = array_column($TmsOrderDispatch->toArray(),'self_id');
                $orderStatus = TmsFastDispatch::where('delete_flag','=','Y')->whereIn('self_id',$dispatch_list)->update($update);


                /*** 订单完成后，如果订单是在线支付，添加运费到承接司机或3pl公司余额 **/
                if ($orderStatus){
//                    if ($order->pay_type == 'online'){
//                        dd($dispatch_list);
                    foreach ($dispatch_list as $key => $value){

                        $carriage_order = TmsFastDispatch::where('self_id','=',$value)->first();
                        if ($carriage_order->total_user_id){
                            $wallet_where = [
                                ['total_user_id','=',$carriage_order->total_user_id]
                            ];
                            $data['wallet_type'] = 'user';
                            $data['total_user_id'] = $carriage_order->total_user_id;
                        }else{
                            $wallet_where = [
                                ['group_code','=',$carriage_order->group_code]
                            ];
                            $data['wallet_type'] = '3PLTMS';
                            $data['group_code'] = $carriage_order->group_code;
                        }
                        $wallet = UserCapital::where($wallet_where)->select(['self_id','money'])->first();
                        $money['money'] = $wallet->money + $order->total_money;
                        $data['money'] = $order->total_money;
                        if ($carriage_order->group_code == $carriage_order->group_code){
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
                    }
//                    }
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


}
?>

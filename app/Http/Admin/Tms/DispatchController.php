<?php
namespace App\Http\Admin\Tms;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsDriver;
use App\Models\Tms\TmsLittleOrder;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderCost;
use App\Models\Tms\TmsOrderMoney;
use App\Models\Tms\TmsReceipt;
use App\Models\Tms\TmsSettle;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use App\Models\Tms\TmsOrderDispatch;
use App\Http\Controllers\DetailsController as Details;
use App\Http\Controllers\TmsController as Tms;
use App\Models\Tms\TmsGroup;
use App\Models\Tms\TmsCarriage;
use App\Models\Tms\TmsCarriageDispatch;
use App\Models\Tms\TmsCarriageDriver;



class DispatchController extends CommonController{

    /***    调度头部      /tms/dispatch/dispatchList
     */
    public function  dispatchList(Request $request){
        $group_info             = $request->get('group_info');
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');
        $order_state_type        =config('tms.3pl_dispatch_state');
        $data['state_info']       =$order_state_type;
        /** 抓取可调度的订单**/
        $where=[
            ['delete_flag','=','Y'],
            ['dispatch_flag','=','Y'],
            ['order_status','=',3],
        ];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsOrderDispatch::where($where)->count(); //总的数据量
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=TmsOrderDispatch::where($where)->count(); //总的数据量
                break;

            case 'more':
                $data['total']=TmsOrderDispatch::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                break;
        }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /***    调度分页      /tms/dispatch/dispatchPage
     */
    public function dispatchPage(Request $request){
        $pay_status     =config('tms.tms_order_status_type');
        $dispatch_status     =config('tms.tms_dispatch_type');
        $online_status     =config('tms.tms_online_type');
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数


        $carriage_flag    =array_column(config('tms.carriage_flag'),'name','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        $tms_order_inco_type         =array_column(config('tms.tms_order_inco_type'),'icon','key');
        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $group_code     =$request->input('group_code');
        $state          =$request->input('order_status');
        $on_line_flag   =$request->input('online_flag');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'all','name'=>'use_flag','value'=>$use_flag],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
            ['type'=>'=','name'=>'order_status','value'=>$state],
            ['type'=>'=','name'=>'on_line_flag','value'=>$on_line_flag],
            ['type'=>'!=','name'=>'order_type','value'=>'lift'],
        ];


        $where=$where1=get_list_where($search);

        $select=['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
        ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','receiver_id','total_user_id','receipt_flag'];
        $selectUser = ['self_id','tel'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsOrderDispatch::where($where)->count(); //总的数据量
                $data['items']=TmsOrderDispatch::with(['userReg' => function($query)use($select,$selectUser){
                    $query->where('delete_flag','=','Y');
                    $query->select($selectUser);
                }])
                    ->where($where);
                $data['items'] = $data['items']
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where1[]=['receiver_id','=',$group_info['group_code']];
                $where[] = ['group_code','=',$group_info['group_code']];
                $data['total']=TmsOrderDispatch::where($where)->orWhere($where1)->count(); //总的数据量
                $data['items']=TmsOrderDispatch::with(['userReg' => function($query)use($select,$selectUser){
                    $query->where('delete_flag','=','Y');
                    $query->select($selectUser);
                }])
                    ->where($where)->orWhere($where1);

                    $data['items'] = $data['items']
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsOrderDispatch::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsOrderDispatch::with(['userReg' => function($query)use($select,$selectUser){
                    $query->where('delete_flag','=','Y');
                    $query->select($selectUser);
                }])
                    ->where($where)->whereIn('group_code',$group_info['group_code']);
                $data['items'] = $data['items']
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
        foreach ($button_info as $k => $v){
            if($v->id == 643){
                $button_info1[]=$v;
            }
            if($v->id == 644){
                $button_info2[]=$v;
            }
            if ($v->id == 704){
                $button_info4[] = $v;
            }
            if ($v->id == 705){
                $button_info5[] = $v;
            }
            if($v->id ==  645){
                $button_info1[]=$v;
                $button_info2[]=$v;
                $button_info3[] = $v;
                $button_info4[] = $v;
                $button_info5[] = $v;
            }


        }
//        dd($button_info1,$button_info2,$button_info3);
        foreach ($data['items'] as $k=>$v) {
            $v->button_info=$button_info;
            $v->order_type_show=$tms_order_type[$v->order_type]??null;
            $v->type_inco = img_for($tms_order_inco_type[$v->order_type],'no_json')??null;
            $v->pay_status_color=$pay_status[$v->order_status-1]['pay_status_color']??null;
            $v->pay_status_text=$pay_status[$v->order_status-1]['pay_status_text']??null;
            if ($v->dispatch_flag == 'Y'){
                $v->dispatch_use_flag = 1;
            }else{
                $v->dispatch_use_flag = 2;
            }
            $v->dispatch_status_color=$dispatch_status[$v->dispatch_use_flag-1]['dispatch_status_color']??null;
            $v->dispatch_status_text=$dispatch_status[$v->dispatch_use_flag-1]['name']??null;
            if ($v->on_line_flag == 'Y'){
                $v->on_line_use_flag = 1;
            }else{
                $v->on_line_use_flag = 2;
            }
            $v->online_status_color=$online_status[$v->on_line_use_flag-1]['online_status_color']??null;
            $v->online_status_text=$online_status[$v->on_line_use_flag-1]['name']??null;
            $v->carriage_flag_show=$carriage_flag[$v->carriage_flag]??null;

            if($v->dispatch_flag =='N' && $v->on_line_flag =='Y' && $v->order_status != 2){
                $v->button_info=$button_info1;
            }elseif($v->dispatch_flag =='Y' && $v->on_line_flag =='N' && ($v->order_status == 2 || $v->order_status == 1)){
                $v->button_info=$button_info2;
            }elseif($v->dispatch_flag =='N' && $v->on_line_flag =='N'){
                $v->button_info=$button_info3;
            }else{
                $v->button_info=$button_info2;
            }
//            dd($button_info4);
            if ($v->order_status == 4){
                $v->button_info = $button_info4;
            }
            if ($v->order_status == 5 && $v->receipt_flag == 'N'){
                $v->button_info = $button_info5;
            }
            $v->total_money = number_format($v->total_money/100,2);
            $v->on_line_money = number_format($v->on_line_money/100,2);

            if (empty($v->total_user_id)){
                $v->object_show = $v->group_name;
            }else{
                $v->object_show = $v->userReg->tel;
            }

        }
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;

        return $msg;
    }



    /***    可调度订单列表    /tms/dispatch/createDispatch
     */
    public function createDispatch(Request $request){
        $group_info             = $request->get('group_info');
        $buttonInfo             = $request->get('buttonInfo');
//        dd($buttonInfo);
        $tms_order_status_type = array_column(config('tms.tms_order_status_type'),'pay_status_text','key');
        $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        $tms_order_inco_type         =array_column(config('tms.tms_order_inco_type'),'icon','key');
        $order_status    = $request->get('status');//状态值 后端调用固传 status = 1;
        /** 接收数据*/
        $input          =$request->all();
        $group_code     =$request->input('group_code');
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;
        $app_status = $request->input('app_status');
//        $input['group_code'] = $group_code =  '1234';
        $rules=[
            'group_code'=>'required',
        ];
        $message=[
            'group_code.required'=>'请选择业务公司',
        ];
//        $order_status = 2;
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['receiver_id','=',$group_code],
                ['order_type','!=','lift'],
            ];
            $select=['self_id','receiver_id','company_name','create_time','create_time','group_name','group_code','order_type','order_id',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','order_status','car_type','receipt_flag','remark',
                'send_sheng_name','send_shi_name','send_qu_name','send_address','clod','on_line_money','company_id','total_user_id','delete_flag',
                'good_info','good_number','good_weight','good_volume','total_money','send_time','gather_time','pay_type','pay_status','dispatch_flag'];
            $select2 = ['self_id','parame_name'];
            $select3 = ['self_id','total_user_id','tel'];
            $data['info']=TmsOrderDispatch::with(['tmsCarType' => function($query) use($select2){
                $query->select($select2);
            }])
                ->with(['userReg' => function($query) use($select3){
                    $query->select($select3);
                }])
                ->where($where);
            if ($order_status){
                if ($order_status == 1){
                    if ($app_status){
                        $data['info'] = $data['info']->whereIn('order_status',[2,3]);
                    }else{
                        $data['info'] = $data['info']->where('dispatch_flag','Y')->whereIn('order_status',[2,3]);
                    }
                }elseif($order_status == 2){
                    $data['info'] = $data['info']->whereIn('order_status',[4,5]);
                }else{
                    $data['info'] = $data['info']->where('order_status',6);
                }
            }

            $data['info'] = $data['info']
                ->select($select)
                ->offset($firstrow)
                ->limit($listrows)
                ->orderBy('update_time','DESC')->get(); //总的数据量
            $data['total'] = TmsOrderDispatch::where($where)->where('dispatch_flag','Y')->whereIn('order_status',[2,3])->count();
            foreach ($data['info'] as $key => $value){
//                dd($value);
                $value->self_id_show = substr($value->self_id,15);
                $value->on_line_money       = number_format($value->on_line_money/100, 2);
                $value->total_money       = number_format($value->total_money/100, 2);
                $value->order_type_show=$tms_order_type[$value->order_type]??null;
                $value->order_status_show = $tms_order_status_type[$value->order_status] ?? null;
                $value->type_inco = img_for($tms_order_inco_type[$value->order_type],'no_json')??null;
                $value->send_time         = date('m-d H:i',strtotime($value->send_time));
                    $value['good_info'] = json_decode($value['good_info'],true);
                    if (!empty($value['good_info'])) {
                        foreach ($value['good_info'] as $k => $v) {
                            if ($v['clod']) {
                                $v['clod'] = $tms_control_type[$v['clod']];
                            }
                            $value['good_info_show'] .= $v['good_name'] . ',';
                        }
                    }
                if ($value->order_type == 'vehicle' || $value->order_type == 'lift'){
                    if ($value->tmsCarType){
                        $value->car_type_show = $value->tmsCarType->parame_name;
                    }
                }
                $temperture = json_decode($value['clod'],true);
                foreach ($temperture as $kkk => $vvv){
                    $temperture[$kkk] = $tms_control_type[$vvv];
                }
                $value->temperture = implode(',',$temperture);

                if($value->order_type == 'vehicle' || $value->order_type == 'lift'){
                    $value->picktime_show = '装车时间 '.$value->send_time;
                }else{
                    $value->picktime_show = '提货时间 '.$value->send_time;
                }

                $value->temperture_show ='温度 '.$value->temperture;
                $value->order_id_show = '订单编号'.substr($value->self_id,15);
                if ($value->order_status == 1){
                    $value->state_font_color = '#333';
                }elseif($value->order_status == 2){
                    $value->state_font_color = '#333';
                }elseif($value->order_status == 3){
                    $value->state_font_color = '#0088F4';
                }elseif($value->order_status == 4){
                    $value->state_font_color = '#35B85F';
                }elseif($value->order_status == 5){
                    $value->state_font_color = '#35B85F';
                }elseif($value->order_status == 6){
                    $value->state_font_color = '#FF9400';
                }else{
                    $value->state_font_color = '#FF807D';
                }
                if($value->order_type == 'vehicle' || $value->order_type == 'lcl' || $value->order_type == 'lift'){
                    $value->order_type_color = '#E4F3FF';
                    $value->order_type_font_color = '#0088F4';
                    if ($value->order_type == 'vehicle'){
                        $value->order_type_color = '#0088F4';
                        $value->order_type_font_color = '#FFFFFF';
                    }
                    if ($value->TmsCarType){
                        $value->car_type_show = $value->TmsCarType->parame_name;
                        $value->good_info_show = '车型 '.$value->car_type_show;
                    }
                }else{
                    $value->good_info_show = '货物 '.$value->good_number.'件'.$value->good_weight.'kg'.$value->good_volume.'方';
                    $value->order_type_color = '#E4F3FF';
                    $value->order_type_font_color = '#0088F4';
                }
                $button1 = [];
                $button2 = [];
                $button3 = [];
                $button4 = [];
                $button5 = [];
                $button6 = [];
                $button7 = [];
                foreach ($buttonInfo as $kk =>$vv){
                    if ($vv->id == 129){
                        $button1[] = $vv;
                    }
                    if ($vv->id == 128){
                        $button1[] = $vv;
                        $button3[] = $vv;
                    }
                    if ($vv->id == 125){
                        $button2[] = $vv;
                    }
                    if ($vv->id == 126){
                        $button2[] = $vv;
                    }
                    if ($vv->id == 127){
                        $button4[] = $vv;
                        $button6[] = $vv;
                    }
                    if ($vv->id == 143){
                        $button5[] = $vv;
                    }
                    if ($vv->id == 145){
                        $button3[] = $vv;
                    }
                    if ($vv->id == 228){
                        $button6[] = $vv;
                        $button7[] = $vv;
                    }


                    if ($value->order_status == 3){
                        if ($value->order_type == 'vehicle'){
                            if ($value->group_code == $value->receiver_id || $value->receiver_id == $value->total_user_id){
                                $value->button  = $button3;
                            }else{
                                $value->button  = $button1;
                            }
                        }else{
                            $value->button  = $button3;
                        }

                    }
                    if ($value->order_status == 4){
                        $value->button  = $button2;
                    }
                    if ($value->order_status == 5 && $value->receipt_flag == 'N'){
                        $value->button  = $button4;
                    }
                    if ($value->order_status == 5 && $value->receipt_flag == 'N' && $value->pay_type == 'offline' && $value->pay_status == 'N' && $value->group_code != $value->receiver_id){
                        $value->button = $button6;
                    }
                    if ($value->order_status == 5 && $value->receipt_flag == 'Y' && $value->pay_type == 'offline' && $value->pay_status == 'N' && $value->group_code != $value->receiver_id){
                        $value->button = $button7;
                    }
                    if ($value->order_status == 6 && $value->receipt_flag == 'N'){
                        $value->button  = $button4;
                    }
                    if ($value->order_status == 6  && $value->pay_type == 'offline' && $value->pay_status == 'N' && $value->receipt_flag == 'N' && $value->group_code != $value->receiver_id){
                        $value->button = $button7;
                    }
//                    if ($value->receipt_flag == 'Y'){
//                        $value->button  = $button5;
//                    }
                    if ($value->receipt_flag == 'Y' && $value->pay_type == 'online'){
                        $value->button  = [];
                    }

                }

            }
//            dd($data['info']->toArray());
            $msg['code']=200;
            $msg['msg']="数据拉取成功";
            $msg['data']=$data;
            return $msg;
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


    /***    调度订单提交      /tms/dispatch/dispatchOrder
     */
    public function dispatchOrder(Request $request){
        $group_info             = $request->get('group_info');
        $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
        /** 接收数据*/
        $input              =$request->all();

        $group_code=$request->input('group_code');
//        $input['group_code'] = $group_code =  'dispatch_202102230939402797844824';
        $rules=[
            'group_code'=>'required',
        ];
        $message=[
            'group_code.required'=>'请选择业务公司',
        ];
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['dispatch_flag','=','Y'],
            ];
            $select=['self_id','receiver_id','company_name','create_time','create_time','group_name',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','order_status',
                'send_sheng_name','send_shi_name','send_qu_name','send_address','order_type',
                'good_info','good_number','good_weight','good_volume'];

            $data['info']=TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$group_code))->select($select)->get();
//            dd(explode(',',$group_code));
            $status = [];
            foreach ($data['info'] as $key => $value){
                $status[] = $value['order_status'];
                $value['good_info'] = json_decode($value['good_info'],true);
                foreach ($value['good_info'] as $k =>$v){
                    if ($v['clod']){
                        $v['clod'] =$tms_control_type[$v['clod']];
                    }
                    $value['good_info_show'] .= $v['good_name'].',';
                }
            }
            if (in_array(4,$status)){
                $msg['code']=301;
                $msg['msg']="有已调度订单，请确认";
                return $msg;
            }
            if (in_array(7,$status)){
                $msg['code']=301;
                $msg['msg']="有已取消订单，请确认";
                return $msg;
            }
            $msg['code']=200;
            $msg['msg']="数据拉取成功";
            $msg['data']=$data;
            return $msg;
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
    /***    调度数据提交      /tms/dispatch/addDispatch
     */
    public function addDispatch(Request $request,Tms $tms){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_carriage';

        $operationing->access_cause     ='创建调度';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';

        $input              =$request->all();

        /** 接收数据*/
        $dispatch_list      =$request->input('dispatch_list');
        $company_id         =$request->input('company_id'); //承运公司
        $carriage_flag      =$request->input('carriage_flag');
        $group_code      	=$request->input('group_code');
        $total_price 		= $request->input('total_price');//应付费用
        $car_info 			= $request->input('car_info');//司机车辆信息
        /*** 虚拟数据
        $input['dispatch_list']     =$dispatch_list='dispatch_202101311523511804451337,dispatch_202101311514279957651368';
//        $input['company_id']        =$company_id='company_202012291153523141320375'; //
        $input['carriage_flag']        =$carriage_flag='driver';  // 自己oneself，个体司机driver，承运商carriers 组合compose'
        $input['car_info']         =$car_info = [['car_id'=>'','type'=>'lease','car_number'=>'沪V12784','contacts'=>'刘伯温','tel'=>'19819819819','price'=>500,'company_id'='company_202102241023099922958143'],
            ['car_id'=>'','type'=>'oneself','car_number'=>'沪V44561','contacts'=>'赵匡胤','tel'=>'16868686868','price'=>100,'company_id'='']];
        $input['group_code'] = $group_code = '1234';
        $input['total_price'] = $total_price = 200;
        * ***/
        $rules=[
            'dispatch_list'=>'required',
        ];
        $message=[
            'dispatch_list.required'=>'必须选择最少一个可调度单',
        ];
        $where_check=[
            ['delete_flag','=','Y'],
            ['self_id','=',$group_code],
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $group_info= SystemGroup::where($where_check)->select('self_id','group_code','group_name')->first();

            $company_info = TmsGroup::where('self_id','=',$company_id)->select('self_id','company_name')->first();
            if ($company_id){
                if(empty($company_info)){
                    $msg['code'] = 303;
                    $msg['msg'] = '业务公司不存在';
                    return $msg;
                }
            }

            $where=[
                ['delete_flag','=','Y'],
            ];
            $select=['self_id','company_name','create_time','create_time','group_name','dispatch_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address',
                'send_sheng_name','send_shi_name','send_qu_name','send_address',
                'good_info','good_number','good_weight','good_volume'];
            $select1 = ['order_id'];
            $wait_info=TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$dispatch_list))->select($select)->get();
            $orderList=TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$dispatch_list))->select($select1)->get();
            if ($orderList){
                $id_list = array_column($orderList->toArray(),'order_id');
                $tmsOrder['order_status'] = 4;
                $tmsOrder['update_time']  = $now_time;
                TmsOrder::whereIn('self_id',$id_list)->update($tmsOrder);
            }

            if(empty($wait_info)){
                $msg['code'] = 304;
                $msg['msg'] = '您选择的调度单为空';
                return $msg;
            }

            $datalist= $order_info = $order_money = $settle_money =[];       //初始化数组为空
            $cando='Y';         //错误数据的标记
            $strs='';           //错误提示的信息拼接  当有错误信息的时候，将$cando设定为N，就是不允许执行数据库操作
            $abcd=0;            //初始化为0     当有错误则加1，页面显示的错误条数不能超过$errorNum 防止页面显示不全1
            $errorNum=50;       //控制错误数据的条数
            $a=1;

            $carriage_id            =generate_id('carriage_');
            foreach ($wait_info as $k => $v){
                if($v ->dispatch_flag == 'N' ){
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."行不可以调度".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }else{
                    $list['self_id']            =generate_id('c_d_');
                    $list['order_dispatch_id']        = $v->self_id;
                    $list['carriage_id']        = $carriage_id;
                    $list['group_code']         = $group_info->group_code;
                    $list['group_name']         = $group_info->group_name;
                    $list['create_user_id']     = $user_info->admin_id;
                    $list['create_user_name']   = $user_info->name;
                    $list['create_time']        =$list['update_time']=$now_time;
                    if($company_id){
                        $list['company_id']         = $company_info->self_id;
                        $list['company_name']         = $company_info->company_name;
                    }

                    $datalist[]=$list;
                }
                $a++;
            }
            if ($carriage_flag == 'oneself' || $carriage_flag == 'driver'){
                foreach ($car_info as $key => $value){
                    $order_list['self_id']            =generate_id('driver_');
                    $order_list['carriage_id']        = $carriage_id;
                    $order_list['group_code']         = $group_info->group_code;
                    $order_list['group_name']         = $group_info->group_name;
                    $order_list['create_user_id']     = $user_info->admin_id;
                    $order_list['create_user_name']   = $user_info->name;
                    $order_list['create_time']        =$order_list['update_time']=$now_time;
                    $order_list['car_id']   = $value['car_id'];
                    $car_list_info = $tms->get_car($value['car_id'],$value['car_number'],$value['type'],$group_info,$user_info,$now_time);
                    $order_list['car_id']   = $car_list_info->self_id;
                    if($company_id) {
                        $order_list['company_id'] = $company_info->self_id;
                        $order_list['company_name'] = $company_info->company_name;
                    }
                    $order_list['car_number']   =  $car_list_info->car_number;
                    $order_list['contacts']   =  $value['contacts'];
                    $order_list['tel']   = $value['tel'];
                    $order_list['price'] = $value['price']*100;
                    $order_info[]=$order_list;
                    $car_list[] = $car_list_info->car_possess;

                    $money['self_id']                    = generate_id('order_money_');
                    $money['shouk_driver_id']            = $order_list['self_id'];
                    $money['shouk_type']                 = 'DRIVER';
                    $money['fk_group_code']              = $group_info->group_code;
                    $money['fk_type']                    = 'GROUP_CODE';
                    $money['ZIJ_group_code']             = $group_info->group_code;
                    $money['carriage_id']                = $carriage_id;
                    $money['create_time']                = $now_time;
                    $money['update_time']                = $now_time;
                    $money['money']                      = $value['price']*100;
                    $money['money_type']                 = 'freight';
                    $money['type']                       = 'out';
                    $money['driver_id']                  = $order_list['self_id'];
                    $money['carriage_id']                = $carriage_id;
                    $order_money[] = $money;

                }
            }
            if($cando == 'N'){
                $msg['code'] = 306;
                $msg['msg'] = $strs;
                return $msg;
            }

            $data['self_id']            = $carriage_id;
            $data['create_user_id']     = $user_info->admin_id;
            $data['create_user_name']   = $user_info->name;
            $data['create_time']        = $data['update_time']=$now_time;
            $data['group_code']         = $group_info->group_code;
            $data['group_name']         = $group_info->group_name;
            $data['total_money']        = $total_price*100;
            $data['order_status']       = 2;
            if($company_id){
                $data['company_id']         = $company_info->self_id;
                $data['company_name']       = $company_info->company_name;
                $data['order_status']       = 1;

                $money['self_id']                    = generate_id('order_money_');
                $money['shouk_company_id']           = $company_info->self_id;
                $money['shouk_type']                 = 'COMPANY';
                $money['fk_group_code']              = $group_info->group_code;
                $money['fk_type']                    = 'GROUP_CODE';
                $money['ZIJ_group_code']             = $group_info->group_code;
                $money['carriage_id']                = $carriage_id;
                $money['create_time']                = $now_time;
                $money['update_time']                = $now_time;
                $money['money']                      = $total_price*100;
                $money['money_type']                 = 'freight';
                $money['type']                       = 'out';
                $order_money[] = $money;
            }

            if ($carriage_flag == 'oneself' || $carriage_flag == 'driver'){
                $count_car = array_unique($car_list);
                if ($count_car >1){
                    $data['carriage_flag']   =  'compose';
                }
            }else{
                $data['carriage_flag']       =  $carriage_flag;
            }
            $id=TmsCarriage::insert($data);
            TmsCarriageDispatch::insert($datalist);
            TmsOrderCost::insert($order_money);
             if ($carriage_flag == 'oneself' || $carriage_flag == 'driver'){
                 TmsCarriageDriver::insert($order_info);
             }


            $data_update['dispatch_flag']      ='N';
            $data_update['order_status']       = 4;
            $data_update['update_time']        =$now_time;
            TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$dispatch_list))->update($data_update);


			$operationing->table_id=$carriage_id;
            $operationing->old_info=null;
            $operationing->new_info=$data;

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


    /***    拿调度信息     /tms/dispatch/getDispatch
     */
    public function  getDispatch(Request $request){
        $company_id=$request->input('company_id');
        //$company_id='company_202012281339503129654415';
        $where=[
            ['delete_flag','=','Y'],
            ['company_id','=',$company_id],
        ];
        $select=['self_id','sheng_name','shi_name','qu_name','address','particular','company_id'];
	//dd($where);
        $data['address_info']=TmsAddress::where($where)->select($select)->get();

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        //dd($msg);
        return $msg;
    }


    /***    调度详情     /tms/dispatch/details
     */
    public function  details(Request $request,Details $details){
        $tms_order_status_type = array_column(config('tms.tms_order_status_type'),'pay_status_text','key');
        $self_id=$request->input('self_id');
        $table_name='tms_order_dispatch';
        $select=['self_id','order_id','company_id','company_name','create_time','use_flag','delete_flag','group_code','group_name','order_type','order_status','gather_name','remark',
            'gather_tel','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_name','send_tel','send_sheng_name','send_shi_name','send_qu_name','info','kilometre',
            'send_address','total_money','good_info','good_number','good_weight','good_volume','dispatch_flag','carriage_group_id','carriage_group_name','on_line_flag','on_line_money',
            'pick_flag','send_flag','clod','total_money','gather_time','send_time','receiver_id','total_user_id','pay_type','pay_status'];
//        $self_id='patch_202106231845194304919865';
//        $info=$details->details($self_id,$table_name,$select);

        $where = [
            ['self_id','=',$self_id],
        ];
        $select1 = ['self_id','carriage_id','order_dispatch_id'];
        $select2 = ['self_id','company_id','company_name','carriage_flag','total_money'];
        $select3 = ['carriage_id','car_number','contacts','tel','price','car_id'];
        $selectList = ['self_id','receipt','order_id','total_user_id','group_code','group_name'];
        $info = TmsOrderDispatch::with(['tmsCarriageDispatch'=>function($query)use($select,$select1,$select2,$select3){
            $query->where('delete_flag','=','Y');
            $query->select($select1);
            $query->with(['tmsCarriage'=>function($query)use($select2){
                $query->where('delete_flag','=','Y');
                $query->select($select2);
            }]);
            $query->with(['tmsCarriageDriver'=>function($query)use($select3){
                $query->where('delete_flag','=','Y');
                $query->select($select3);
            }]);
        }])
            ->with(['tmsReceipt'=>function($query)use($selectList){
                $query->where('delete_flag','=','Y');
                $query->select($selectList);
            }])
            ->where($where)->select($select)->first();
        if($info){
            $tms_order_type        =array_column(config('tms.tms_order_type'),'name','key');
            $tms_control_type        =array_column(config('tms.tms_control_type'),'name','key');
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $info->total_money=$info->total_money/100;
            $info->on_line_money=$info->on_line_money/100;
            $info->clod=json_decode($info->clod,true);
            $info->order_type_show=$tms_order_type[$info->order_type];
            $info->order_status_show = $tms_order_status_type[$info->order_status] ?? null;
            $info->self_id_show = substr($info->self_id,15);
            $info->driver_price = 0;
            $cold = $info->clod;
            foreach ($cold as $key => $value){
                $cold[$key] =$tms_control_type[$value];
            }
            $info->clod= $cold;
            $info->info = json_decode($info->info,true);
            /** 对商品信息进行处理*/

            $good_info=json_decode($info->good_info,true);
            foreach ($good_info as $k => $v){
                $good_info[$k]['clod_show']=$tms_control_type[$v['clod']];
            }
            $info->good_info_show=$good_info;
            if ($info->dispatch == 'N' && $info->on_line_flag == 'N'){
                foreach ($info->tmsCarriageDispatch->tmsCarriage as $kk =>$vv){
                    if ($vv->carriage_flag == 'compose'){
                        $info->driver_show = $info->tmsCarriageDispatch->tmsCarriageDriver->toArray();
                    }else{
                        $info->company_show = $vv->company_name;
                    }
                }
            }
            $car_list = [];
            if ($info->tmsCarriageDispatch){
                if ($info->tmsCarriageDispatch->tmsCarriageDriver){
                    foreach ($info->tmsCarriageDispatch->tmsCarriageDriver as $kk => $vv){
                        $carList['car_number'] = $vv->car_number;
                        $carList['tel'] = $vv->tel;
                        $carList['contacts'] = $vv->contacts;
                        $car_list[] = $carList;
                    }
                    $info->car_info = $car_list;
                }

            }
            if ($info->tmsReceipt){
                $receipt_info = img_for($info->tmsReceipt->receipt,'more');
                $info->receipt = $receipt_info;
            }

            if($info->tmsCarriage){
                $info->driver_price = $info->tmsCarriage->total_money;
            }
            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl' || $info->order_type == 'lift'){
                $order_info = $info->info;
                foreach ($order_info as $kkk => $vvv){
                    $order_info[$kkk]['good_weight'] = ($vvv['good_weight']/1000).'吨';
                }
                $info->info = $order_info;
                $info->good_weight = ($info->good_weight/1000).'吨';
            }
            $info->color = '#FF7A1A';
            $info->order_id_show = '订单编号'.$info->self_id_show;
            $order_details = [];
            $receipt_list = [];
            $car_info = [];
            $order_details1['name'] = '应收运费';
            if ($info->group_code != $info->receiver_id){
                $order_details1['value'] = '¥'.$info->on_line_money;
            }else{
                $order_details1['value'] = '¥'.$info->total_money;
            }
            $order_details1['color'] = '#FF7A1A';
            if ($info->group_code != $info->receiver_id || $info->total_user_id != $info->receiver_id){
                $order_details1['value'] = '¥'.$info->on_line_money;
            }
            $order_details2['name'] = '里程';
            $order_details2['value'] = $info->kilometre.'km';
            $order_details2['color'] = '#FF7A1A';

            $order_details4['name'] = '收货时间';
            $order_details4['value'] = $info->gather_time;
            $order_details4['color'] = '#000000';
            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl' || $info->order_type == 'lift'){
                $order_details3['name'] = '装车时间';
                $order_details3['value'] = $info->send_time;
                $order_details3['color'] = '#000000';
                $order_details5['name'] = '是否装卸';
                if($info->pick_flag == 'Y'){
                    $pick_flag_show = '需要装货';
                }else{
                    $pick_flag_show = '不需装货';
                }
                if ($info->send_flag == 'Y'){
                    $send_flag_show = '需要卸货';
                }else{
                    $send_flag_show = '不需卸货';
                }
                $order_details5['value'] = $pick_flag_show.' '.$send_flag_show;
                $order_details5['color'] = '#000000';
            }else{

                $order_details5['name'] = '是否提配';
                if($info->pick_flag == 'Y'){
                    $pick_flag_show = '需要提货';
                }else{
                    $pick_flag_show = '不需提货';
                }
                if ($info->send_flag == 'Y'){
                    $send_flag_show = '需要配送';
                }else{
                    $send_flag_show = '不需配送';
                }
                $order_details5['value'] = $pick_flag_show.' '.$send_flag_show;
                $order_details5['color'] = '#000000';
            }
            $order_details6['name'] = '订单备注';
            $order_details6['value'] = $info->remark;
            $order_details6['color'] = '#000000';
//            $order_details7['name'] = '班次号';
//            $order_details7['value'] = $info->shift_number;
//            $order_details8['name'] = '时效';
//            $order_details8['value'] = $info->trunking;

            $order_details9['name'] = '运输信息';
            $order_details9['value'] = $info->car_info;

            $order_details10['name'] = '回单信息';
            $order_details10['value'] = $info->receipt;

            $order_details11['name'] = '应付司机';
            $order_details11['value'] = '¥'.$info->driver_price;
            $order_details11['color'] = '#000000';

            $order_details[] = $order_details1;
//            $order_details[]= $order_details2;
            if(!empty($info->tmsCarriage)){
                $car_info[] = $order_details11;
            }
            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl' || $info->order_type == 'lift'){
                if($info->kilometre){
                    $order_details[] = $order_details2;
                }
                $order_details[] = $order_details3;
                $order_details[]= $order_details4;
                $order_details[]= $order_details5;
                $order_details[]= $order_details6;
            }else{
//                $order_details[]= $order_details7;
//                $order_details[]= $order_details8;
                $order_details[]= $order_details5;
                $order_details[]= $order_details6;
            }
            if(!empty($info->car_info)){
                $car_info[] = $order_details9;
            }
            if (!empty($info->receipt)){
                $receipt_list[] = $order_details10;
            }

            $data['info']=$info;
            $data['order_details'] = $order_details;
            $data['receipt_list'] = $receipt_list;
            $data['car_info'] = $car_info;
            $log_flag='N';
            $data['log_flag']=$log_flag;
            $log_num='10';
            $data['log_num']=$log_num;
            $data['log_data']=null;

            if($log_flag =='Y'){
                $data['log_data']=$details->change($self_id,$log_num);

            }

            $msg['code']=200;
            $msg['msg']="数据拉取成功";
            $msg['data']=$data;
            return $msg;
        }else{
            $msg['code']=300;
            $msg['msg']="没有查询到数据";
            return $msg;
        }

    }


    /***
     * 上线可调度订单		/tms/dispatch/online
     **/
    public function online(Request $request,Tms $tms){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_order_dispatch';

        $operationing->access_cause     ='上线';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='update';
        $operationing->now_time         =$now_time;

        $input              = $request->all();
        /** 接收数据*/
        $self_id			= $request->input('self_id');
        $price 				= $request->input('price');//上线价格
        $send_flag 			= $request->input('send_flag');
        $gather_flag 		= $request->input('gather_flag');
        $group_code      	= $request->input('group_code');
        $address 			= $request->input('address');//收发货地址
        $state              = $request->input('state'); // Y  空值;
        /*** 虚拟数据

        $self_id=  $input['self_id'] = 'dispatch_202104291124143304329583';
        $price=  $input['price'] = 2000;//上线价格
        $send_flag = $input['send_flag'] = 'N';
        $gather_flag = $input['gather_flag'] = 'N'; // Y 修改地址 N地址不变
        $group_code = $input['group_code'] = '1234';
        $state   = $input['state']  = '';
        $input['address']                =  $address=
            [
                'send_qu'=>'37',
                'send_address'=>'天安门广场',
                'send_name'=>'李白',
                'send_tel'=>'18888888888',

                'gather_qu'=>'145',
                'gather_address'=>'植物园',
                'gather_name'=>'杜甫',
                'gather_tel'=>'15555555555',
            ];
         ***/

        $rules=[
            'self_id'=>'required',
            'price'=>'required'
        ];
        $message=[
            'self_id.required'=>'必须选择最少一个可调度单',
            'price'=>'价格必须填写'
        ];

        $where=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
            ['self_id','=',$self_id]
        ];
//        if($price<=0){
//            $msg['code']=305;
//            $msg['msg']='上线价格不能小于0';
//            return $msg;
//        }
//        dd($group_info);
        $validator=Validator::make($input,$rules,$message);
        $where_check=[
            ['delete_flag','=','Y'],
            ['self_id','=',$group_code],
        ];
        $group_info= SystemGroup::where($where_check)->select('self_id','group_code','group_name')->first();

        $select=['self_id','order_id','company_id','company_name','order_type','order_status','total_money','dispatch_flag','gather_address_id','gather_contacts_id','gather_tel','gather_sheng',
            'gather_shi','gather_qu','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','gather_address_longitude','gather_address_latitude','pay_type',
            'send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_shi','send_qu','send_sheng_name','send_shi_name','send_qu_name','send_sheng_name','send_address','send_address_longitude',
            'send_address_latitude', 'on_line_flag','on_line_money'];
        $order_info = TmsOrderDispatch::where($where)->select($select)->first();

        if($validator->passes()) {
//        dd($order_info);
        /**** 验证调度单   **/
        if($order_info->on_line_flag == 'Y'){
            $msg['code']=303;
            $msg['msg']='调度单已上线，请勿重复操作';
            return $msg;
        }
        if ($order_info->dispatch_flag == 'N'){
            $msg['code']=303;
            $msg['msg']='调度单已调度，不能执行上线操作';
            return $msg;
        }
        if ($state){
            $data['on_line_flag'] = 'Y';
            $data['dispatch_flag'] = 'N';
            $data['receiver_id'] = null;
            $data['on_line_money'] = $price*100;
            $data['order_status'] = 2;
        }else{

        }
        $data['group_code']  = $group_info->group_code;
        $data['group_name']  = $group_info->group_name;
        $data['update_time'] = $now_time;
        if ($gather_flag == 'N'){
            $data['line_gather_address_id'] = $order_info->gather_address_id;
            $data['line_gather_contacts_id'] = $order_info->gather_contacts_id;
            $data['line_gather_name'] = $order_info->gather_name;
            $data['line_gather_tel'] = $order_info->gather_tel;
            $data['line_gather_sheng'] = $order_info->gather_sheng;
            $data['line_gather_shi'] = $order_info->gather_shi;
            $data['line_gather_qu'] = $order_info->gather_qu;
            $data['line_gather_sheng_name'] = $order_info->gather_sheng_name;
            $data['line_gather_shi_name'] = $order_info->gather_shi_name;
            $data['line_gather_qu_name'] = $order_info->gather_qu_name;
            $data['line_gather_address'] = $order_info->gather_address;
            $data['line_gather_address_longitude'] = $order_info->gather_address_longitude;
            $data['line_gather_address_latitude'] = $order_info->gather_address_latitude;
        }else{
            $gather_address = $tms->address_contact('',$address['gather_qu'],$address['gather_address'],$address['gather_name'],$address['gather_tel'],$group_info,$user_info,$now_time);
//            $gather_conact = $tms->contacts('',$address['gather_name'],$address['gather_tel'],$group_info,$user_info,$now_time);
            $data['line_gather_address_id'] = $gather_address->self_id;
//            $data['line_gather_contacts_id'] = $gather_conact->self_id;
            $data['line_gather_name'] = $gather_address->contacts;
            $data['line_gather_tel'] = $gather_address->tel;
            $data['line_gather_sheng'] = $gather_address->sheng;
            $data['line_gather_shi'] = $gather_address->shi;
            $data['line_gather_qu'] = $gather_address->qu;
            $data['line_gather_sheng_name'] = $gather_address->sheng_name;
            $data['line_gather_shi_name'] = $gather_address->shi_name;
            $data['line_gather_qu_name'] = $gather_address->qu_name;
            $data['line_gather_address'] = $gather_address->address;
            $data['line_gather_address_longitude'] = $gather_address->longitude;
            $data['line_gather_address_latitude'] = $gather_address->dimensionality;
        }
        if ($send_flag == 'N'){
            $data['line_send_address_id'] = $order_info->send_address_id;
//            $data['line_send_contacts_id'] = $order_info->send_contacts_id;
            $data['line_send_name'] = $order_info->send_name;
            $data['line_send_tel'] = $order_info->send_tel;
            $data['line_send_sheng'] = $order_info->send_sheng;
            $data['line_send_shi'] = $order_info->send_shi;
            $data['line_send_qu'] = $order_info->send_qu;
            $data['line_send_sheng_name'] = $order_info->send_sheng_name;
            $data['line_send_shi_name'] = $order_info->send_shi_name;
            $data['line_send_qu_name'] = $order_info->send_qu_name;
            $data['line_send_address'] = $order_info->send_address;
            $data['line_send_address_longitude'] = $order_info->send_address_longitude;
            $data['line_send_address_latitude'] = $order_info->send_address_latitude;
        }else{
            $send_address = $tms->address_contact('',$address['send_qu'],$address['send_address'],$group_info,$user_info,$now_time);
//            $send_contact = $tms->contacts('',$address['send_name'],$address['send_tel'],$group_info,$user_info,$now_time);
            $data['line_send_address_id'] = $send_address->self_id;
//            $data['line_send_contacts_id'] = $send_contact->self_id;
            $data['line_send_name'] = $send_address->contacts;
            $data['line_send_tel'] = $send_address->tel;
            $data['line_send_sheng'] = $send_address->sheng;
            $data['line_send_shi'] = $send_address->shi;
            $data['line_send_qu'] = $send_address->qu;
            $data['line_send_sheng_name'] = $send_address->sheng_name;
            $data['line_send_shi_name'] = $send_address->shi_name;
            $data['line_send_qu_name'] = $send_address->qu_name;
            $data['line_send_address'] = $send_address->address;
            $data['line_send_address_longitude'] = $send_address->longitude;
            $data['line_send_address_latitude'] = $send_address->dimensionality;
        }

            if($order_info->pay_type == 'online'){
                $money['self_id']                    = generate_id('order_money_');
                $money['fk_group_code']              = $group_info->group_code;
                $money['fk_type']                    = 'GROUP_CODE';
                $money['ZIJ_group_code']             = $group_info->group_code;
                $money['shouk_group_code']           = $group_info->group_code;
                $money['shouk_type']                 = 'PLATFORM';
                $money['dispatch_id']                = $self_id;
                $money['create_time']                = $now_time;
                $money['update_time']                = $now_time;
                $money['money']                      = $price*100;
                $money['money_type']                 = 'freight';
                $money['type']                       = 'out';
            }else{
                $money['self_id']                    = generate_id('order_money_');
                $money['fk_group_code']              = $group_info->group_code;
                $money['fk_type']                    = 'GROUP_CODE';
                $money['ZIJ_group_code']             = $group_info->group_code;
                $money['dispatch_id']                = $self_id;
                $money['create_time']                = $now_time;
                $money['update_time']                = $now_time;
                $money['money']                      = $price*100;
                $money['money_type']                 = 'freight';
                $money['type']                       = 'out';
            }

            TmsOrderCost::insert($money);
        $id=TmsOrderDispatch::where($where)->update($data);
        $operationing->old_info=$order_info;
        $operationing->new_info=$data;
        $operationing->table_id = $self_id;
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

    /***
     * 下线可调度订单		/tms/dispatch/unline
     **/

    public function unline(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $table_name='tms_order_dispatch';
        $self_id=$request->input('self_id');
        $operationing->access_cause='下线';
        $operationing->table = $table_name;
        $operationing->table_id = $self_id;
        $operationing->now_time = $now_time;

//        $self_id='dispatch_202101161343430556992404';
        $where=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
            ['self_id','=',$self_id]
        ];
        $select=['self_id','order_id','on_line_money','group_code','company_id','company_name','order_type','order_status','total_money','dispatch_flag', 'send_address_latitude', 'on_line_flag','pay_type','pay_status'];
        $order_info = TmsOrderDispatch::where($where)->select($select)->first();
        if($order_info->on_line_flag == 'N'){
            $msg['code']=303;
            $msg['msg']='调度单已下线，请勿重复操作';
            return $msg;
        }

        $old_info = [
            'on_line_flag'=>$order_info->on_line_flag,
            'update_time'=>$now_time
        ];
            $data['on_line_flag'] = 'N';
            $data['dispatch_flag'] = 'Y';
            $data['order_status']  = 3;
            $data['receiver_id'] = $order_info->group_code;
            $data['update_time'] = $now_time;
            $id=TmsOrderDispatch::where($where)->update($data);

            if($order_info->pay_type == 'online' && $order_info->pay_status == 'Y'){
                $wallet = UserCapital::where('group_code',$user_info->group_code)->select(['self_id','money'])->first();
                $wallet_update['money'] = $order_info->on_line_money + $wallet->money;
                $wallet_update['update_time'] = $now_time;
                UserCapital::where('group_code',$user_info->group_code)->update($wallet_update);
                $wallet_info['self_id'] = generate_id('wallet_');
                $wallet_info['produce_type'] = 'refund';
                $wallet_info['capital_type'] = 'wallet';
                $wallet_info['money'] = $order_info->on_line_money;
                $wallet_info['create_time'] = $now_time;
                $wallet_info['update_time'] = $now_time;
                $wallet_info['now_money'] = $wallet_update['money'];
                $wallet_info['now_money_md'] = get_md5($wallet_update['money']);
                $wallet_info['wallet_status'] = 'SU';
                $wallet_info['wallet_type'] = 'user';
                $wallet_info['group_code'] = $user_info->group_code;
                $wallet_info['group_name'] = $user_info->group_name;
                UserWallet::insert($wallet_info);
            }

            $money_where = [
                ['dispatch_id','=',$self_id],
                ['type','=','out']
            ];
            $update['delete_flag'] = 'N';
            $update['update_time'] = $now_time;
            $tmsOrderCost = TmsOrderCost::where('dispatch_id',$self_id)->update($update);

            $operationing->old_info = (object)$old_info;
            $operationing->table_id = $self_id;
            $operationing->new_info=$data;

            if($id){
                $msg['code'] = 200;
                $msg['msg'] = "操作成功";
                return $msg;
            }else{
                $msg['code'] = 302;
                $msg['msg'] = "操作失败";
                return $msg;
            }
    }

    /**
     * 取消调度(3pl) /tms/dispatch/dispatchCancel
     * */
    public function dispatchCancel(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_order_dispatch';
//        dd($user_info);
        $operationing->access_cause     ='确认送达';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';

        $input              =$request->all();

        /** 接收数据*/
        $dispatch_id        = $request->input('dispatch_id'); //调度单ID
        /*** 虚拟数据
        $input['dispatch_id']     =$dispatch_id='dispatch_202103041052309956180789';
         * ***/
        $rules=[
            'dispatch_id'=>'required',
        ];
        $message=[
            'dispatch_id.required'=>'请选择运输订单',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];
            $dispatch_where = [
                ['delete_flag','=','Y'],
                ['order_dispatch_id','=',$dispatch_id],
            ];

            $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','order_id',
                'send_sheng_name','send_shi_name','send_qu_name','send_address','receiver_id',
                'good_info','good_number','good_weight','good_volume','total_money','on_line_money'];
//            $wait_info=TmsOrderDispatch::where($where)->select($select)->first();
            $select1 = ['self_id','carriage_id','order_dispatch_id'];
            $select2 = ['self_id','company_id','company_name','carriage_flag','total_money'];
            $select3 = ['carriage_id','car_number','contacts','tel','price','car_id','self_id'];
            $wait_info = TmsOrderDispatch::with(['tmsCarriageDispatch'=>function($query)use($select,$select1,$select2,$select3,$dispatch_where){
                $query->where($dispatch_where);
                $query->select($select1);
                $query->with(['tmsCarriage'=>function($query)use($select2){
                    $query->select($select2);
                }]);
                $query->with(['tmsCarriageDriver'=>function($query)use($select3){
                    $query->select($select3);
                }]);
            }])->where($where)->select($select)->first();
//            dump($wait_info->toArray());
            //调度订单修改订单状态
            $order_where = [
                ['self_id','=',$wait_info->order_id]
            ];

            $dispatch_where = [
                ['order_id','=',$wait_info->order_id]
            ];

            //判断是否所有的运输单状态是否都为调度，是修改订单状态为已接单
            $tmsOrderDispatch = TmsOrderDispatch::where($dispatch_where)->select(['self_id'])->get();
            if ($tmsOrderDispatch){
                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
                $orderStatus = TmsOrderDispatch::where('self_id','!=',$dispatch_id)->whereIn('self_id',$dispatch_list)->select(['order_status'])->get();
                $arr = array_unique(array_column($orderStatus->toArray(),'order_status'));
                if (count($arr) >= 1){
                    if (implode('',$arr) == 3){
                        $order_update['order_status'] = 3;
                        $order_update['update_time']  = $now_time;
                        $order = TmsOrder::where($order_where)->update($order_update);
                    }
                }else{
                    $order_update['order_status'] = 3;
                    $order_update['update_time']  = $now_time;
                    $order = TmsOrder::where($order_where)->update($order_update);
                }
            }

            //修改当前运输单状态及同一调度运输单状态
            $dispatch_order['order_status']  = 3;
            $dispatch_order['dispatch_flag'] = 'Y';
            $dispatch_order['update_time']   = $now_time;
            $id = TmsOrderDispatch::where($where)->update($dispatch_order);


            //删除所有关联运输数据
            $list['delete_flag']         = 'N';
            $list['update_time']         = $now_time;

//            dump($wait_info->toArray());
            TmsCarriageDispatch::where('self_id',$wait_info->tmsCarriageDispatch->self_id)->update($list);
            foreach ($wait_info->tmsCarriageDispatch->tmsCarriage as $key => $value){
//                dump($value);
//                dump($value->self_id);
                TmsCarriage::where('self_id',$value->self_id)->update($list);
                if ($value->carriage_flag == 'carriers'){
                    $money_where = [
                        ['fk_group_code','=',$wait_info->receiver_id],
                        ['fk_type','=','GROUP_CODE'],
                        ['shouk_company_id','=',$value->company_id],
                        ['shouk_type','=','COMPANY'],
                        ['type','=','out'],
                        ['carriage_id','=',$value->self_id],
//                        ['dispatch_id','=',$dispatch_id]
                    ];
                    TmsOrderCost::where($money_where)->update($list);
                }else{
                    foreach($wait_info->tmsCarriageDispatch->tmsCarriageDriver as $k => $v){
//                        dump($v);
                        $money_where = [
                            ['fk_group_code','=',$wait_info->receiver_id],
                            ['fk_type','=','GROUP_CODE'],
                            ['shouk_driver_id','=',$v->self_id],
                            ['shouk_type','=','DRIVER'],
                            ['type','=','out'],
                            ['carriage_id','=',$v->carriage_id],
                            ['driver_id','=',$v->self_id],
                            ['money','=',$v->price]
                        ];
                        TmsOrderCost::where($money_where)->update($list);
                        TmsCarriageDriver::where('self_id',$v->self_id)->update($list);
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
     * 完成运输(3pl) /tms/dispatch/carriageDone
     * */
    public function carriageDone(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_order_dispatch';
//        dd($user_info);
        $operationing->access_cause     ='确认送达';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';

        $input              =$request->all();

        /** 接收数据*/
        $dispatch_id        = $request->input('dispatch_id'); //调度单ID
        /*** 虚拟数据
        $input['dispatch_id']     =$dispatch_id='patch_202105091028035791605429';
         * ***/
        $rules=[
            'dispatch_id'=>'required',
        ];
        $message=[
            'dispatch_id.required'=>'请选择运输订单',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];

            $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','order_id','receiver_id','group_code',
                'send_sheng_name','send_shi_name','send_qu_name','send_address','order_status',
                'good_info','good_number','good_weight','good_volume','total_money','on_line_money'];
//            $wait_info=TmsOrderDispatch::where($where)->select($select)->first();
            $select1 = ['self_id','carriage_id','order_dispatch_id'];
            $select2 = ['self_id','company_id','company_name','carriage_flag','total_money'];
            $select3 = ['carriage_id','car_number','contacts','tel','price','car_id'];
            $wait_info = TmsOrderDispatch::with(['tmsCarriageDispatch'=>function($query)use($select,$select1,$select2,$select3){
                $query->select($select1);
                $query->with(['tmsCarriage'=>function($query)use($select2){
                    $query->select($select2);
                }]);
                $query->with(['tmsCarriageDriver'=>function($query)use($select3){
                    $query->select($select3);
                }]);
            }])->where($where)->select($select)->first();
            if ($wait_info->order_status == 5){
                $msg['code'] = 301;
                $msg['msg'] = "已确认货物送达，请勿重复操作";
                return $msg;
            }
            if($wait_info->group_code == $wait_info->receiver_id){
                $receipt = TmsReceipt::where('order_id',$dispatch_id)->count();
                if ($receipt = 0){
                    $msg['code'] = 302;
                    $msg['msg'] = "请上传回单！";
                    return $msg;
                }
            }
            //调度订单修改订单状态
            $order_where = [
                ['self_id','=',$wait_info->order_id]
            ];
            $dispatch_where = [
                ['order_id','=',$wait_info->order_id]
            ];
            $flag = 'Y';
            //判断是否所有的运输单状态是否都为调度，是修改订单状态为已接单
            $tmsOrderDispatch = TmsOrderDispatch::where($dispatch_where)->select(['self_id'])->get();

            if ($tmsOrderDispatch){
                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
                $orderStatus = TmsOrderDispatch::where('self_id','!=',$dispatch_id)->whereIn('self_id',$dispatch_list)->select(['order_status'])->get();
                $arr = array_unique(array_column($orderStatus->toArray(),'order_status'));
//                dump($arr);
                if (count($arr) >= 1){
                    if (implode('',$arr) == 5){
                        $flag = 'Y';
                    }else{
                        $flag = 'N';
                    }
                }else{
                    $flag = 'Y';
                }
            }
            //修改当前运输单状态

            $dispatch_order['update_time']   = $now_time;

            if ($wait_info->group_code == $wait_info->receiver_id){
                $order_update['order_status'] = 6;
                $dispatch_order['order_status']  = 6;
            }else{
                $order_update['order_status'] = 5;
                $dispatch_order['order_status']  = 5;
            }
            $order_update['update_time']  = $now_time;
            if ($flag == 'Y'){
                $order = TmsOrder::where($order_where)->update($order_update);
            }
            $id = TmsOrderDispatch::where($where)->update($dispatch_order);
            $carriage_order['order_status']  = 4;
            $carriage_order['update_time']   = $now_time;
            foreach ($wait_info->tmsCarriageDispatch->tmsCarriage as $key => $value){
                TmsCarriage::where('self_id',$value->self_id)->update($carriage_order);
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
     * 上传回单  /tms/dispatch/uploadReceipt
     * */
    public function uploadReceipt(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_receipt';

        $operationing->access_cause     ='上传回单';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';

        $input              =$request->all();
		//dd($input);
        /** 接收数据*/
        $order_id            =$request->input('order_id');
        $receipt             =$request->input('receipt');


        /*** 虚拟数据
        $input['order_id']           =$order_id='dispatch_202103191711247168432433';
        $input['receipt']              =$receipt=[['url'=>'https://bloodcity.oss-cn-beijing.aliyuncs.com/images/2021-03-20/829b89fa038d26bc6af59a76f16794c5.jpg','width'=>'','height'=>'']];
        **/
        $rules=[
            'order_id'=>'required',
            'receipt'=>'required',
        ];
        $message=[
            'order_id.required'=>'请选择运输单',
            'receipt.required'=>'请选择要上传的回单',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$order_id],
            ];
            $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name',
                'gather_address','order_status','send_sheng_name','send_shi_name','send_qu_name','send_address', 'good_info','good_number','good_weight','good_volume','total_money',
                'on_line_money'];
            $wait_info=TmsOrderDispatch::where($where)->select($select)->first();
            if(!in_array($wait_info->order_status,[5,6])){
                $msg['code']=301;
                $msg['msg']='请确认订单已送达';
                return $msg;
            }
            $data['self_id'] = generate_id('receipt_');
            $data['receipt'] = img_for($receipt,'in');
            $data['order_id'] = $order_id;
            $data['create_time'] = $data['update_time'] = $now_time;
            $data['group_code']  = $user_info->group_code;
            $data['group_name']  = $user_info->group_name;

            $id=TmsReceipt::insert($data);

            $order_update['receipt_flag'] = 'Y';
            $order_update['update_time']  = $now_time;
            TmsOrderDispatch::where($where)->update($order_update);
            $operationing->old_info = (object)$wait_info;
            $operationing->table_id = $order_id;
            $operationing->new_info=$data;

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

    /*
     * 顺风车列表 /tms/dispatch/createLift
     * */
    public function createLift(Request $request){
        $group_info             = $request->get('group_info');
        $buttonInfo             = $request->get('buttonInfo');
//        dd($buttonInfo);
        $tms_order_status_type = array_column(config('tms.tms_order_status_type'),'pay_status_text','key');
        $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        $tms_order_inco_type         =array_column(config('tms.tms_order_inco_type'),'icon','key');
        $order_status    = $request->get('status');//状态值 后端调用固传 status = 1;
        /** 接收数据*/
        $input          =$request->all();
        $group_code     =$request->input('group_code');
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;
        $app_status = $request->input('app_status');
//        $input['group_code'] = $group_code =  '1234';
        $rules=[
            'group_code'=>'required',
        ];
        $message=[
            'group_code.required'=>'请选择业务公司',
        ];
//        $order_status = 2;
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['receiver_id','=',$group_code],
                ['order_type','=','lift'],
            ];
            $select=['self_id','receiver_id','company_name','create_time','create_time','group_name','group_code','order_type','order_id','carpool',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','order_status','car_type','receipt_flag','remark',
                'send_sheng_name','send_shi_name','send_qu_name','send_address','clod','on_line_money','company_id','total_user_id','delete_flag',
                'good_info','good_number','good_weight','good_volume','total_money','send_time','gather_time','pay_type','pay_status','dispatch_flag'];
            $select2 = ['self_id','parame_name'];
            $select3 = ['self_id','total_user_id','tel'];
            $data['info']=TmsOrderDispatch::with(['tmsCarType' => function($query) use($select2){
                $query->select($select2);
            }])
                ->with(['userReg' => function($query) use($select3){
                    $query->select($select3);
                }])
                ->where($where);
            if ($order_status){
                if ($order_status == 1){
                    if ($app_status){
                        $data['info'] = $data['info']->whereIn('order_status',[2,3]);
                    }else{
                        $data['info'] = $data['info']->where('dispatch_flag','Y')->whereIn('order_status',[2,3]);
                    }
                }elseif($order_status == 2){
                    $data['info'] = $data['info']->whereIn('order_status',[4,5]);
                }else{
                    $data['info'] = $data['info']->where('order_status',6);
                }
            }

            $data['info'] = $data['info']
                ->select($select)
                ->offset($firstrow)
                ->limit($listrows)
                ->orderBy('update_time','DESC')->get(); //总的数据量
            $data['total'] = TmsOrderDispatch::where($where)->where('dispatch_flag','Y')->whereIn('order_status',[2,3])->count();
            foreach ($data['info'] as $key => $value){
//                dd($value);
                $value->self_id_show = substr($value->self_id,15);
                $value->on_line_money       = number_format($value->on_line_money/100, 2);
                $value->total_money       = number_format($value->total_money/100, 2);
                $value->order_type_show=$tms_order_type[$value->order_type]??null;
                $value->order_status_show = $tms_order_status_type[$value->order_status] ?? null;
                $value->type_inco = img_for($tms_order_inco_type[$value->order_type],'no_json')??null;
                $value->send_time         = date('m-d H:i',strtotime($value->send_time));
                $value['good_info'] = json_decode($value['good_info'],true);
                if (!empty($value['good_info'])) {
                    foreach ($value['good_info'] as $k => $v) {
                        if ($v['clod']) {
                            $v['clod'] = $tms_control_type[$v['clod']];
                        }
                        $value['good_info_show'] .= $v['good_name'] . ',';
                    }
                }
                if ($value->order_type == 'vehicle' || $value->order_type == 'lift'){
                    if ($value->tmsCarType){
                        $value->car_type_show = $value->tmsCarType->parame_name;
                    }
                }
                $temperture = json_decode($value['clod'],true);
                foreach ($temperture as $kkk => $vvv){
                    $temperture[$kkk] = $tms_control_type[$vvv];
                }
                $value->temperture = implode(',',$temperture);

                if($value->order_type == 'vehicle' || $value->order_type == 'lift'){
                    $value->picktime_show = '装车时间 '.$value->send_time;
                }else{
                    $value->picktime_show = '提货时间 '.$value->send_time;
                }

                $value->temperture_show ='温度 '.$value->temperture;
                $value->order_id_show = '订单编号'.substr($value->self_id,15);
                if ($value->order_status == 1){
                    $value->state_font_color = '#333';
                }elseif($value->order_status == 2){
                    $value->state_font_color = '#333';
                }elseif($value->order_status == 3){
                    $value->state_font_color = '#0088F4';
                }elseif($value->order_status == 4){
                    $value->state_font_color = '#35B85F';
                }elseif($value->order_status == 5){
                    $value->state_font_color = '#35B85F';
                }elseif($value->order_status == 6){
                    $value->state_font_color = '#FF9400';
                }else{
                    $value->state_font_color = '#FF807D';
                }
                if($value->order_type == 'vehicle' || $value->order_type == 'lcl' || $value->order_type == 'lift'){
                    $value->order_type_color = '#E4F3FF';
                    $value->order_type_font_color = '#0088F4';
                    if ($value->order_type == 'vehicle'){
                        $value->order_type_color = '#0088F4';
                        $value->order_type_font_color = '#FFFFFF';
                    }
                    if ($value->TmsCarType){
                        $value->car_type_show = $value->TmsCarType->parame_name;
                        $value->good_info_show = '车型 '.$value->car_type_show;
                    }
                }else{
                    $value->good_info_show = '货物 '.$value->good_number.'件'.$value->good_weight.'kg'.$value->good_volume.'方';
                    $value->order_type_color = '#E4F3FF';
                    $value->order_type_font_color = '#0088F4';
                }
                $button1 = [];
                $button2 = [];
                $button3 = [];
                $button4 = [];
                $button5 = [];
                $button6 = [];
                $button7 = [];
                foreach ($buttonInfo as $kk =>$vv){
                    if ($vv->id == 129){
                        $button1[] = $vv;
                    }
//                    if ($vv->id == 128){
//                        $button1[] = $vv;
//                        $button3[] = $vv;
//                    }
                    if ($vv->id == 125){
                        $button2[] = $vv;
                    }
                    if ($vv->id == 126){
                        $button2[] = $vv;
                    }
                    if ($vv->id == 127){
                        $button4[] = $vv;
                        $button6[] = $vv;
                    }
                    if ($vv->id == 143){
                        $button5[] = $vv;
                    }
                    if ($vv->id == 145){
                        $button3[] = $vv;
                    }
                    if ($vv->id == 228){
                        $button6[] = $vv;
                        $button7[] = $vv;
                    }


                    if ($value->order_status == 3){
                        if ($value->order_type == 'vehicle'){
                            if ($value->group_code == $value->receiver_id || $value->receiver_id == $value->total_user_id){
                                $value->button  = $button3;
                            }else{
                                $value->button  = $button1;
                            }
                        }else{
                            $value->button  = $button3;
                        }

                    }
                    if ($value->order_status == 4){
                        $value->button  = $button2;
                    }
                    if ($value->order_status == 5 && $value->receipt_flag == 'N'){
                        $value->button  = $button4;
                    }
                    if ($value->order_status == 5 && $value->receipt_flag == 'N' && $value->pay_type == 'offline' && $value->pay_status == 'N'){
                        $value->button = $button6;
                    }
                    if ($value->order_status == 5 && $value->receipt_flag == 'Y' && $value->pay_type == 'offline' && $value->pay_status == 'N'){
                        $value->button = $button7;
                    }
                    if ($value->order_status == 6 && $value->receipt_flag == 'N'){
                        $value->button  = $button4;
                    }
                    if ($value->order_status == 6  && $value->pay_type == 'offline' && $value->pay_status == 'N' && $value->receipt_flag == 'N'){
                        $value->button = $button7;
                    }
//                    if ($value->receipt_flag == 'Y'){
//                        $value->button  = $button5;
//                    }
                    if ($value->receipt_flag == 'Y' && $value->pay_type == 'online'){
                        $value->button  = [];
                    }

                }

            }
//            dd($data['info']->toArray());
            $msg['code']=200;
            $msg['msg']="数据拉取成功";
            $msg['data']=$data;
            return $msg;
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
     *  /tms/dispatch/liftOrder
     * */
    public function liftOrder(Request $request){

    }

    /*
     * 顺风车调度 /tms/dispatch/liftDispatch
     * */
    public function liftDispatch(Request $request,Tms $tms){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_carriage';

        $operationing->access_cause     ='创建调度';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';

        $input              =$request->all();

        /** 接收数据*/
        $dispatch_list      =$request->input('dispatch_list');
        $company_id         =$request->input('company_id'); //承运公司
        $carriage_flag      =$request->input('carriage_flag');
        $group_code      	=$request->input('group_code');
        $total_price 		= $request->input('total_price');//应付费用
        $car_info 			= $request->input('car_info');//司机车辆信息
        /*** 虚拟数据
        $input['dispatch_list']     =$dispatch_list='dispatch_202101311523511804451337,dispatch_202101311514279957651368';
        //        $input['company_id']        =$company_id='company_202012291153523141320375'; //
        $input['carriage_flag']        =$carriage_flag='driver';  // 自己oneself，个体司机driver，承运商carriers 组合compose'
        $input['car_info']         =$car_info = [['car_id'=>'','type'=>'lease','car_number'=>'沪V12784','contacts'=>'刘伯温','tel'=>'19819819819','price'=>500,'company_id'='company_202102241023099922958143'],
        ['car_id'=>'','type'=>'oneself','car_number'=>'沪V44561','contacts'=>'赵匡胤','tel'=>'16868686868','price'=>100,'company_id'='']];
        $input['group_code'] = $group_code = '1234';
        $input['total_price'] = $total_price = 200;
         * ***/
        $rules=[
            'dispatch_list'=>'required',
        ];
        $message=[
            'dispatch_list.required'=>'必须选择最少一个可调度单',
        ];
        $where_check=[
            ['delete_flag','=','Y'],
            ['self_id','=',$group_code],
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $group_info= SystemGroup::where($where_check)->select('self_id','group_code','group_name')->first();

            $company_info = TmsGroup::where('self_id','=',$company_id)->select('self_id','company_name')->first();
            if ($company_id){
                if(empty($company_info)){
                    $msg['code'] = 303;
                    $msg['msg'] = '业务公司不存在';
                    return $msg;
                }
            }

            $where=[
                ['delete_flag','=','Y'],
            ];
            $select=['self_id','company_name','create_time','create_time','group_name','dispatch_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address',
                'send_sheng_name','send_shi_name','send_qu_name','send_address','carpool',
                'good_info','good_number','good_weight','good_volume'];
            $select1 = ['order_id'];
            $wait_info=TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$dispatch_list))->select($select)->get();
            foreach($wait_info as $key => $value){
                   if ($value->carpool == 'N' && count($wait_info) > 1){
                       $msg['code'] = 304;
                       $msg['msg'] = '订单：'.$value->self_id.'不接受拼车，请重新调度';
                       return $msg;
                   }
            }
            $orderList=TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$dispatch_list))->select($select1)->get();
            if ($orderList){
                $id_list = array_column($orderList->toArray(),'order_id');
                $tmsOrder['order_status'] = 4;
                $tmsOrder['update_time']  = $now_time;
                TmsOrder::whereIn('self_id',$id_list)->update($tmsOrder);
            }

            if(empty($wait_info)){
                $msg['code'] = 304;
                $msg['msg'] = '您选择的调度单为空';
                return $msg;
            }

            $datalist= $order_info = $order_money = $settle_money =[];       //初始化数组为空
            $cando='Y';         //错误数据的标记
            $strs='';           //错误提示的信息拼接  当有错误信息的时候，将$cando设定为N，就是不允许执行数据库操作
            $abcd=0;            //初始化为0     当有错误则加1，页面显示的错误条数不能超过$errorNum 防止页面显示不全1
            $errorNum=50;       //控制错误数据的条数
            $a=1;

            $carriage_id            =generate_id('carriage_');
            foreach ($wait_info as $k => $v){
                if($v ->dispatch_flag == 'N' ){
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."行不可以调度".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }else{
                    $list['self_id']            =generate_id('c_d_');
                    $list['order_dispatch_id']        = $v->self_id;
                    $list['carriage_id']        = $carriage_id;
                    $list['group_code']         = $group_info->group_code;
                    $list['group_name']         = $group_info->group_name;
                    $list['create_user_id']     = $user_info->admin_id;
                    $list['create_user_name']   = $user_info->name;
                    $list['create_time']        =$list['update_time']=$now_time;
                    if($company_id){
                        $list['company_id']         = $company_info->self_id;
                        $list['company_name']         = $company_info->company_name;
                    }

                    $datalist[]=$list;
                }
                  $a++;
            }
            if ($carriage_flag == 'oneself' || $carriage_flag == 'driver'){
                foreach ($car_info as $key => $value){
                    $order_list['self_id']            =generate_id('driver_');
                    $order_list['carriage_id']        = $carriage_id;
                    $order_list['group_code']         = $group_info->group_code;
                    $order_list['group_name']         = $group_info->group_name;
                    $order_list['create_user_id']     = $user_info->admin_id;
                    $order_list['create_user_name']   = $user_info->name;
                    $order_list['create_time']        =$order_list['update_time']=$now_time;
                    $order_list['car_id']   = $value['car_id'];
                    $car_list_info = $tms->get_car($value['car_id'],$value['car_number'],$value['type'],$group_info,$user_info,$now_time);
                    $order_list['car_id']   = $car_list_info->self_id;
                    if($company_id) {
                        $order_list['company_id'] = $company_info->self_id;
                        $order_list['company_name'] = $company_info->company_name;
                    }
                    $order_list['car_number']   =  $car_list_info->car_number;
                    $order_list['contacts']   =  $value['contacts'];
                    $order_list['tel']   = $value['tel'];
                    $order_list['price'] = $value['price']*100;
                    $order_info[]=$order_list;
                    $car_list[] = $car_list_info->car_possess;

                    $money['self_id']                    = generate_id('order_money_');
                    $money['shouk_driver_id']            = $order_list['self_id'];
                    $money['shouk_type']                 = 'DRIVER';
                    $money['fk_group_code']              = $group_info->group_code;
                    $money['fk_type']                    = 'GROUP_CODE';
                    $money['ZIJ_group_code']             = $group_info->group_code;
                    $money['carriage_id']                = $carriage_id;
                    $money['create_time']                = $now_time;
                    $money['update_time']                = $now_time;
                    $money['money']                      = $value['price']*100;
                    $money['money_type']                 = 'freight';
                    $money['type']                       = 'out';
                    $money['driver_id']                  = $order_list['self_id'];
                    $money['carriage_id']                = $carriage_id;
                    $order_money[] = $money;

                }
            }
            if($cando == 'N'){
                $msg['code'] = 306;
                $msg['msg'] = $strs;
                return $msg;
            }

            $data['self_id']            = $carriage_id;
            $data['create_user_id']     = $user_info->admin_id;
            $data['create_user_name']   = $user_info->name;
            $data['create_time']        = $data['update_time']=$now_time;
            $data['group_code']         = $group_info->group_code;
            $data['group_name']         = $group_info->group_name;
            $data['total_money']        = $total_price*100;
            $data['order_status']       = 2;
            if($company_id){
                $data['company_id']         = $company_info->self_id;
                $data['company_name']       = $company_info->company_name;
                $data['order_status']       = 1;

                $money['self_id']                    = generate_id('order_money_');
                $money['shouk_company_id']           = $company_info->self_id;
                $money['shouk_type']                 = 'COMPANY';
                $money['fk_group_code']              = $group_info->group_code;
                $money['fk_type']                    = 'GROUP_CODE';
                $money['ZIJ_group_code']             = $group_info->group_code;
                $money['carriage_id']                = $carriage_id;
                $money['create_time']                = $now_time;
                $money['update_time']                = $now_time;
                $money['money']                      = $total_price*100;
                $money['money_type']                 = 'freight';
                $money['type']                       = 'out';
                $order_money[] = $money;
            }

            if ($carriage_flag == 'oneself' || $carriage_flag == 'driver'){
                $count_car = array_unique($car_list);
                if ($count_car >1){
                    $data['carriage_flag']   =  'compose';
                }
            }else{
                $data['carriage_flag']       =  $carriage_flag;
            }
            $id=TmsCarriage::insert($data);
            TmsCarriageDispatch::insert($datalist);
            TmsOrderCost::insert($order_money);
            if ($carriage_flag == 'oneself' || $carriage_flag == 'driver'){
                TmsCarriageDriver::insert($order_info);
            }


            $data_update['dispatch_flag']      ='N';
            $data_update['order_status']       = 4;
            $data_update['update_time']        =$now_time;
            TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$dispatch_list))->update($data_update);


            $operationing->table_id=$carriage_id;
            $operationing->old_info=null;
            $operationing->new_info=$data;

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
     * 可调度顺风车列表 /tms/dispatch/liftPage
     * */
    public function liftPage(Request $request){
        $pay_status     =config('tms.tms_order_status_type');
        $dispatch_status     =config('tms.tms_dispatch_type');
        $online_status     =config('tms.tms_online_type');
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数


        $carriage_flag    =array_column(config('tms.carriage_flag'),'name','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        $tms_order_inco_type         =array_column(config('tms.tms_order_inco_type'),'icon','key');
        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $group_code     =$request->input('group_code');
        $state          =$request->input('order_status');
        $on_line_flag   =$request->input('online_flag');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'all','name'=>'use_flag','value'=>$use_flag],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
            ['type'=>'=','name'=>'order_status','value'=>$state],
            ['type'=>'=','name'=>'on_line_flag','value'=>$on_line_flag],
            ['type'=>'=','name'=>'order_type','value'=>'lift'],
        ];


        $where=$where1 =get_list_where($search);

        $select=['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
            ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','receiver_id','total_user_id','receipt_flag'];
        $selectUser = ['self_id','tel'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsOrderDispatch::where($where)->count(); //总的数据量
                $data['items']=TmsOrderDispatch::with(['userReg' => function($query)use($select,$selectUser){
                    $query->where('delete_flag','=','Y');
                    $query->select($selectUser);
                }])
                    ->where($where);
                $data['items'] = $data['items']
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where1[]=['receiver_id','=',$group_info['group_code']];
                $where[] = ['group_code','=',$group_info['group_code']];
                $data['total']=TmsOrderDispatch::where($where)->orWhere($where1)->count(); //总的数据量
                $data['items']=TmsOrderDispatch::with(['userReg' => function($query)use($select,$selectUser){
                    $query->where('delete_flag','=','Y');
                    $query->select($selectUser);
                }])
                    ->where($where)->orWhere($where1);

                $data['items'] = $data['items']
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsOrderDispatch::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsOrderDispatch::with(['userReg' => function($query)use($select,$selectUser){
                    $query->where('delete_flag','=','Y');
                    $query->select($selectUser);
                }])
                    ->where($where)->whereIn('group_code',$group_info['group_code']);
                $data['items'] = $data['items']
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
        foreach ($button_info as $k => $v){
            if($v->id == 643){
                $button_info1[]=$v;
            }
            if($v->id == 644){
                $button_info2[]=$v;
            }
            if ($v->id == 765){
                $button_info4[] = $v;
            }
            if ($v->id == 766){
                $button_info5[] = $v;
            }
            if($v->id ==  764){
                $button_info1[]=$v;
                $button_info2[]=$v;
                $button_info3[] = $v;
                $button_info4[] = $v;
                $button_info5[] = $v;
            }


        }
//        dd($button_info1,$button_info2,$button_info3);
        foreach ($data['items'] as $k=>$v) {
            $v->button_info=$button_info;
            $v->order_type_show=$tms_order_type[$v->order_type]??null;
            $v->type_inco = img_for($tms_order_inco_type[$v->order_type],'no_json')??null;
            $v->pay_status_color=$pay_status[$v->order_status-1]['pay_status_color']??null;
            $v->pay_status_text=$pay_status[$v->order_status-1]['pay_status_text']??null;
            if ($v->dispatch_flag == 'Y'){
                $v->dispatch_use_flag = 1;
            }else{
                $v->dispatch_use_flag = 2;
            }
            $v->dispatch_status_color=$dispatch_status[$v->dispatch_use_flag-1]['dispatch_status_color']??null;
            $v->dispatch_status_text=$dispatch_status[$v->dispatch_use_flag-1]['name']??null;
            if ($v->on_line_flag == 'Y'){
                $v->on_line_use_flag = 1;
            }else{
                $v->on_line_use_flag = 2;
            }
            $v->online_status_color=$online_status[$v->on_line_use_flag-1]['online_status_color']??null;
            $v->online_status_text=$online_status[$v->on_line_use_flag-1]['name']??null;
            $v->carriage_flag_show=$carriage_flag[$v->carriage_flag]??null;

            if($v->dispatch_flag =='N' && $v->on_line_flag =='Y' && $v->order_status != 2){
                $v->button_info=$button_info1;
            }elseif($v->dispatch_flag =='Y' && $v->on_line_flag =='N' && ($v->order_status == 2 || $v->order_status == 1)){
                $v->button_info=$button_info2;
            }elseif($v->dispatch_flag =='N' && $v->on_line_flag =='N'){
                $v->button_info=$button_info3;
            }else{
                $v->button_info=$button_info2;
            }
//            dd($button_info4);
            if ($v->order_status == 4){
                $v->button_info = $button_info4;
            }
            if ($v->order_status == 5 && $v->receipt_flag == 'N'){
                $v->button_info = $button_info5;
            }
            $v->total_money = number_format($v->total_money/100,2);
            $v->on_line_money = number_format($v->on_line_money/100,2);

            if (empty($v->total_user_id)){
                $v->object_show = $v->group_name;
            }else{
                $v->object_show = $v->userReg->tel;
            }

        }
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;

        return $msg;
    }

    /**
     * 顺风车订单列表list  /tms/dispatch/liftList
     * */
    public function liftList(Request $request){
        $group_info             = $request->get('group_info');
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');
        $order_state_type        =config('tms.3pl_dispatch_state');
        $data['state_info']       =$order_state_type;

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 快捷订单接单列表
     * */
    public function dispatchFastOrderPage(Request $request){
        $group_info             = $request->get('group_info');
        $buttonInfo             = $request->get('buttonInfo');
//        dd($buttonInfo);
        $tms_order_status_type = array_column(config('tms.tms_order_status_type'),'pay_status_text','key');
        $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        $tms_order_inco_type         =array_column(config('tms.tms_order_inco_type'),'icon','key');
        $order_status    = $request->get('status');//状态值 后端调用固传 status = 1;
        /** 接收数据*/
        $input          =$request->all();
        $group_code     =$request->input('group_code');
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;
        $app_status = $request->input('app_status');
//        $input['group_code'] = $group_code =  '1234';
        $rules=[
            'group_code'=>'required',
        ];
        $message=[
            'group_code.required'=>'请选择业务公司',
        ];
//        $order_status = 2;
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['receiver_id','=',$group_code],
                ['order_type','!=','lift'],
            ];
            $select=['self_id','receiver_id','create_time','create_time','group_name','group_code','order_type',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','order_status','receipt_flag','remark',
                'send_sheng_name','send_shi_name','send_qu_name','send_address','clod','total_user_id','delete_flag','total_money',
                'good_info','good_number','good_weight','good_volume','total_money','send_time','gather_time','pay_type','dispatch_flag'];

            $data['info']=TmsLittleOrder::where($where);
            if ($order_status){
                if ($order_status == 1){
                    if ($app_status){
                        $data['info'] = $data['info']->whereIn('order_status',[2,3]);
                    }else{
                        $data['info'] = $data['info']->where('dispatch_flag','Y')->whereIn('order_status',[2,3]);
                    }
                }elseif($order_status == 2){
                    $data['info'] = $data['info']->whereIn('order_status',[4,5]);
                }else{
                    $data['info'] = $data['info']->where('order_status',6);
                }
            }

            $data['info'] = $data['info']
                ->select($select)
                ->offset($firstrow)
                ->limit($listrows)
                ->orderBy('update_time','DESC')->get(); //总的数据量
            $data['total'] = TmsLittleOrder::where($where)->where('dispatch_flag','Y')->whereIn('order_status',[2,3])->count();
            foreach ($data['info'] as $key => $value){
//                dd($value);
                $value->self_id_show = substr($value->self_id,15);
                $value->total_money       = number_format($value->total_money/100, 2);
                $value->order_type_show=$tms_order_type[$value->order_type]??null;
                $value->order_status_show = $tms_order_status_type[$value->order_status] ?? null;
                $value->type_inco = img_for($tms_order_inco_type[$value->order_type],'no_json')??null;
                $value->send_time         = date('m-d H:i',strtotime($value->send_time));
                $value['good_info'] = json_decode($value['good_info'],true);

                $temperture = json_decode($value['clod'],true);
                foreach ($temperture as $kkk => $vvv){
                    $temperture[$kkk] = $tms_control_type[$vvv];
                }
                $value->temperture = implode(',',$temperture);

                if($value->order_type == 'vehicle' || $value->order_type == 'lift'){
                    $value->picktime_show = '装车时间 '.$value->send_time;
                }else{
                    $value->picktime_show = '提货时间 '.$value->send_time;
                }

                $value->temperture_show ='温度 '.$value->temperture;
                $value->order_id_show = '订单编号'.substr($value->self_id,15);
                if ($value->order_status == 1){
                    $value->state_font_color = '#333';
                }elseif($value->order_status == 2){
                    $value->state_font_color = '#333';
                }elseif($value->order_status == 3){
                    $value->state_font_color = '#0088F4';
                }elseif($value->order_status == 4){
                    $value->state_font_color = '#35B85F';
                }elseif($value->order_status == 5){
                    $value->state_font_color = '#35B85F';
                }elseif($value->order_status == 6){
                    $value->state_font_color = '#FF9400';
                }else{
                    $value->state_font_color = '#FF807D';
                }
                if($value->order_type == 'vehicle' || $value->order_type == 'lcl' || $value->order_type == 'lift'){
                    $value->order_type_color = '#E4F3FF';
                    $value->order_type_font_color = '#0088F4';
                    if ($value->order_type == 'vehicle'){
                        $value->order_type_color = '#0088F4';
                        $value->order_type_font_color = '#FFFFFF';
                    }

                }else{
                    $value->good_info_show = '货物 '.$value->good_number.'件'.$value->good_weight.'kg'.$value->good_volume.'方';
                    $value->order_type_color = '#E4F3FF';
                    $value->order_type_font_color = '#0088F4';
                }
                $button1 = [];
                $button2 = [];
                $button3 = [];
                $button4 = [];
                $button5 = [];
                $button6 = [];
                $button7 = [];
                foreach ($buttonInfo as $kk =>$vv){
                    if ($vv->id == 129){
                        $button1[] = $vv;
                    }
                    if ($vv->id == 128){
                        $button1[] = $vv;
                        $button3[] = $vv;
                    }
                    if ($vv->id == 125){
                        $button2[] = $vv;
                    }
                    if ($vv->id == 126){
                        $button2[] = $vv;
                    }
                    if ($vv->id == 127){
                        $button4[] = $vv;
                        $button6[] = $vv;
                    }
                    if ($vv->id == 143){
                        $button5[] = $vv;
                    }
                    if ($vv->id == 145){
                        $button3[] = $vv;
                    }
                    if ($vv->id == 228){
                        $button6[] = $vv;
                        $button7[] = $vv;
                    }


                    if ($value->order_status == 3){
                        if ($value->order_type == 'line'){
                            if ($value->group_code == $value->receiver_id || $value->receiver_id == $value->total_user_id){
                                $value->button  = $button3;
                            }else{
                                $value->button  = $button1;
                            }
                        }else{
                            $value->button  = $button3;
                        }

                    }
                    if ($value->order_status == 4){
                        $value->button  = $button2;
                    }
                    if ($value->order_status == 5 && $value->receipt_flag == 'N'){
                        $value->button  = $button4;
                    }
                    if ($value->order_status == 5 && $value->receipt_flag == 'N' && $value->pay_type == 'offline' && $value->pay_status == 'N' && $value->group_code != $value->receiver_id){
                        $value->button = $button6;
                    }
                    if ($value->order_status == 5 && $value->receipt_flag == 'Y' && $value->pay_type == 'offline' && $value->pay_status == 'N' && $value->group_code != $value->receiver_id){
                        $value->button = $button7;
                    }
                    if ($value->order_status == 6 && $value->receipt_flag == 'N'){
                        $value->button  = $button4;
                    }
                    if ($value->order_status == 6  && $value->pay_type == 'offline' && $value->pay_status == 'N' && $value->receipt_flag == 'N' && $value->group_code != $value->receiver_id){
                        $value->button = $button7;
                    }
//                    if ($value->receipt_flag == 'Y'){
//                        $value->button  = $button5;
//                    }
                    if ($value->receipt_flag == 'Y' && $value->pay_type == 'online'){
                        $value->button  = [];
                    }

                }

            }
//            dd($data['info']->toArray());
            $msg['code']=200;
            $msg['msg']="数据拉取成功";
            $msg['data']=$data;
            return $msg;
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
     * 快捷接单
     * */
    public function addDispatchFastOrder(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_little_order';
//        dd($user_info);
        $operationing->access_cause     ='接单';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';

        $input              =$request->all();

        /** 接收数据*/
        $dispatch_id         =$request->input('dispatch_id'); //调度单ID
        /*** 虚拟数据
        $input['dispatch_id']     =$dispatch_id='dispatch_202102021623135577267482';
         * ***/
        $rules=[
            'dispatch_id'=>'required',
        ];
        $message=[
            'dispatch_id.required'=>'必须选择最少一个可调度单',
        ];


        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];
            $select=['self_id','order_status','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','group_code',
                'send_sheng_name','send_shi_name','send_qu_name','send_address',
                'good_info','good_number','good_weight','good_volume'];
            $wait_info=TmsLittleOrder::where($where)->select($select)->first(); //总的数据量
//            dd($wait_info->order_id);
            if(empty($wait_info)){
                $msg['code'] = 304;
                $msg['msg'] = '您选择的订单已被承接';
                return $msg;
            }
            if ($wait_info->group_code == $user_info->group_code){
                $msg['code'] = 305;
                $msg['msg'] = '不可以接取自己的订单';
                return $msg;
            }

            $update['receiver_id']      =$user_info->group_code;
            $update['dispatch_flag']      ='Y';
            $update['on_line_flag']       ='N';
            $update['order_status']       = 3;
            $update['update_time']        =$now_time;
            $id = TmsLittleOrder::where($where)->update($update);

            /*** 保存应收及应付对象**/
//            if ($wait_info->pay_type == 'online'){
//                $money['shouk_group_code']              = $user_info->group_code;
//                $money['shouk_type']                    = 'GROUP_CODE';
//                $money['fk_group_code']                 = '1234';
//                $money['fk_type']                       = 'PLATFORM';
//                $money['ZIJ_group_code']                = '1234';
//                $money['order_id']                      = $wait_info->order_id;
//                $money['create_time']                   = $now_time;
//                $money['update_time']                   = $now_time;
//                $money['money']                         = $wait_info->on_line_money*100;
//                $money['money_type']                    = 'freight';
//                $money['type']                          = 'out';
//                TmsOrderCost::insert($money);
//            }else{
//                $money_where = [
//                    ['order_id','=',$wait_info->order_id],
//                    ['delete_flag','=','Y'],
//
//                ];
//                $money['shouk_group_code']              = $user_info->group_code;
//                $money['shouk_type']                    = 'GROUP_CODE';
//                TmsOrderCost::where($money_where)->update($money);
//            }

            $operationing->table_id=$dispatch_id;
            $operationing->old_info=null;
            $operationing->new_info=(object)$update;

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
     * 快捷接单详情
     * */
    public function dispatchFastOrderDetails(Request $request,TMS $tms){
        $tms_order_status_type = array_column(config('tms.tms_order_status_type'),'pay_status_text','key');
        $self_id=$request->input('self_id');
        $table_name='tms_little_order';
        $select=['self_id','create_time','use_flag','delete_flag','group_code','group_name','order_type','order_status','gather_name','remark',
            'gather_tel','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_name','send_tel','send_sheng_name',
            'send_shi_name','send_qu_name','info','kilometre', 'send_address','total_money','good_info','good_number','good_weight','good_volume',
            'dispatch_flag','on_line_flag', 'clod','total_money','gather_time','send_time','receiver_id','total_user_id','pay_type'];

        $where = [
            ['self_id','=',$self_id],
        ];

        $info = TmsLittleOrder::where($where)->select($select)->first();
        if($info){
            $tms_order_type        =array_column(config('tms.tms_order_type'),'name','key');
            $tms_control_type        =array_column(config('tms.tms_control_type'),'name','key');
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $info->total_money=$info->total_money/100;

            $info->clod=json_decode($info->clod,true);
            $info->order_type_show=$tms_order_type[$info->order_type];
            $info->order_status_show = $tms_order_status_type[$info->order_status] ?? null;
            $info->self_id_show = substr($info->self_id,15);
            $info->driver_price = 0;
            $cold = $info->clod;
            foreach ($cold as $key => $value){
                $cold[$key] =$tms_control_type[$value];
            }
            $info->clod= $cold;
            $info->info = json_decode($info->info,true);
            /** 对商品信息进行处理*/

            $good_info=json_decode($info->good_info,true);
            $info->good_info_show=$good_info;

            $car_list = [];

            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl' || $info->order_type == 'lift'){
                $order_info = $info->info;
                foreach ($order_info as $kkk => $vvv){
                    $order_info[$kkk]['good_weight'] = ($vvv['good_weight']/1000).'吨';
                }
                $info->info = $order_info;
                $info->good_weight = ($info->good_weight/1000).'吨';
            }
            $info->color = '#FF7A1A';
            $info->order_id_show = '订单编号'.$info->self_id_show;
            $order_details = [];
            $receipt_list = [];
            $car_info = [];
            $order_details1['name'] = '应收运费';
            $order_details1['value'] = '¥'.$info->total_money;
            $order_details1['color'] = '#FF7A1A';
            $order_details2['name'] = '里程';
            $order_details2['value'] = $info->kilometre.'km';
            $order_details2['color'] = '#FF7A1A';

            $order_details4['name'] = '收货时间';
            $order_details4['value'] = $info->gather_time;
            $order_details4['color'] = '#000000';

            $order_details3['name'] = '装车时间';
            $order_details3['value'] = $info->send_time;
            $order_details3['color'] = '#000000';

            $order_details6['name'] = '订单备注';
            $order_details6['value'] = $info->remark;
            $order_details6['color'] = '#000000';

            $order_details9['name'] = '运输信息';
            $order_details9['value'] = $info->car_info;

            $order_details10['name'] = '回单信息';
            $order_details10['value'] = $info->receipt;

            $order_details11['name'] = '应付司机';
            $order_details11['value'] = '¥'.$info->driver_price;
            $order_details11['color'] = '#000000';

            $order_details[] = $order_details1;
//            $order_details[]= $order_details2;
            if(!empty($info->tmsCarriage)){
                $car_info[] = $order_details11;
            }
            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl' || $info->order_type == 'lift'){
                if($info->kilometre){
                    $order_details[] = $order_details2;
                }
                $order_details[] = $order_details3;
                $order_details[]= $order_details4;
                $order_details[]= $order_details6;
            }else{
                $order_details[]= $order_details6;
            }
            if(!empty($info->car_info)){
                $car_info[] = $order_details9;
            }
            if (!empty($info->receipt)){
                $receipt_list[] = $order_details10;
            }

            $data['info']=$info;
            $data['order_details'] = $order_details;
            $data['receipt_list'] = $receipt_list;
            $data['car_info'] = $car_info;
            $log_flag='N';
            $data['log_flag']=$log_flag;
            $log_num='10';
            $data['log_num']=$log_num;
            $data['log_data']=null;

            $msg['code']=200;
            $msg['msg']="数据拉取成功";
            $msg['data']=$data;
            return $msg;
        }else{
            $msg['code']=300;
            $msg['msg']="没有查询到数据";
            return $msg;
        }
    }
        /**
         * 极速版取消接单
         * */
        public function dispatchOrderCancel(Request $request){
            $now_time=date('Y-m-d H:i:s',time());
            $operationing = $request->get('operationing');//接收中间件产生的参数
            $table_name='tms_little_order';
            $self_id=$request->input('self_id');
            $operationing->access_cause='取消接单';
            $operationing->table = $table_name;
            $operationing->table_id = $self_id;
            $operationing->now_time = $now_time;

//        $self_id='dispatch_202101161343430556992404';
            $where=[
                ['delete_flag','=','Y'],
                ['use_flag','=','Y'],
                ['self_id','=',$self_id]
            ];
            $select=['self_id','group_code','order_type','order_status','total_money','dispatch_flag', 'send_address_latitude', 'on_line_flag',];
            $order_info = TmsLittleOrder::where($where)->select($select)->first();
            if($order_info->order_status == 2){
                $msg['code']=303;
                $msg['msg']='已取消，请勿重复操作';
                return $msg;
            }
//        dd($order_info);
            $old_info = [
                'on_line_flag'=>$order_info->on_line_flag,
                'update_time'=>$now_time
            ];
            $data['on_line_flag'] = 'Y';
            $data['dispatch_flag'] = 'N';
            $data['order_status']  = 2;
            $data['receiver_id'] = null;
            $data['update_time'] = $now_time;
            $id=TmsLittleOrder::where($where)->update($data);


            /** 修改订单费用中的应收对象 **/
//            $money_where = [
//                ['delete_flag','=','Y'],
//                ['use_flag','=','Y'],
//                ['dispatch_id','=',$self_id],
//                ['shouk_type','=','GROUP_CODE'],
//                ['order_id','=',$order_info->order_id]
//
//            ];
//            $money_update['shouk_group_code'] = null;
//            $money_update['shouk_type']       = null;
//            $money_update['update_time']      = $now_time;
//            TmsOrderCost::where($money_where)->update($money_update);

            $operationing->old_info = (object)$old_info;
            $operationing->table_id = $self_id;
            $operationing->new_info=$data;

            if($id){
                $msg['code'] = 200;
                $msg['msg'] = "操作成功";
                return $msg;
            }else{
                $msg['code'] = 302;
                $msg['msg'] = "操作失败";
                return $msg;
            }
        }

        /**
         * 快速下单确认订单送达
         * */
    public function fastOrderDone(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_littlle_dispatch';
//        dd($user_info);
        $operationing->access_cause     ='确认送达';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';

        $input              =$request->all();

        /** 接收数据*/
        $dispatch_id        = $request->input('dispatch_id'); //调度单ID
        /*** 虚拟数据
        $input['dispatch_id']     =$dispatch_id='patch_202105091028035791605429';
         * ***/
        $rules=[
            'dispatch_id'=>'required',
        ];
        $message=[
            'dispatch_id.required'=>'请选择运输订单',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];

            $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','order_id','receiver_id','group_code',
                'send_sheng_name','send_shi_name','send_qu_name','send_address','order_status',
                'good_info','good_number','good_weight','good_volume','total_money'];

            $wait_info = TmsLittleOrder::where($where)->select($select)->first();
            if ($wait_info->order_status == 5){
                $msg['code'] = 301;
                $msg['msg'] = "已确认货物送达，请勿重复操作";
                return $msg;
            }
            if($wait_info->group_code == $wait_info->receiver_id){
                $receipt = TmsReceipt::where('order_id',$dispatch_id)->count();
                if ($receipt = 0){
                    $msg['code'] = 302;
                    $msg['msg'] = "请上传回单！";
                    return $msg;
                }
            }
            //调度订单修改订单状态
            $order_where = [
                ['self_id','=',$wait_info->order_id]
            ];
            $dispatch_where = [
                ['order_id','=',$wait_info->order_id]
            ];
            $flag = 'Y';
            //判断是否所有的运输单状态是否都为调度，是修改订单状态为已接单
            $tmsOrderDispatch = TmsOrderDispatch::where($dispatch_where)->select(['self_id'])->get();

            if ($tmsOrderDispatch){
                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
                $orderStatus = TmsOrderDispatch::where('self_id','!=',$dispatch_id)->whereIn('self_id',$dispatch_list)->select(['order_status'])->get();
                $arr = array_unique(array_column($orderStatus->toArray(),'order_status'));
//                dump($arr);
                if (count($arr) >= 1){
                    if (implode('',$arr) == 5){
                        $flag = 'Y';
                    }else{
                        $flag = 'N';
                    }
                }else{
                    $flag = 'Y';
                }
            }
            //修改当前运输单状态

            $dispatch_order['update_time']   = $now_time;

            if ($wait_info->group_code == $wait_info->receiver_id){
                $order_update['order_status'] = 6;
                $dispatch_order['order_status']  = 6;
            }else{
                $order_update['order_status'] = 5;
                $dispatch_order['order_status']  = 5;
            }
            $order_update['update_time']  = $now_time;
            $id = TmsLittleDispatch::where($where)->update($dispatch_order);


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
     * 极速版快捷订单调度
     * */
    public function addFastDispatch(Request $request,TMS $tms){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_carriage';

        $operationing->access_cause     ='创建调度';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';

        $input              =$request->all();

        /** 接收数据*/
        $dispatch_list      =$request->input('dispatch_list');
        $company_id         =$request->input('company_id'); //承运公司
        $carriage_flag      =$request->input('carriage_flag');
        $group_code      	=$request->input('group_code');
        $total_price 		= $request->input('total_price');//应付费用
        $car_info 			= $request->input('car_info');//司机车辆信息
        /*** 虚拟数据
        $input['dispatch_list']     =$dispatch_list='dispatch_202101311523511804451337,dispatch_202101311514279957651368';
        //        $input['company_id']        =$company_id='company_202012291153523141320375'; //
        $input['carriage_flag']        =$carriage_flag='driver';  // 自己oneself，个体司机driver，承运商carriers 组合compose'
        $input['car_info']         =$car_info = [['car_id'=>'','type'=>'lease','car_number'=>'沪V12784','contacts'=>'刘伯温','tel'=>'19819819819','price'=>500,'company_id'='company_202102241023099922958143'],
        ['car_id'=>'','type'=>'oneself','car_number'=>'沪V44561','contacts'=>'赵匡胤','tel'=>'16868686868','price'=>100,'company_id'='']];
        $input['group_code'] = $group_code = '1234';
        $input['total_price'] = $total_price = 200;
         * ***/
        $rules=[
            'dispatch_list'=>'required',
        ];
        $message=[
            'dispatch_list.required'=>'必须选择最少一个可调度单',
        ];
        $where_check=[
            ['delete_flag','=','Y'],
            ['self_id','=',$group_code],
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $group_info= SystemGroup::where($where_check)->select('self_id','group_code','group_name')->first();

            $company_info = TmsGroup::where('self_id','=',$company_id)->select('self_id','company_name')->first();
            $msg['code'] = 303;
            $msg['msg'] = $company_info;
            return $msg;
            if ($company_id){
                if(!(array)$company_info){
                    $msg['code'] = 303;
                    $msg['msg'] = '请选择承运公司';
                    return $msg;
                }
            }

            $where=[
                ['delete_flag','=','Y'],
            ];
            $select=['self_id','company_name','create_time','create_time','group_name','dispatch_flag','gather_sheng_name','gather_shi_name',
                'gather_qu_name','gather_address','send_sheng_name','send_shi_name','send_qu_name','send_address', 'good_info',
                'good_number','good_weight','good_volume'];
            $select1 = ['self_id'];
            $wait_info=TmsLittleOrder::where($where)->whereIn('self_id',explode(',',$dispatch_list))->select($select)->get();
            $orderList=TmsLittleOrder::where($where)->whereIn('self_id',explode(',',$dispatch_list))->select($select1)->get();
            if ($orderList){
                $id_list = array_column($orderList->toArray(),'order_id');
                $tmsOrder['order_status'] = 4;
                $tmsOrder['update_time']  = $now_time;
                TmsLittleOrder::whereIn('self_id',$id_list)->update($tmsOrder);
            }

            if(empty($wait_info)){
                $msg['code'] = 304;
                $msg['msg'] = '您选择的调度单为空';
                return $msg;
            }

            $datalist= $order_info = $order_money = $settle_money =[];       //初始化数组为空
            $cando='Y';         //错误数据的标记
            $strs='';           //错误提示的信息拼接  当有错误信息的时候，将$cando设定为N，就是不允许执行数据库操作
            $abcd=0;            //初始化为0     当有错误则加1，页面显示的错误条数不能超过$errorNum 防止页面显示不全1
            $errorNum=50;       //控制错误数据的条数
            $a=1;

            $carriage_id            =generate_id('carriage_');
            foreach ($wait_info as $k => $v){
                if($v ->dispatch_flag == 'N' ){
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."行不可以调度".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }else{
                    $list['self_id']            =generate_id('c_d_');
                    $list['order_dispatch_id']        = $v->self_id;
                    $list['carriage_id']        = $carriage_id;
                    $list['group_code']         = $group_info->group_code;
                    $list['group_name']         = $group_info->group_name;
                    $list['create_user_id']     = $user_info->admin_id;
                    $list['create_user_name']   = $user_info->name;
                    $list['create_time']        =$list['update_time']=$now_time;
                    if($company_id){
                        $list['company_id']         = $company_info->self_id;
                        $list['company_name']         = $company_info->company_name;
                    }

                    $datalist[]=$list;
                }
                $a++;
            }
            if ($carriage_flag == 'oneself' || $carriage_flag == 'driver'){
                foreach ($car_info as $key => $value){
                    $order_list['self_id']            =generate_id('driver_');
                    $order_list['carriage_id']        = $carriage_id;
                    $order_list['group_code']         = $group_info->group_code;
                    $order_list['group_name']         = $group_info->group_name;
                    $order_list['create_user_id']     = $user_info->admin_id;
                    $order_list['create_user_name']   = $user_info->name;
                    $order_list['create_time']        =$order_list['update_time']=$now_time;
                    $order_list['car_id']   = $value['car_id'];
                    $car_list_info = $tms->get_car($value['car_id'],$value['car_number'],$value['type'],$group_info,$user_info,$now_time);
                    $order_list['car_id']   = $car_list_info->self_id;
                    if($company_id) {
                        $order_list['company_id'] = $company_info->self_id;
                        $order_list['company_name'] = $company_info->company_name;
                    }
                    $order_list['car_number']   =  $car_list_info->car_number;
                    $order_list['contacts']   =  $value['contacts'];
                    $order_list['tel']   = $value['tel'];
                    $order_list['price'] = $value['price']*100;
                    $order_info[]=$order_list;
                    $car_list[] = $car_list_info->car_possess;

//                    $money['self_id']                    = generate_id('order_money_');
//                    $money['shouk_driver_id']            = $order_list['self_id'];
//                    $money['shouk_type']                 = 'DRIVER';
//                    $money['fk_group_code']              = $group_info->group_code;
//                    $money['fk_type']                    = 'GROUP_CODE';
//                    $money['ZIJ_group_code']             = $group_info->group_code;
//                    $money['carriage_id']                = $carriage_id;
//                    $money['create_time']                = $now_time;
//                    $money['update_time']                = $now_time;
//                    $money['money']                      = $value['price']*100;
//                    $money['money_type']                 = 'freight';
//                    $money['type']                       = 'out';
//                    $money['driver_id']                  = $order_list['self_id'];
//                    $money['carriage_id']                = $carriage_id;
//                    $order_money[] = $money;

                }
            }
            if($cando == 'N'){
                $msg['code'] = 306;
                $msg['msg'] = $strs;
                return $msg;
            }

            $data['self_id']            = $carriage_id;
            $data['create_user_id']     = $user_info->admin_id;
            $data['create_user_name']   = $user_info->name;
            $data['create_time']        = $data['update_time']=$now_time;
            $data['group_code']         = $group_info->group_code;
            $data['group_name']         = $group_info->group_name;
            $data['total_money']        = $total_price*100;
            $data['order_status']       = 2;
            if($company_id){
                $data['company_id']         = $company_info->self_id;
                $data['company_name']       = $company_info->company_name;
                $data['order_status']       = 1;

                $money['self_id']                    = generate_id('order_money_');
                $money['shouk_company_id']           = $company_info->self_id;
                $money['shouk_type']                 = 'COMPANY';
                $money['fk_group_code']              = $group_info->group_code;
                $money['fk_type']                    = 'GROUP_CODE';
                $money['ZIJ_group_code']             = $group_info->group_code;
                $money['carriage_id']                = $carriage_id;
                $money['create_time']                = $now_time;
                $money['update_time']                = $now_time;
                $money['money']                      = $total_price*100;
                $money['money_type']                 = 'freight';
                $money['type']                       = 'out';
                $order_money[] = $money;
            }

            if ($carriage_flag == 'oneself' || $carriage_flag == 'driver'){
                $count_car = array_unique($car_list);
                if ($count_car >1){
                    $data['carriage_flag']   =  'compose';
                }
            }else{
                $data['carriage_flag']       =  $carriage_flag;
            }
            $id=TmsCarriage::insert($data);
            TmsCarriageDispatch::insert($datalist);
            TmsOrderCost::insert($order_money);
            if ($carriage_flag == 'oneself' || $carriage_flag == 'driver'){
                TmsCarriageDriver::insert($order_info);
            }


            $data_update['dispatch_flag']      ='N';
            $data_update['order_status']       = 4;
            $data_update['update_time']        =$now_time;
            TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$dispatch_list))->update($data_update);


            $operationing->table_id=$carriage_id;
            $operationing->old_info=null;
            $operationing->new_info=$data;

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
}
?>

<?php
namespace App\Http\Admin\Tms;
use App\Models\Tms\TmsCarType;
use App\Models\Tms\TmsOrderCost;
use App\Models\Tms\TmsOrderMoney;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Tools\Import;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;
use App\Models\Tms\TmsGroup;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderDispatch;
use App\Models\Tms\TmsLine;
use App\Http\Controllers\TmsController as Tms;
class OrderController extends CommonController{

    /***    订单头部      /tms/order/orderList
     */
    public function  orderList(Request $request){
        /** 接收中间件参数**/
        $group_info             = $request->get('group_info');
        $user_info              = $request->get('user_info');
        $order_state_type        =config('tms.3pl_order_state');
        $data['state_info']       =$order_state_type;
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');
        $data['user_info']      = $user_info;
        $abc='';
        $data['import_info']    =[
            'import_text'=>'下载'.$abc.'导入示例文件',
            'import_color'=>'#FC5854',
            'import_url'=>config('aliyun.oss.url').'execl/2020-07-02/车辆导入文件范本.xlsx',
        ];

        /** 抓取可调度的订单**/
        $where['delete_flag'] = 'Y';
        $where['dispatch_flag'] = 'Y';
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


        foreach ($data['button_info'] as $k => $v){
            if($v->id == '625'){
                $v->name.='（'.$data['total'].'）';
            }
        }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;

//        dd($data);
        return $msg;
    }

    /***    费用明细分页      /tms/order/orderPage
     */
    public function orderPage(Request $request){
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
            ['type'=>'=','name'=>'company_id','value'=>$company_id],
            ['type'=>'=','name'=>'type','value'=>$type],
            ['type'=>'=','name'=>'order_status','value'=>$state],
        ];


        $where=get_list_where($search);

        $select=['self_id','group_name','company_name','create_user_name','create_time','use_flag','order_type','order_status','pay_type','pay_state','app_flag','gather_address_id',
            'gather_contacts_id','gather_name','gather_tel','gather_sheng','gather_shi','gather_qu','gather_qu_name','gather_address','send_address_id','send_contacts_id', 'send_name',
            'send_tel','send_sheng','send_shi','send_qu','send_address','total_money','total_user_id','good_name','good_number', 'good_weight', 'good_volume', 'gather_shi_name',
            'send_shi_name','send_qu_name','car_type','clod','send_time','gather_time','discuss_flag','follow_flag','line_id'];
        $select2 = ['self_id','parame_name'];
        $select3 = ['self_id','total_user_id','tel'];
        $select1 = ['self_id','carriage_id','order_dispatch_id'];
        $select5 = ['self_id','company_id','company_name','carriage_flag','total_money'];
        $select4 = ['carriage_id','car_number','contacts','tel','price','car_id'];
        $list_select=['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
            ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','line_gather_address_id','line_gather_contacts_id','line_gather_name','line_gather_tel',
            'line_gather_sheng','line_gather_shi','line_gather_qu','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name' , 'line_gather_address','remark',
            'line_gather_address_longitude','line_gather_address_latitude','line_send_address_id','line_send_contacts_id','line_send_name','line_send_tel', 'line_send_sheng','line_send_shi',
            'line_send_qu','line_send_sheng_name','line_send_shi_name','line_send_qu_name','line_send_address','line_send_address_longitude','line_send_address_latitude','clod','pick_flag','send_flag',
            'pay_type','order_id','pay_status','pay_time','receiver_type','gather_name','gather_tel','send_name','send_tel','receipt_flag','receiver_id'
        ];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsOrder::where($where)->count(); //总的数据量
                $data['items']=TmsOrder::with(['TmsCarType' => function($query) use($select2){
                    $query->select($select2);
                }])
                    ->with(['userReg' => function($query) use($select3){
                        $query->select($select3);
                    }])
                    ->with(['TmsOrderDispatch' => function($query) use($list_select,$select1,$select5,$select4){
                        $query->select($list_select);
                        $query->with(['tmsCarriageDispatch'=>function($query)use($select1,$select5,$select4){
                            $query->where('delete_flag','=','Y');
                            $query->select($select1);
                            $query->with(['tmsCarriage'=>function($query)use($select5){
                                $query->where('delete_flag','=','Y');
                                $query->select($select5);
                            }]);
                            $query->with(['tmsCarriageDriver'=>function($query)use($select4){
                                $query->where('delete_flag','=','Y');
                                $query->select($select4);
                            }]);
                        }]);
                    }])
                    ->where($where);
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
                $data['total']=TmsOrder::where($where)->count(); //总的数据量
                $data['items']=TmsOrder::with(['TmsCarType' => function($query) use($select2){
                    $query->select($select2);
                }])
                    ->with(['userReg' => function($query) use($select3){
                        $query->select($select3);
                    }])
                    ->with(['TmsOrderDispatch' => function($query) use($list_select,$select1,$select5,$select4){
                        $query->select($list_select);
                        $query->with(['tmsCarriageDispatch'=>function($query)use($select1,$select5,$select4){
                            $query->where('delete_flag','=','Y');
                            $query->select($select1);
                            $query->with(['tmsCarriage'=>function($query)use($select5){
                                $query->where('delete_flag','=','Y');
                                $query->select($select5);
                            }]);
                            $query->with(['tmsCarriageDriver'=>function($query)use($select4){
                                $query->where('delete_flag','=','Y');
                                $query->select($select4);
                            }]);
                        }]);
                    }])
                    ->where($where);
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
                $data['items']=TmsOrder::with(['TmsCarType' => function($query) use($select2){
                    $query->select($select2);
                }])
                    ->with(['userReg' => function($query) use($select3){
                        $query->select($select3);
                    }])
                    ->with(['TmsOrderDispatch' => function($query) use($list_select,$select1,$select5,$select4){
                        $query->select($list_select);
                        $query->with(['tmsCarriageDispatch'=>function($query)use($select1,$select5,$select4){
                            $query->where('delete_flag','=','Y');
                            $query->select($select1);
                            $query->with(['tmsCarriage'=>function($query)use($select5){
                                $query->where('delete_flag','=','Y');
                                $query->select($select5);
                            }]);
                            $query->with(['tmsCarriageDriver'=>function($query)use($select4){
                                $query->where('delete_flag','=','Y');
                                $query->select($select4);
                            }]);
                        }]);
                    }])
                    ->where($where);
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

//        dd($data['items']->toArray());
//        dd($tms_order_status_type);
        $button_info1=[];
        $button_info2=[];
        $button_info3 = [];
        $button_info4 = [];
        $button_info5 = [];
        $button_info6 = [];
        $button_info7 = [];
        foreach ($button_info as $k => $v){
            if($v->id == 647){
                $button_info1[]=$v;
                $button_info4[]=$v;
                $button_info5[]=$v;
                $button_info6[]=$v;
                $button_info7[]=$v;
            }
            if($v->id == 272){
                $button_info2[] = $v;
                $button_info5[] = $v;
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
        }
        foreach ($data['items'] as $k=>$v) {
            foreach ($v->TmsOrderDispatch as $kkk =>$vvv){
                $car_list = [];
                if ($vvv->tmsCarriageDispatch){
                    if ($vvv->tmsCarriageDispatch->tmsCarriageDriver){
                        foreach ($vvv->tmsCarriageDispatch->tmsCarriageDriver as $kk => $vv){
                            $carList['car_id']    = $vv->car_id;
                            $carList['car_number'] = $vv->car_number;
                            $carList['tel'] = $vv->tel;
                            $carList['contacts'] = $vv->contacts;
                            $car_list[] = $carList;
                        }
                        $v->car_info = $car_list;
                    }
                }
            }
            $v->total_money = number_format($v->total_money/100, 2);
            $v->order_status_show=$tms_order_status_type[$v->order_status]??null;
            $v->order_type_show=$tms_order_type[$v->order_type]??null;
            $v->type_inco = img_for($tms_order_inco_type[$v->order_type],'no_json')??null;
            $v->button_info=$button_info;
            if ($order_status == 1 || $order_status==2){
                $v->button_info=$button_info5;
            }else if($v->order_status == 5){
                $v->button_info=$button_info4;
            }else if($v->order_status == 6 && $v->discuss_flag == 'N'){
                $v->button_info=$button_info6;
            }else if($v->order_status == 6 && $v->follow_flag =='N'){
                $v->button_info=$button_info7;
            }else{
                $v->button_info=$button_info1;
            }
            $v->self_id_show = substr($v->self_id,15);
            $v->send_time    = date('m-d H:i',strtotime($v->send_time));
            if ($v->order_type == 'vehicle'){
                if ($v->TmsCarType){
                    $v->car_type_show = $v->TmsCarType->parame_name;
                }

            }

            $v->clod=json_decode($v->clod,true);

            $cold = $v->clod;
            foreach ($cold as $key => $value){
                $cold[$key] =$tms_control_type[$value];
            }
            $v->clod= $cold;
            if($v->order_type == 'vehicle'){
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
            if($v->order_type == 'vehicle' || $v->order_type == 'lcl'){
                $v->order_type_color = '#E4F3FF';
                $v->order_type_font_color = '#0088F4';
                if ($v->order_type == 'vehicle'){
                    $v->order_type_color = '#0088F4';
                    $v->order_type_font_color = '#FFFFFF';
                }
                if ($v->TmsCarType){
                    $v->car_type_show = $v->TmsCarType->parame_name;
                    $v->good_info_show = '车型 '.$v->car_type_show;
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
                if ($value->id == 184){
                    $button4[] = $value;
                    $button5[] = $value;
                }
                if ($value->id == 162){
                    $button6[] = $value;
                }
                if ($value->id == 236){
                    $button7[] = $value;
                }
                if ($value->id == 239){
                    $button8[] = $value;
                }
                if ($v->order_status == 3 && $v->order_type == 'line'){
                    $v->button  = $button1;
                }
                if ($v->order_status == 5){
                    $v->button  = $button2;
                }
                if ($v->order_status == 2){
                    $v->button = $button3;
                }
                if ($v->order_status == 2 && $v->order_type == 'vehicle'){
                    $v->button  = $button1;
                }
                if ($v->order_status == 5 && $v->pay_type == 'offline' && $v->pay_state == 'N' && $v->app_flag == 1){
                    $v->button  = $button4;
                }
                if ($v->order_status == 6 && $v->pay_type == 'offline' && $v->pay_state == 'N' && $v->app_flag == 1){
                    $v->button  = $button5;
                }
                if($v->order_status == 6 && $v->discuss_flag == 'N'){
                    $v->button = $button7;
                }
                if($v->order_status == 6 && $v->discuss_flag == 'Y' && $v->follow_flag == 'N'){
                    $v->button = $button8;
                }
                if ($v->order_status  == 6 && $v->pay_type == 'offline' && $v->pay_state == 'N'){
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



    /***    新建订单     /tms/order/createOrder
     */
    public function createOrder(Request $request){
        /** 接收数据*/
        $data['tms_order_type']          =config('tms.tms_order_type');
        $data['tms_control_type']          =config('tms.tms_control_type');
        $data['tms_pick_type']              =config('tms.tms_pick_type');
        $data['tms_send_type']              =config('tms.tms_send_type');
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $self_id=$request->input('self_id');
        $where=[
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select=['self_id','company_id','group_code','info','pick_flag','send_flag','price','more_money','total_money','pick_money','send_money','order_type','line_id'];
        $detail = TmsOrder::where($where)->select($select)->first();
        if ($detail) {
            $detail->info =json_decode($detail->info,true);

            $detail->price      = $detail->price ? number_format($detail->price/100, 2) : '';
            $detail->more_money = $detail->more_money ? number_format($detail->more_money/100, 2) : '';
            $detail->pick_money = $detail->pick_money ? number_format($detail->pick_money/100, 2) : '';
            $detail->send_money = $detail->send_money ? number_format($detail->send_money/100, 2) : '';
        }
        $data['info']= $detail;
        $msg['user'] = $user_info->type;
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        // dd($msg);
        return $msg;


    }


    /***    新建订单数据提交      /tms/order/addOrder
     */
    public function addOrder(Request $request,Tms $tms){
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_order';

        $operationing->access_cause     ='创建/修改订单';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $input                          =$request->all();

//        /** 接收数据*/
        $self_id       = $request->input('self_id');
        $group_code    = $request->input('group_code');
        $company_id    = $request->input('company_id');
        $order_type    = $request->input('order_type');
        $line_id       = $request->input('line_id');
        $pick_flag     = $request->input('pick_flag');
        $send_flag     = $request->input('send_flag');
        $pick_money    = $request->input('pick_money');
        $send_money    = $request->input('send_money');
        $price         = $request->input('price');
        $send_time     = $request->input('send_time') ?? null;
        $gather_time   = $request->input('gather_time') ??null;
        $total_money   = $request->input('total_money');
        $good_name_n   = $request->input('good_name');
        $good_number_n = $request->input('good_number');
        $good_weight_n = $request->input('good_weight');
        $good_volume_n = $request->input('good_volume');
        $dispatcher    = $request->input('dispatcher')??[];
        $clod          = $request->input('clod');
        $more_money    = $request->input('more_money') ? $request->input('more_money') - 0 : null ;
        $car_type      = $request->input('car_type')??''; //车型
        $pay_type      = $request->input('pay_type');
        $remark        = $request->input('remark');
        $app_flag      = $request->input('app_flag');//app上下单   1 是 2 PC下单
        $payer         = $request->input('payer');//付款方：发货人 consignor  收货人receiver
        if (empty($price)){
            $price = $request->input('line_price');
        }
        /*** 虚拟数据
//        $input['self_id']                 =$self_id='';
        $input['group_code']                =$group_code='1234';
        $input['company_id']                =$company_id='company_202105071628285392886298';
        $input['order_type']                =$order_type='vehicle';                                          //vehicle           lcl   line
        $input['line_id']                   =$line_id='line_202101051525424631283496';
        $input['pick_flag']                 =$pick_flag='Y';
        $input['send_flag']                 =$send_flag='N';
        $input['pick_money']                =$pick_money='200';
        $input['price']                     =$price='200';
        $input['send_money']                =$send_money='300';
        $input['total_money']               =$total_money='300';
        $input['more_money']                =$more_money=20;//多点费用
        $input['good_name']                 =$good_name_n='';
        $input['good_number']               =$good_number_n=20;
        $input['good_weight']               =$good_weight_n=5000;
        $input['good_volume']               =$good_volume_n=5;
        $input['clod']                      =$clod='refrigeration';
        $input['car_type']                  =$car_type='type_202102051755118039490396';
        $input['remark']                    =$remark='';
        $input['app_flag']                  =$app_flag = 1;
        $input['pay_type']                  =$pay_type='offline';
        $input['gather_time']               = $gather_time = '2021-05-22';
        $input['send_time']                 = $send_time = '2021-05-20';
        $input['dispatcher']                = $dispatcher=[
            '0'=>[
                'send_address_id'=>'',

                'send_qu'=>'43',
                'send_address'=>'小浪底001',
                'send_address_longitude'=>'121.471732',
                'send_address_latitude'=>'31.231518',
                'send_name'=>'张三001',
                'send_tel'=>'001',
                'good_name'=>'产品名称001',
                'good_number'=>'10',
                'good_weight'=>'55',
                'good_volume'=>'12',
                'clod'=>'refrigeration',
                'gather_address_id'=>'',

                'gather_qu'=>'43',
                'gather_address'=>'小浪底001_ga',
                'gather_address_longitude'=>'121.471732',
                'gather_address_latitude'=>'31.231518',
                'gather_name'=>'张三',
                'gather_tel'=>'123456',
            ],

             '1'=>[
                 'send_address_id'=>'',
                 'send_qu'=>'43',
                 'send_address'=>'小浪底002',
                 'send_address_longitude'=>'121.471732',
                 'send_address_latitude'=>'31.231518',
                 'send_name'=>'张三002',
                 'send_tel'=>'002',
                 'good_name'=>'产品名称002',
                 'good_number'=>'20',
                 'good_weight'=>'55',
                 'good_volume'=>'13',
                 'clod'=>'freeze',
                 'gather_address_id'=>'',

                 'gather_qu'=>'43',
                 'gather_address'=>'小浪底002_ga',
                 'gather_address_longitude'=>'121.471732',
                 'gather_address_latitude'=>'31.231518',
                 'gather_name'=>'张三002_ga',
                 'gather_tel'=>'002_ga',
             ],

             '2'=>[
                 'send_address_id'=>'',
                 'send_qu'=>'43',
                 'send_address'=>'小浪底003',
                 'send_address_longitude'=>'121.471732',
                 'send_address_latitude'=>'31.231518',
                 'send_name'=>'张三003',
                 'send_tel'=>'003',
                 'good_name'=>'产品名称003',
                 'good_number'=>'50',
                 'good_weight'=>'55',
                 'good_volume'=>'14',
                 'clod'=>'freeze',
                 'gather_address_id'=>'',
                 'gather_qu'=>'43',
                 'gather_address'=>'小浪底003_ga',
                 'gather_address_longitude'=>'121.471732',
                 'gather_address_latitude'=>'31.231518',
                 'gather_name'=>'张三003_ga',
                 'gather_tel'=>'123456_ga',
             ],
        ];
        **/
        if (empty($company_id)){
            $msg['code'] = 311;
            $msg['msg'] = '请选择客户公司';
            return $msg;
        }

        $rules=[
            'order_type'=>'required',
        ];
        $message=[
            'order_type.required'=>'必须选择',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            /***开始做二次效验**/
            $where_group=[
                ['delete_flag','=','Y'],
                ['self_id','=',$group_code],
            ];
            $group_info    =SystemGroup::where($where_group)->select('group_code','group_name')->first();
            if(empty($group_info)){
                $msg['code'] = 301;
                $msg['msg'] = '公司不存在';
                return $msg;
            }

            if ($order_type == 'vehicle' || $order_type == 'lcl') {
                if (count($dispatcher) == 0) {
                    $msg['code'] = 302;
                    $msg['msg'] = '请填写订单信息！';
                    return $msg;
                }
            }
            if ($order_type == 'line') {
                if ($pick_flag =='N' && $send_flag =='N') {
                    if (empty($good_name_n)) {
                        $msg['code'] = 306;
                        $msg['msg'] = '货物名称不能为空！';
                        return $msg;
                    }

                    if (empty($good_number_n) || $good_number_n <= 0) {
                        $msg['code'] = 307;
                        $msg['msg'] = '货物件数错误！';
                        return $msg;
                    }

                    if (empty($good_weight_n) || $good_weight_n <= 0) {
                        $msg['code'] = 308;
                        $msg['msg'] = '货物重量错误！';
                        return $msg;
                    }

                    if (empty($good_volume_n) || $good_volume_n <= 0) {
                        $msg['code'] = 309;
                        $msg['msg'] = '货物体积错误！';
                        return $msg;
                    }

                    if (empty($clod)) {
                        $msg['code'] = 309;
                        $msg['msg'] = '请选择温度！';
                        return $msg;
                    }
                }

                $send_t = '提货';
                $pick_t = '配送';
            } else {
                $send_t = '发货';
                $pick_t = '收货';
            }
            // $dispatcher[$k]['send_address_id']       =$send_address['self_id'];
            //     $dispatcher[$k]['send_sheng']            =$send_address['sheng'];
            //     $dispatcher[$k]['send_sheng_name']       =$send_address['sheng_name'];
            //     $dispatcher[$k]['send_shi']              =$send_address['shi'];
            //     $dispatcher[$k]['send_shi_name']         =$send_address['shi_name'];
            //     $dispatcher[$k]['send_qu']               =$send_address['qu'];
            //     $dispatcher[$k]['send_qu_name']          =$send_address['qu_name'];
            //     $dispatcher[$k]['send_address']          =$send_address['address'];
            //     $dispatcher[$k]['send_address_longitude']=$send_address['longitude'];
            //     $dispatcher[$k]['send_address_latitude'] =$send_address['dimensionality'];
            //     $dispatcher[$k]['send_contacts_id']      =$send_contacts['self_id'];
            //     $dispatcher[$k]['send_contacts_name']    =$send_contacts['contacts'];
            //     $dispatcher[$k]['send_contacts_tel']     =$send_contacts['tel'];

            //     $dispatcher[$k]['gather_address_id']       =$gather_address['self_id'];
            //     $dispatcher[$k]['gather_sheng']            =$gather_address['sheng'];
            //     $dispatcher[$k]['gather_sheng_name']       =$gather_address['sheng_name'];
            //     $dispatcher[$k]['gather_shi']              =$gather_address['shi'];
            //     $dispatcher[$k]['gather_shi_name']         =$gather_address['shi_name'];
            //     $dispatcher[$k]['gather_qu']               =$gather_address['qu'];
            //     $dispatcher[$k]['gather_qu_name']          =$gather_address['qu_name'];
            //     $dispatcher[$k]['gather_address']          =$gather_address['address'];
            //     $dispatcher[$k]['gather_address_longitude']=$gather_address['longitude'];
            //     $dispatcher[$k]['gather_address_latitude'] =$gather_address['dimensionality'];
            //     $dispatcher[$k]['gather_contacts_id']      =$gather_contacts['self_id'];
            //     $dispatcher[$k]['gather_contacts_name']    =$gather_contacts['contacts'];
            //     $dispatcher[$k]['gather_contacts_tel']     =$gather_contacts['tel'];
            /** 处理一下发货地址  及联系人**/
            foreach ($dispatcher as $k => $v){
                if ($order_type == 'vehicle' || $order_type == 'lcl' || ($order_type == 'line' && $send_flag == 'Y')) {
//                    dd($v['gather_name']);
                    $gather_address=$tms->address_contact($v['gather_address_id'],$v['gather_qu'],$v['gather_address'],$v['gather_name'],$v['gather_tel'],$group_info,$user_info,$now_time);

                    if(empty($gather_address)){
                        $msg['code'] = 303;
                        $msg['msg'] = $pick_t.'地址不存在';
                        return $msg;
                    }
//                    $gather_contacts = $tms->contacts($v['gather_contacts_id'],$v['gather_name'],$v['gather_tel'],$group_info,$user_info,$now_time);
//                    if(empty($gather_contacts)){
//                        $msg['code'] = 305;
//                        $msg['msg'] = $pick_t.'联系人不存在';
//                        return $msg;
//                    }
                } else if($order_type == 'line' && $send_flag == 'N') {
                    $gather_address = [
                        'self_id'           => '',
                        'sheng'             => '',
                        'sheng_name'        => '',
                        'shi'               => '',
                        'shi_name'          => '',
                        'qu'                => '',
                        'qu_name'           => '',
                        'address'           => '',
                        'longitude'         => '',
                        'dimensionality'    => '',
                        'contacts'  => '',
                        'tel'  => ''
                    ];

                    $gather_address = (Object)$gather_address;

                }
                if ($order_type == 'vehicle' || $order_type == 'lcl' || ($order_type == 'line' && $pick_flag == 'Y')) {

                    $send_address=$tms->address_contact($v['send_address_id'],$v['send_qu'],$v['send_address'],$v['send_name'],$v['send_tel'],$group_info,$user_info,$now_time);
                    if(empty($send_address)){
                        $msg['code'] = 303;
                        $msg['msg'] = $send_t.'地址不存在';
                        return $msg;
                    }
//                    $send_contacts = $tms->contacts($v['send_contacts_id'],$v['send_name'],$v['send_tel'],$group_info,$user_info,$now_time);
//                    if(empty($send_contacts)){
//                        $msg['code'] = 305;
//                        $msg['msg'] = $send_t.'联系人不存在';
//                        return $msg;
//                    }
                } else if($order_type == 'line' && $pick_flag == 'N') {
                    $send_address = [
                        'self_id'           => '',
                        'sheng'             => '',
                        'sheng_name'        => '',
                        'shi'               => '',
                        'shi_name'          => '',
                        'qu'                => '',
                        'qu_name'           => '',
                        'address'           => '',
                        'longitude'         => '',
                        'dimensionality'    => '',
                        'contacts'  => '',
                        'tel'  => ''
                    ];

                    $send_address = (Object)$send_address;
                }

                if (empty($v['good_name'])) {
                    $msg['code'] = 306;
                    $msg['msg'] = '货物名称不能为空！';
                    return $msg;
                }

                if (empty($v['good_number']) || $v['good_number'] <= 0) {
                    $msg['code'] = 307;
                    $msg['msg'] = '货物件数错误！';
                    return $msg;
                }

                if (empty($v['good_weight']) || $v['good_weight'] <= 0) {
                    $msg['code'] = 308;
                    $msg['msg'] = '货物重量错误！';
                    return $msg;
                }

                if (empty($v['good_volume']) || $v['good_volume'] <= 0) {
                    $msg['code'] = 309;
                    $msg['msg'] = '货物体积错误！';
                    return $msg;
                }

                if (empty($v['clod'])) {
                    $msg['code'] = 309;
                    $msg['msg'] = '请选择温度！';
                    return $msg;
                }
                // dump($gather_address);
                // dump($gather_contacts);
                // dump($send_address);
                // dd($send_contacts);

                $dispatcher[$k]['send_address_id']       =$send_address->self_id;
                $dispatcher[$k]['send_sheng']            =$send_address->sheng;
                $dispatcher[$k]['send_sheng_name']       =$send_address->sheng_name;
                $dispatcher[$k]['send_shi']              =$send_address->shi;
                $dispatcher[$k]['send_shi_name']         =$send_address->shi_name;
                $dispatcher[$k]['send_qu']               =$send_address->qu;
                $dispatcher[$k]['send_qu_name']          =$send_address->qu_name;
                $dispatcher[$k]['send_address']          =$send_address->address;
                $dispatcher[$k]['send_address_longitude']=$send_address->longitude;
                $dispatcher[$k]['send_address_latitude'] =$send_address->dimensionality;
//                $dispatcher[$k]['send_contacts_id']      =$send_contacts->self_id;
                $dispatcher[$k]['send_contacts_name']    =$send_address->contacts;
                $dispatcher[$k]['send_contacts_tel']     =$send_address->tel;

                $dispatcher[$k]['gather_address_id']       =$gather_address->self_id;
                $dispatcher[$k]['gather_sheng']            =$gather_address->sheng;
                $dispatcher[$k]['gather_sheng_name']       =$gather_address->sheng_name;
                $dispatcher[$k]['gather_shi']              =$gather_address->shi;
                $dispatcher[$k]['gather_shi_name']         =$gather_address->shi_name;
                $dispatcher[$k]['gather_qu']               =$gather_address->qu;
                $dispatcher[$k]['gather_qu_name']          =$gather_address->qu_name;
                $dispatcher[$k]['gather_address']          =$gather_address->address;
                $dispatcher[$k]['gather_address_longitude']=$gather_address->longitude;
                $dispatcher[$k]['gather_address_latitude'] =$gather_address->dimensionality;
//                $dispatcher[$k]['gather_contacts_id']      =$gather_contacts->self_id;
                $dispatcher[$k]['gather_contacts_name']    =$gather_address->contacts;
                $dispatcher[$k]['gather_contacts_tel']     =$gather_address->tel;

            }
            /** 处理一下发货地址  及联系人 结束**/

            /** 开始处理正式的数据*/
            $lki=[];
            $lki2=[];
            $gather = [];               //定义一个收货地址的集合
            $send = [];                 //定义一个送货地址的集合
            $good_name=[];
            $good_number=0;
            $good_weight=0;
            $good_volume=0;
            $clodss=[];

            $abccxxxxx=[];
            foreach ($dispatcher as $k => $v){
                $gather[]=$v['gather_address_id'];
                $send[]=$v['send_address_id'];
                $good_number+=$v['good_number'];
                $good_weight+=$v['good_weight'];
                $good_volume+=$v['good_volume'];
                $good_name[]=$v['good_name'];
                $clodss[]=$v['clod'];
                $abcc222['good_name']   =$v['good_name'];
                $abcc222['good_number'] =$v['good_number'];
                $abcc222['good_weight'] =$v['good_weight'];
                $abcc222['good_volume'] =$v['good_volume'];
                $abcc222['clod']        =$v['clod'];
                $abccxxxxx[]=$abcc222;
            }

            if($pick_flag == 'N' && $send_flag == 'N' && $order_type == 'line'){
                $abcc222 = [];
                $clodss = [];
                $abccxxxxx = [];
                $abcc222['good_name']   =$good_name_n;
                $abcc222['good_number'] =$good_number_n;
                $abcc222['good_weight'] =$good_weight_n;
                $abcc222['good_volume'] =$good_volume_n;
                $abcc222['clod']        =$clod;
                $abccxxxxx[]=$abcc222;
                $good_number=$good_number_n;
                $good_weight=$good_weight_n;
                $good_volume=$good_volume_n;
                $clodss[]=$clod;
            }

            $gather         =array_unique($gather);
            $send           =array_unique($send);
            $clodss         =array_unique($clodss);

            foreach ($gather as $k => $v){
                $lki[$k]['good_number']=0;
                $lki[$k]['good_weight']=0;
                $lki[$k]['good_volume']=0;
                $abccxx=[];
                $clod=[];
                foreach ($dispatcher as $kk => $vv){
                    if($v == $vv['gather_address_id']){
                        $lki[$k]['good_number']+=$vv['good_number'];
                        $lki[$k]['good_weight']+=$vv['good_weight'];
                        $lki[$k]['good_volume']+=$vv['good_volume'];
                        $lki[$k]['gather_address_id']=$vv['gather_address_id'];
//                        $lki[$k]['gather_contacts_id']=$vv['gather_contacts_id'];
                        $lki[$k]['gather_qu']=$vv['gather_qu'];
                        $lki[$k]['gather_qu_name']=$vv['gather_qu_name'];
                        $lki[$k]['gather_address']=$vv['gather_address'];
                        $lki[$k]['gather_address_longitude']=$vv['gather_address_longitude'];
                        $lki[$k]['gather_address_latitude']=$vv['gather_address_latitude'];
                        $lki[$k]['gather_sheng']=$vv['gather_sheng'];
                        $lki[$k]['gather_sheng_name']=$vv['gather_sheng_name'];
                        $lki[$k]['gather_shi']=$vv['gather_shi'];
                        $lki[$k]['gather_shi_name']=$vv['gather_shi_name'];
                        $lki[$k]['gather_contacts_name']=$vv['gather_contacts_name'];
                        $lki[$k]['gather_contacts_tel']=$vv['gather_contacts_tel'];
                        $abcc['good_name']=$vv['good_name'];
                        $abcc['good_number']=$vv['good_number'];
                        $abcc['good_weight']=$vv['good_weight'];
                        $abcc['good_volume']=$vv['good_volume'];
                        $abcc['clod']        =$vv['clod'];
                        $abccxx[]=$abcc;
                        $clod[]=$vv['clod'];
                    }
                }
                $lki[$k]['clod']=$clod;
                $lki[$k]['good_josn']=json_encode($abccxx,JSON_UNESCAPED_UNICODE);
            }

            $good_info=json_encode($abccxxxxx,JSON_UNESCAPED_UNICODE);

            foreach ($send as $k => $v){
                $lki2[$k]['good_number']=0;
                $lki2[$k]['good_weight']=0;
                $lki2[$k]['good_volume']=0;
                $abccxx2=[];
                $clod2=[];
                foreach ($dispatcher as $kk => $vv){
                    if($v == $vv['send_address_id']){
                        $lki2[$k]['good_number']+=$vv['good_number'];
                        $lki2[$k]['good_weight']+=$vv['good_weight'];
                        $lki2[$k]['good_volume']+=$vv['good_volume'];
                        $lki2[$k]['send_address_id']=$vv['send_address_id'];
//                        $lki2[$k]['send_contacts_id']=$vv['send_contacts_id'];
                        $lki2[$k]['send_qu']=$vv['send_qu'];
                        $lki2[$k]['send_qu_name']=$vv['send_qu_name'];
                        $lki2[$k]['send_address']=$vv['send_address'];
                        $lki2[$k]['send_address_longitude']=$vv['send_address_longitude'];
                        $lki2[$k]['send_address_latitude']=$vv['send_address_latitude'];
                        $lki2[$k]['send_sheng']=$vv['send_sheng'];
                        $lki2[$k]['send_sheng_name']=$vv['send_sheng_name'];
                        $lki2[$k]['send_shi']=$vv['send_shi'];
                        $lki2[$k]['send_shi_name']=$vv['send_shi_name'];
                        $lki2[$k]['send_contacts_name']=$vv['send_contacts_name'];
                        $lki2[$k]['send_contacts_tel']=$vv['send_contacts_tel'];
                        $abcc2['good_name']=$vv['good_name'];
                        $abcc2['good_number']=$vv['good_number'];
                        $abcc2['good_weight']=$vv['good_weight'];
                        $abcc2['good_volume']=$vv['good_volume'];
                        $abcc2['clod']        =$vv['clod'];
                        $abccxx2[]=$abcc2;
                        $clod2[]=$vv['clod'];
                    }
                }

                $lki2[$k]['good_josn']=json_encode($abccxx2,JSON_UNESCAPED_UNICODE);
                $lki2[$k]['clod']=$clod2;
            }

            /***现在处理收货地址的控制**/
            $select_company=['self_id','company_name'];
            $where_company=[
                ['delete_flag','=','Y'],
                ['self_id','=',$company_id],
            ];
            $company_info = TmsGroup::where($where_company)->select($select_company)->first();
//            dd($company_info);
            if(empty($company_info)){
                $company_name = '';
            } else {
                $company_name = $company_info->company_name;
            }

            $order_id = generate_id('order_');
             /***现在处理费用的部分控制**/
                    $money = [];
                    if ($company_id) {
                        $type = 'in';
                        if($pick_flag == 'Y'){
                            //如果客户需要提货/装货，则产生提货费/装货费
                            $money[] = [
                                'money'             => ($pick_money - 0)*100,
                                'money_type'        => $order_type == 'vehicle' ? 'loading' : 'gather',
                            ];
                        }
                        $money[] = [
                            'money'             => ($price - 0)*100,
                            'money_type'        =>'freight',
                        ];
                        if($more_money > 0){
                            $money[] = [
                                'money'             => $more_money*100,
                                'money_type'        =>'more',
                            ];
                        }
                        if($send_flag == 'Y'){
                            //如果客户需要配送/卸货，则产生送货费/卸货费
                            $money[] = [
                                'money'             => ($send_money - 0)*100,
                                'money_type'        =>  $order_type == 'vehicle' ? 'unloading' : 'send',
                            ];
                        }

                        if ($pay_type == 'offline'){
                            foreach($money as $kkkk => $vvvv) {
                                if ($company_id){
                                    $company_money['self_id']                    = generate_id('order_money_');
                                    $company_money['shouk_group_code']           = $group_info->group_code;
                                    $company_money['shouk_type']                 = 'GROUP_CODE';
                                    $company_money['fk_company_id']              = $company_id;
                                    $company_money['fk_type']                    = 'COMPANY';
                                    $company_money['ZIJ_company_id']             = $company_id;
                                    $company_money['order_id']                   = $order_id;
                                    $company_money['create_time']                = $now_time;
                                    $company_money['update_time']                = $now_time;
                                    $company_money['money']                      = $vvvv['money'];
                                    $company_money['money_type']                 = $vvvv['money_type'];
                                    $company_money['type']                       = 'in';
                                    $money_company[] = $company_money;
                                }else{
//                                    $money[$k]['self_id']                    = generate_id('order_money_');
//                                    $money[$k]['fk_group_code']              = $group_info->group_code;
//                                    $money[$k]['fk_type']                    = 'GROUP_CODE';
//                                    $money[$k]['ZIJ_group_code']             = $group_info->group_code;
//                                    $money[$k]['order_id']                   = $order_id;
//                                    $money[$k]['create_time']                = $now_time;
//                                    $money[$k]['update_time']                = $now_time;
//                                    $money[$k]['money']                      = $v['money'];
//                                    $money[$k]['money_type']                 = $v['money_type'];
//                                    $money[$k]['type']                       = 'out';
                                }

                            }
                        }else{
                            $money_company = [];
                            foreach($money as $kkkk => $vvvv) {
                                if ($company_id){
                                    $company_money['self_id']                    = generate_id('order_money_');
                                    $company_money['shouk_group_code']           = $group_info->group_code;
                                    $company_money['shouk_type']                 = 'GROUP_CODE';
                                    $company_money['fk_company_id']              = $company_id;
                                    $company_money['fk_type']                    = 'COMPANY';
                                    $company_money['ZIJ_company_id']             = $company_id;
                                    $company_money['order_id']                   = $order_id;
                                    $company_money['create_time']                = $now_time;
                                    $company_money['update_time']                = $now_time;
                                    $company_money['money']                      = $vvvv['money'];
                                    $company_money['money_type']                 = $vvvv['money_type'];
                                    $company_money['type']                       = 'in';
                                    $money_company[] = $company_money;
                                }else{
//                                    $money[$k]['self_id']                    = generate_id('order_money_');
//                                    $money[$k]['shouk_group_code']           = '1234';
//                                    $money[$k]['shouk_type']                 = 'PLATFORM';
//                                    $money[$k]['fk_group_code']              = $group_info->group_code;
//                                    $money[$k]['fk_type']                    = 'GROUP_CODE';
//                                    $money[$k]['ZIJ_group_code']             = $group_info->group_code;
//                                    $money[$k]['order_id']                   = $order_id;
//                                    $money[$k]['create_time']                = $now_time;
//                                    $money[$k]['update_time']                = $now_time;
//                                    $money[$k]['money']                      = $v['money'];
//                                    $money[$k]['money_type']                 = $v['money_type'];
//                                    $money[$k]['type']                       = 'out';
                                }
                            }
                        }

                    }
//                    dd($money_company);
            switch ($order_type){
                case 'line':

                    // $line_id= 'line_202101061755043806385994';
                    $where_line=[
                        ['delete_flag','=','Y'],
                        ['self_id','=',$line_id],
                    ];
                    $select_line=['self_id','shift_number','type','price','use_flag','group_name','type',
                        'pick_price','send_price','pick_type','send_type','all_weight','all_volume','trunking','control',
                        'send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_sheng_name','send_shi','send_shi_name',
                        'send_qu','send_qu_name','send_address','send_address_longitude','send_address_latitude',
                        'gather_address_id','gather_contacts_id','gather_name','gather_tel',
                        'gather_sheng','gather_sheng_name','gather_shi','gather_shi_name','gather_qu','gather_qu_name','gather_address','gather_address_longitude','gather_address_latitude'];
                    $selectList=['line_id','yuan_self_id'];

                    $line_info = TmsLine::with(['tmsLineList' => function($query)use($selectList,$select_line){
                        $query->where('delete_flag','=','Y');
                        $query->select($selectList);
                        $query->with(['tmsLine' => function($query)use($select_line){
                            $query->select($select_line);
                        }]);
                    }])->where($where_line)->select($select_line)->first();
                    // dd($line_info->toArray());
                    if(empty($line_info)){
                        $msg['code'] = 307;
                        $msg['msg'] = '线路不存在';
                        return $msg;
                    }

                     $inserttt=[];
                     $sort=1;
                    /** 以上是提货 的调度的地方 **/
                    if($pick_flag == 'Y'){
                        foreach ($lki2 as $k => $v){
                            $list['self_id']                      = generate_id('patch_');
                            $list['order_id']                     = $order_id;
                            $list['group_code']                   = $group_info->group_code;
                            $list['receiver_id']                   = $group_info->group_code;
                            $list['group_name']                   = $group_info->group_name;
                            $list['create_user_id']               = $user_info->admin_id;
                            $list['create_user_name']             = $user_info->name;
                            $list['create_time']                  = $list['update_time']=$now_time;
                            $list['gather_address_id']            = $line_info['send_address_id'];
//                            $list['gather_contacts_id']           = $line_info['send_contacts_id'];
                            $list['gather_name']                  = $line_info['send_name'];
                            $list['gather_tel']                   = $line_info['send_tel'];
                            $list['gather_sheng']                 = $line_info['send_sheng'];
                            $list['gather_sheng_name']            = $line_info['send_sheng_name'];
                            $list['gather_shi']                   = $line_info['send_shi'];
                            $list['gather_shi_name']              = $line_info['send_shi_name'];
                            $list['gather_qu']                    = $line_info['send_qu'];
                            $list['gather_qu_name']               = $line_info['send_qu_name'];
                            $list['gather_address']               = $line_info['send_address'];
                            $list['gather_address_longitude']     = $line_info['send_address_longitude'];
                            $list['gather_address_latitude']      = $line_info['send_address_latitude'];

                            $list['send_address_id']            = $v['send_address_id'];
//                            $list['send_contacts_id']           = $v['send_contacts_id'];
                            $list['send_name']                  = $v['send_contacts_name'];
                            $list['send_tel']                   = $v['send_contacts_tel'];
                            $list['send_sheng']                 = $v['send_sheng'];
                            $list['send_sheng_name']            = $v['send_sheng_name'];
                            $list['send_shi']                   = $v['send_shi'];
                            $list['send_shi_name']              = $v['send_shi_name'];
                            $list['send_qu']                    = $v['send_qu'];
                            $list['send_qu_name']               = $v['send_qu_name'];
                            $list['send_address']               = $v['send_address'];
                            $list['send_address_longitude']     = $v['send_address_longitude'];
                            $list['send_address_latitude']      = $v['send_address_latitude'];

                            $list['good_number']                = $v['good_number'];
                            $list['good_weight']                = $v['good_weight'];
                            $list['good_volume']                = $v['good_volume'];
                            $list['dispatch_flag']              = 'Y';
                            $list['sort']                       = $sort++;
                            $list['group_code']                 = $group_info->group_code;
                            $list['group_name']                 = $group_info->group_name;
                            $list['order_type']                 = $order_type;
                            $list['pick_flag']                  = $pick_flag;
                            $list['send_flag']                  = $send_flag;
                            $list['good_info']                  = $v['good_josn'];
                            $list['clod']                       = json_encode($v['clod'],JSON_UNESCAPED_UNICODE);
                            $list['remark']                     = $remark;
                            $list['order_status']               = 3;
                            $list['pay_type']                   = $pay_type;
                            if ($pay_type == 'online'){
                                $list['order_status']               = 1;
                            }
                            $list['payer']                      = $payer;
                            $inserttt[]=$list;
                        }

                    }

                    /** 以上是收货的调度的地方 结束**/


                    /** 以下是处理线路中的调度的地方**/
                    if($line_info['type'] == 'combination'){
                        // dd($line_info->tmsLineList->toArray());
                        foreach ($line_info->tmsLineList as $k => $v){
                            $list['self_id']                      = generate_id('patch_');
                            $list['order_id']                     = $order_id;
                            $list['group_code']                   = $group_info->group_code;
                            $list['receiver_id']                   = $group_info->group_code;
                            $list['group_name']                   = $group_info->group_name;
                            $list['create_user_id']               = $user_info->admin_id;
                            $list['create_user_name']             = $user_info->name;
                            $list['create_time']                  = $list['update_time']=$now_time;
                            $list['gather_address_id']            = $v['tmsLine']['gather_address_id'];
//                            $list['gather_contacts_id']           = $v['tmsLine']['gather_contacts_id'];
                            $list['gather_name']                  = $v['tmsLine']['gather_name'];
                            $list['gather_tel']                   = $v['tmsLine']['gather_tel'];
                            $list['gather_sheng']                 = $v['tmsLine']['gather_sheng'];
                            $list['gather_sheng_name']            = $v['tmsLine']['gather_sheng_name'];
                            $list['gather_shi']                   = $v['tmsLine']['gather_shi'];
                            $list['gather_shi_name']              = $v['tmsLine']['gather_shi_name'];
                            $list['gather_qu']                    = $v['tmsLine']['gather_qu'];
                            $list['gather_qu_name']               = $v['tmsLine']['gather_qu_name'];
                            $list['gather_address']               = $v['tmsLine']['gather_address'];
                            $list['gather_address_longitude']     = $v['tmsLine']['gather_address_longitude'];
                            $list['gather_address_latitude']      = $v['tmsLine']['gather_address_latitude'];
                            $list['send_address_id']            = $v['tmsLine']['send_address_id'];
//                            $list['send_contacts_id']           = $v['tmsLine']['send_contacts_id'];
                            $list['send_name']                  = $v['tmsLine']['send_name'];
                            $list['send_tel']                   = $v['tmsLine']['send_tel'];
                            $list['send_sheng']                 = $v['tmsLine']['send_sheng'];
                            $list['send_sheng_name']            = $v['tmsLine']['send_sheng_name'];
                            $list['send_shi']                   = $v['tmsLine']['send_shi'];
                            $list['send_shi_name']              = $v['tmsLine']['send_shi_name'];
                            $list['send_qu']                    = $v['tmsLine']['send_qu'];
                            $list['send_qu_name']               = $v['tmsLine']['send_qu_name'];
                            $list['send_address']               = $v['tmsLine']['send_address'];
                            $list['send_address_longitude']     = $v['tmsLine']['send_address_longitude'];
                            $list['send_address_latitude']      = $v['tmsLine']['send_address_latitude'];
                            $list['good_number']                = $good_number;
                            $list['good_weight']                = $good_weight;
                            $list['good_volume']                = $good_volume;
                            $list['order_type']                 = $order_type;
                            $list['sort']                       = $sort++;
                            $list['good_info']                  = $good_info;
                            $list['pick_flag']                  = $pick_flag;
                            $list['send_flag']                  = $send_flag;
                            $list['dispatch_flag']              = 'Y';
                            $list['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                            $list['remark']                     = $remark;
                            $list['order_status']               = 3;
                            $list['pay_type']                   = $pay_type;
                            if ($pay_type == 'online'){
                                $list['order_status']               = 1;
                            }
                            $list['payer']                      = $payer;
                            $inserttt[]=$list;
                        }

                    }else{
                        $list['self_id']                      = generate_id('patch_');
                        $list['order_id']                     = $order_id;
                        $list['group_code']                   = $group_info->group_code;
                        $list['receiver_id']                   = $group_info->group_code;
                        $list['group_name']                   = $group_info->group_name;
                        $list['create_user_id']               = $user_info->admin_id;
                        $list['create_user_name']             = $user_info->name;
                        $list['create_time']                  = $list['update_time']=$now_time;
                        $list['gather_address_id']            = $line_info->gather_address_id;
//                        $list['gather_contacts_id']           = $line_info->gather_contacts_id;
                        $list['gather_name']                  = $line_info->gather_name;
                        $list['gather_tel']                   = $line_info->gather_tel;
                        $list['gather_sheng']                 = $line_info->gather_sheng;
                        $list['gather_sheng_name']            = $line_info->gather_sheng_name;
                        $list['gather_shi']                   = $line_info->gather_shi;
                        $list['gather_shi_name']              = $line_info->gather_shi_name;
                        $list['gather_qu']                    = $line_info->gather_qu;
                        $list['gather_qu_name']               = $line_info->gather_qu_name;
                        $list['gather_address']               = $line_info->gather_address;
                        $list['gather_address_longitude']     = $line_info->gather_address_longitude;
                        $list['gather_address_latitude']      = $line_info->gather_address_latitude;
                        $list['send_address_id']            = $line_info->send_address_id;
//                        $list['send_contacts_id']           = $line_info->send_contacts_id;
                        $list['send_name']                  = $line_info->send_name;
                        $list['send_tel']                   = $line_info->send_tel;
                        $list['send_sheng']                 = $line_info->send_sheng;
                        $list['send_sheng_name']            = $line_info->send_sheng_name;
                        $list['send_shi']                   = $line_info->send_shi;
                        $list['send_shi_name']              = $line_info->send_shi_name;
                        $list['send_qu']                    = $line_info->send_qu;
                        $list['send_qu_name']               = $line_info->send_qu_name;
                        $list['send_address']               = $line_info->send_address;
                        $list['send_address_longitude']     = $line_info->send_address_longitude;
                        $list['send_address_latitude']      = $line_info->send_address_latitude;
                        $list['good_number']                = $good_number ;
                        $list['good_weight']                = $good_weight ;
                        $list['good_volume']                = $good_volume ;
                        $list['dispatch_flag']              = 'Y';
                        $list['group_code']                 = $group_info->group_code;
                        $list['group_name']                 = $group_info->group_name;
                        $list['sort']                       = $sort++;
                        $list['order_type']                 = $order_type;
                        $list['pick_flag']                  = $pick_flag;
                        $list['send_flag']                  = $send_flag;
                        $list['good_info']                  = $good_info;
                        $list['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                        $list['remark']                     = $remark;
                        $list['order_status']               = 3;
                        $list['pay_type']                   = $pay_type;
                        if ($pay_type == 'online'){
                            $list['order_status']               = 1;
                        }
                        $list['payer']                      = $payer;
                        $inserttt[]=$list;

                    }

                    /** 以上是处理线路中的调度的地方 结束**/
//                     dd($inserttt);
                    /** 以上是收的调度的地方 **/
                    if($send_flag == 'Y'){
                        foreach ($lki as $k => $v){
                            $list['self_id']                      = generate_id('patch_');
                            $list['order_id']                     = $order_id;
                            $list['group_code']                   = $group_info->group_code;
                            $list['receiver_id']                   = $group_info->group_code;
                            $list['group_name']                   = $group_info->group_name;
                            $list['create_user_id']               = $user_info->admin_id;
                            $list['create_user_name']             = $user_info->name;
                            $list['create_time']                  = $list['update_time']=$now_time;

                            $list['send_address_id']            = $line_info['gather_address_id'];
//                            $list['send_contacts_id']           = $line_info['gather_contacts_id'];
                            $list['send_name']                  = $line_info['gather_name'];
                            $list['send_tel']                   = $line_info['gather_tel'];
                            $list['send_sheng']                 = $line_info['gather_sheng'];
                            $list['send_sheng_name']            = $line_info['gather_sheng_name'];
                            $list['send_shi']                   = $line_info['gather_shi'];
                            $list['send_shi_name']              = $line_info['gather_shi_name'];
                            $list['send_qu']                    = $line_info['gather_qu'];
                            $list['send_qu_name']               = $line_info['gather_qu_name'];
                            $list['send_address']               = $line_info['gather_address'];
                            $list['send_address_longitude']     = $line_info['gather_address_longitude'];
                            $list['send_address_latitude']      = $line_info['gather_address_latitude'];

                            $list['gather_address_id']            = $v['gather_address_id'];
//                            $list['gather_contacts_id']           = $v['gather_contacts_id'];
                            $list['gather_name']                  = $v['gather_contacts_name'];
                            $list['gather_tel']                   = $v['gather_contacts_tel'];
                            $list['gather_sheng']                 = $v['gather_sheng'];
                            $list['gather_sheng_name']            = $v['gather_sheng_name'];
                            $list['gather_shi']                   = $v['gather_shi'];
                            $list['gather_shi_name']              = $v['gather_shi_name'];
                            $list['gather_qu']                    = $v['gather_qu'];
                            $list['gather_qu_name']               = $v['gather_qu_name'];
                            $list['gather_address']               = $v['gather_address'];
                            $list['gather_address_longitude']     = $v['gather_address_longitude'];
                            $list['gather_address_latitude']      = $v['gather_address_latitude'];
                            $list['good_number']                = $v['good_number'];
                            $list['good_weight']                = $v['good_weight'];
                            $list['good_volume']                = $v['good_volume'];
                            $list['dispatch_flag']              = 'Y';
                            $list['group_code']                 = $group_info->group_code;
                            $list['group_name']                 = $group_info->group_name;
                            $list['sort']                       = $sort++;
                            $list['order_type']                 = $order_type;
                            $list['pick_flag']                  = $pick_flag;
                            $list['send_flag']                  = $send_flag;
                            $list['good_info']                  = $v['good_josn'];
                            $list['clod']                       = json_encode($v['clod'],JSON_UNESCAPED_UNICODE);
                            $list['remark']                     = $remark;
                            $list['order_status']               = 3;
                            $list['pay_type']                   = $pay_type;
                            if ($pay_type == 'online'){
                                $list['order_status']               = 1;
                            }
                            $list['payer']                      = $payer;
                            $inserttt[]=$list;
                        }
                    }

                    /** 以上是送货的调度的地方 结束**/

                    $data['self_id']                    = $order_id;       //order ID
                    $data['group_code']                 = $group_info->group_code;
                    $data['group_name']                 = $group_info->group_name;
                    $data['create_user_id']             = $user_info->admin_id;
                    $data['create_user_name']           = $user_info->name;
                    $data['create_time']                = $data['update_time']=$now_time;
                    $data['company_id']                 = $company_id;
                    $data['company_name']               = $company_name;
                    $data['order_type']                 = $order_type;
                    $data['pick_money']                 = ($pick_money - 0)*100;
                    $data['send_money']                 = ($send_money - 0)*100;
                    $data['more_money']                 = ($more_money - 0)*100;
                    $data['price']                      = ($price - 0)*100;
                    // 配送
                    if($send_flag == 'Y'){
                        $data['gather_address_id']          = $lki[0]['gather_address_id'];
//                        $data['gather_contacts_id']         = $lki[0]['gather_contacts_id'];
                        $data['gather_name']                = $lki[0]['gather_contacts_name'];
                        $data['gather_tel']                 = $lki[0]['gather_contacts_tel'];
                        $data['gather_sheng']               = $lki[0]['gather_sheng'];
                        $data['gather_sheng_name']          = $lki[0]['gather_sheng_name'];
                        $data['gather_shi']                 = $lki[0]['gather_shi'];
                        $data['gather_shi_name']            = $lki[0]['gather_shi_name'];
                        $data['gather_qu']                  = $lki[0]['gather_qu'];
                        $data['gather_qu_name']             = $lki[0]['gather_qu_name'];
                        $data['gather_address']             = $lki[0]['gather_address'];
                        $data['gather_address_longitude']   = $lki[0]['gather_address_longitude'];
                        $data['gather_address_latitude']    = $lki[0]['gather_address_latitude'];
                    }else{
                        $data['gather_address_id']            = $line_info['gather_address_id'];
//                        $data['gather_contacts_id']           = $line_info['gather_contacts_id'];
                        $data['gather_name']                  = $line_info['gather_name'];
                        $data['gather_tel']                   = $line_info['gather_tel'];
                        $data['gather_sheng']                 = $line_info['gather_sheng'];
                        $data['gather_sheng_name']            = $line_info['gather_sheng_name'];
                        $data['gather_shi']                   = $line_info['gather_shi'];
                        $data['gather_shi_name']              = $line_info['gather_shi_name'];
                        $data['gather_qu']                    = $line_info['gather_qu'];
                        $data['gather_qu_name']               = $line_info['gather_qu_name'];
                        $data['gather_address']               = $line_info['gather_address'];
                        $data['gather_address_longitude']     = $line_info['gather_address_longitude'];
                        $data['gather_address_latitude']      = $line_info['gather_address_latitude'];
                    }
                    //提货
                    if($pick_flag == 'Y'){
                        $data['send_address_id']            = $lki2[0]['send_address_id'];
//                        $data['send_contacts_id']           = $lki2[0]['send_contacts_id'];
                        $data['send_name']                  = $lki2[0]['send_contacts_name'];
                        $data['send_tel']                   = $lki2[0]['send_contacts_tel'];
                        $data['send_sheng']                 = $lki2[0]['send_sheng'];
                        $data['send_sheng_name']            = $lki2[0]['send_sheng_name'];
                        $data['send_shi']                   = $lki2[0]['send_shi'];
                        $data['send_shi_name']              = $lki2[0]['send_shi_name'];
                        $data['send_qu']                    = $lki2[0]['send_qu'];
                        $data['send_qu_name']               = $lki2[0]['send_qu_name'];
                        $data['send_address']               = $lki2[0]['send_address'];
                        $data['send_address_longitude']     = $lki2[0]['send_address_longitude'];
                        $data['send_address_latitude']      = $lki2[0]['send_address_latitude'];
                    }else{
                        $data['send_address_id']            = $line_info->send_address_id;
//                        $data['send_contacts_id']           = $line_info->send_contacts_id;
                        $data['send_name']                  = $line_info->send_name;
                        $data['send_tel']                   = $line_info->send_tel;
                        $data['send_sheng']                 = $line_info->send_sheng;
                        $data['send_sheng_name']            = $line_info->send_sheng_name;
                        $data['send_shi']                   = $line_info->send_shi;
                        $data['send_shi_name']              = $line_info->send_shi_name;
                        $data['send_qu']                    = $line_info->send_qu;
                        $data['send_qu_name']               = $line_info->send_qu_name;
                        $data['send_address']               = $line_info->send_address;
                        $data['send_address_longitude']     = $line_info->send_address_longitude;
                        $data['send_address_latitude']      = $line_info->send_address_latitude;

                    }

                    $data['good_number']                = $good_number;
                    $data['good_weight']                = $good_weight;
                    $data['good_volume']                = $good_volume;
                    $data['pick_flag']                  = $pick_flag;
                    $data['send_flag']                  = $send_flag;
                    $data['total_money']                = ($total_money - 0)*100;
                    $data['line_id']                    = $line_id;
                    $data['line_info']                  = json_encode($line_info,JSON_UNESCAPED_UNICODE);
                    $data['info']                       = json_encode($dispatcher,JSON_UNESCAPED_UNICODE);
                    $data['good_info']                  = $good_info;
                    $data['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                    $data['remark']                     = $remark;
                    $data['app_flag']                   = $app_flag;

                    $data['order_status']               = 3;
                    $data['pay_type']                   = $pay_type;
                    if ($pay_type == 'online'){
                        $list['order_status']               = 1;
                    }
                    $data['payer']                      = $payer;
                    $wheres['self_id'] = $self_id;
                    $old_info=TmsOrder::where($wheres)->first();

                    if($old_info){
                        $data['update_time']=$now_time;
                        $id=TmsOrder::where($wheres)->update($data);
//
                        $operationing->access_cause='修改订单';
                        $operationing->operation_type='update';

                    }else{
//                        $data['self_id']            =generate_id('order_');
                        $data['group_code']         = $group_info->group_code;
                        $data['group_name']         = $group_info->group_name;
                        $data['create_user_id']     =$user_info->admin_id;
                        $data['create_user_name']   =$user_info->name;
                        $data['create_time']        =$data['update_time']=$now_time;
                        $id=TmsOrder::insert($data);
                        TmsOrderDispatch::insert($inserttt);
//                        $money_id=    TmsOrderCost::insert($money);
                        if ($company_id) {
                           $company_money_id= TmsOrderCost::insert($money_company);
                        }


                        $operationing->access_cause='新建订单';
                        $operationing->operation_type='create';

                    }

                    $operationing->table_id=$old_info?$self_id:$data['self_id'];
                    $operationing->old_info=$old_info;
                    $operationing->new_info=$data;

                    if($id){
                        $msg['code'] = 200;
                        $msg['order_id'] = $order_id;
                        $msg['order_id_show'] = substr($order_id,15);
                        $msg['msg'] = "操作成功";
                        return $msg;
                    }else{
                        $msg['code'] = 302;
                        $msg['msg'] = "操作失败";
                        return $msg;
                    }

                    break;
                default:
                    $wheres['self_id'] = $self_id;
                    $old_info=TmsOrder::where($wheres)->first();

                    if($old_info){
                        $operationing->access_cause='修改订单';
                        $operationing->operation_type='update';

                    }else{
                        $operationing->access_cause='新建订单';
                        $operationing->operation_type='create';
                    }

                    $data['order_type']                 = $order_type;
                    $data['gather_address_id']          = $dispatcher[0]['gather_address_id'];
//                    $data['gather_contacts_id']         = $dispatcher[0]['gather_contacts_id'];
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
                    $data['gather_time']                = $gather_time;
                    $data['send_address_id']            = $dispatcher[0]['send_address_id'];
//                    $data['send_contacts_id']           = $dispatcher[0]['send_contacts_id'];
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
                    $data['send_time']                  = $send_time;
                    $data['good_number']                = $good_number;
                    $data['good_weight']                = $good_weight;
                    $data['good_volume']                = $good_volume;
                    $data['company_id']                 = $company_id;
                    $data['company_name']               = $company_name;
                    $data['pick_flag']                  = $pick_flag;
                    $data['send_flag']                  = $send_flag;
                    $data['total_money']                = ($total_money - 0) * 100;
                    $data['info']                       = json_encode($dispatcher,JSON_UNESCAPED_UNICODE);
                    $data['good_info']                  = $good_info;
                    $data['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                    $data['pick_money']                 = ($pick_money - 0)*100;
                    $data['send_money']                 = ($send_money - 0)*100;
                    $data['more_money']                 = ($more_money - 0)*100;
                    $data['price']                      = ($price - 0)*100;
                    $data['order_status']               = 3;
                    $data['pay_type']                   = $pay_type;
                    $data['payer']                      = $payer;
                    if ($pay_type == 'online'){
                        $list['order_status']               = 1;
                    }
                    $data['car_type']                   = $car_type;
                    $data['remark']                     = $remark;
                    $data['app_flag']                   = $app_flag;
                    if ($send_time) {
                        $data['send_time']              = $send_time;
                    }
                    if ($gather_time) {
                        $data['gather_time']            = $gather_time;
                    }
                    /*** 现在根据用户的这个是否提货产生出可调度的数据出来以及费用出来**/
                    $inserttt=[];

                    /** 做一个调度数据出来*/
                    $list['order_type']                 = $order_type;
                    $list['order_id']                   = $order_id;
                    if ($company_info){
                        $list['company_id']                 = $company_info->self_id;
                        $list['company_name']               = $company_info->company_name;
                    }
                    $list['gather_address_id']          = $dispatcher[0]['gather_address_id'];

                    $data['total_money']                = ($total_money - 0) * 100;
//                    $list['gather_contacts_id']         = $dispatcher[0]['gather_contacts_id'];
                    $list['gather_name']                = $dispatcher[0]['gather_contacts_name'];
                    $list['gather_tel']                 = $dispatcher[0]['gather_contacts_tel'];
                    $list['gather_sheng']               = $dispatcher[0]['gather_sheng'];
                    $list['gather_sheng_name']          = $dispatcher[0]['gather_sheng_name'];
                    $list['gather_shi']                 = $dispatcher[0]['gather_shi'];
                    $list['gather_shi_name']            = $dispatcher[0]['gather_shi_name'];
                    $list['gather_qu']                  = $dispatcher[0]['gather_qu'];
                    $list['gather_qu_name']             = $dispatcher[0]['gather_qu_name'];
                    $list['gather_address']             = $dispatcher[0]['gather_address'];
                    $list['gather_address_longitude']   = $dispatcher[0]['gather_address_longitude'];
                    $list['gather_address_latitude']    = $dispatcher[0]['gather_address_latitude'];
                    $list['gather_time']                = $gather_time;
                    $list['send_address_id']            = $dispatcher[0]['send_address_id'];
//                    $list['send_contacts_id']           = $dispatcher[0]['send_contacts_id'];
                    $list['send_name']                  = $dispatcher[0]['send_contacts_name'];
                    $list['send_tel']                   = $dispatcher[0]['send_contacts_tel'];
                    $list['send_sheng']                 = $dispatcher[0]['send_sheng'];
                    $list['send_sheng_name']            = $dispatcher[0]['send_sheng_name'];
                    $list['send_shi']                   = $dispatcher[0]['send_shi'];
                    $list['send_shi_name']              = $dispatcher[0]['send_shi_name'];
                    $list['send_qu']                    = $dispatcher[0]['send_qu'];
                    $list['send_qu_name']               = $dispatcher[0]['send_qu_name'];
                    $list['send_address']               = $dispatcher[0]['send_address'];
                    $list['send_address_longitude']     = $dispatcher[0]['send_address_longitude'];
                    $list['send_address_latitude']      = $dispatcher[0]['send_address_latitude'];
                    $list['send_time']                  = $send_time;
                    $list['good_number']                = $good_number;
                    $list['good_weight']                = $good_weight;
                    $list['good_volume']                = $good_volume;
                    $list['dispatch_flag']              = 'Y';
                    $list['pick_flag']                  = $pick_flag;
                    $list['send_flag']                  = $send_flag;
                    $list['info']                       = json_encode($dispatcher,JSON_UNESCAPED_UNICODE);
                    $list['good_info']                  = $good_info;
                    $list['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                    $list['remark']                     = $remark;
                    $list['order_status']               = 3;
                    $list['pay_type']                   = $pay_type;
                    if ($pay_type == 'online'){
                        $list['order_status']               = 1;
                    }
                    $list['car_type']                   = $car_type;
                    $list['payer']                      = $payer;
                    if($old_info){

                    }else{
                        $list['self_id']            =generate_id('patch_');
                        $list['group_code']         = $group_info->group_code;
                        $list['receiver_id']         = $group_info->group_code;
                        $list['group_name']         = $group_info->group_name;
                        $list['create_user_id']     =$user_info->admin_id;
                        $list['create_user_name']   =$user_info->name;
                        $list['create_time']        =$list['update_time']=$now_time;
                    }

                    $inserttt[]=$list;

                    if($old_info){
                        $data['update_time']=$now_time;
                        $id=TmsOrder::where($wheres)->update($data);
//
                        $operationing->access_cause='修改订单';
                        $operationing->operation_type='update';
                    }else{
                        $data['self_id']            = $order_id;
                        $data['group_code']         = $group_info->group_code;
                        $data['group_name']         = $group_info->group_name;
                        $data['create_user_id']     = $user_info->admin_id;
                        $data['create_user_name']   = $user_info->name;
                        $data['create_time']        = $data['update_time']=$now_time;

                        $id=TmsOrder::insert($data);
                        TmsOrderDispatch::insert($inserttt);
                        if ($data['order_type'] == 'offline'){
                            $center_list = '有从'. $data['send_shi_name'].'发往'.$data['gather_shi_name'].'的整车订单';
                            $push_contnect = array('title' => "赤途承运端",'content' => $center_list , 'payload' => "订单信息");
                            $this->send_push_message($push_contnect,$data['send_shi_name']);
                        }

                       if ($company_id) {
//                           TmsOrderMoney::insert($money);
                           TmsOrderCost::insert($money_company);
                       }
//                        TmsOrderCost::insert($money);
                        $operationing->access_cause='新建订单';
                        $operationing->operation_type='create';

                    }

                    $operationing->table_id=$old_info?$self_id:$data['self_id'];
                    $operationing->old_info=$old_info;
                    $operationing->new_info=$data;

                    if($id){
                        $msg['code'] = 200;
                        $msg['order_id'] = $order_id;
                        $msg['order_id_show'] = substr($order_id,15);
                        $msg['msg'] = "操作成功";
                        return $msg;
                    }else{
                        $msg['code'] = 302;
                        $msg['msg'] = "操作失败";
                        return $msg;
                    }

                    break;
            }

            /***二次效验结束**/
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



    /***    订单禁用/启用      /tms/order/orderUseFlag
     */
    public function orderUseFlag(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_order';
        $medol_name='TmsOrder';
        $self_id=$request->input('self_id');
        $flag='useFlag';
        //$self_id='car_202012242220439016797353';

        $status_info=$status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);

        $operationing->access_cause='启用/禁用';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$status_info['old_info'];
        $operationing->new_info=$status_info['new_info'];
        $operationing->operation_type=$flag;

        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];

        return $msg;


    }

    /***    订单删除     /tms/order/orderDelFlag
     */
    public function orderDelFlag(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_order';
        $medol_name='TmsOrder';
        $self_id=$request->input('self_id');
        $flag='delFlag';
        //$self_id='car_202012242220439016797353';

        $status_info=$status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);

        $operationing->access_cause='删除';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$status_info['old_info'];
        $operationing->new_info=$status_info['new_info'];
        $operationing->operation_type=$flag;

        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];

        return $msg;
    }

    /***    拿去订单数据     /tms/order/getOrder
     */
    public function  getOrder(Request $request){
        /** 接收数据*/
        $warehouse_id        =$request->input('warehouse_id');

        /*** 虚拟数据**/
        //$warehouse_id='ware_202006012159456407842832';

        $where=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
            ['warehouse_id','=',$warehouse_id],
        ];
        $select=['self_id','area','group_code','group_name','warehouse_id','warehouse_name','warm_id','warm_name'];
    //dd($where);
        $data['wms_warehouse_area']=WmsWarehouseArea::where($where)->select($select)->get();
        foreach ($data['wms_warehouse_area'] as $k=>$v) {
            $v->warm_name=warm($v->wmsWarm->warm_name,$v->wmsWarm->min_warm,$v->wmsWarm->max_warm);
            }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;

        //dd($msg);
        return $msg;
    }

    /***    订单导入     /tms/order/import
     */
    public function import(Request $request){
        $table_name         ='wms_warehouse_area';
        $now_time           = date('Y-m-d H:i:s', time());

        $operationing       = $request->get('operationing');//接收中间件产生的参数
        $operationing->access_cause     ='导入创建车辆';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='import';

        $user_info          = $request->get('user_info');//接收中间件产生的参数


        /** 接收数据*/
        $input              =$request->all();
        $importurl          =$request->input('importurl');
        $warm_id            =$request->input('warm_id');
        $file_id            =$request->input('file_id');
    //dd($input);
        /****虚拟数据
        $input['importurl']     =$importurl="uploads/2020-10-13/车辆导入文件范本.xlsx";
        $input['warm_id']       =$warm_id='warm_202012171029290396683997';
         ***/
        $rules = [
            'warm_id' => 'required',
            'importurl' => 'required',
        ];
        $message = [
            'warm_id.required' => '请选择温区',
            'importurl.required' => '请上传文件',
        ];
        $validator = Validator::make($input, $rules, $message);
        if ($validator->passes()) {

            /**发起二次效验，1效验文件是不是存在， 2效验文件中是不是有数据 3,本身数据是不是重复！！！* */
            if (!file_exists($importurl)) {
                $msg['code'] = 301;
                $msg['msg'] = '文件不存在';
                return $msg;
            }

            $res = Excel::toArray((new Import),$importurl);
            //dump($res);
            $info_check=[];
            if(array_key_exists('0', $res)){
                $info_check=$res[0];
            }

            //dump($info_check);

            /**  定义一个数组，需要的数据和必须填写的项目
             键 是EXECL顶部文字，
             * 第一个位置是不是必填项目    Y为必填，N为不必须，
             * 第二个位置是不是允许重复，  Y为允许重复，N为不允许重复
             * 第三个位置为长度判断
             * 第四个位置为数据库的对应字段
             */
            $shuzu=[
               '车辆' =>['Y','N','64','area'],
                ];
            $ret=arr_check($shuzu,$info_check);

            //dump($ret);
            if($ret['cando'] == 'N'){
                $msg['code'] = 304;
                $msg['msg'] = $ret['msg'];
                return $msg;
            }

            $info_wait=$ret['new_array'];


            $where_check=[
                ['delete_flag','=','Y'],
                ['self_id','=',$warm_id],
            ];

            $info= WmsWarm::where($where_check)->select('self_id','warm_name','warehouse_id','warehouse_name','group_code','group_name')->first();
            if(empty($info)){
                $msg['code'] = 305;
                $msg['msg'] = '温区不存在';
                return $msg;
            }
            /** 二次效验结束**/

            $datalist=[];       //初始化数组为空
            $cando='Y';         //错误数据的标记
            $strs='';           //错误提示的信息拼接  当有错误信息的时候，将$cando设定为N，就是不允许执行数据库操作
            $abcd=0;            //初始化为0     当有错误则加1，页面显示的错误条数不能超过$errorNum 防止页面显示不全1
            $errorNum=50;       //控制错误数据的条数
            $a=2;

            //dump($info_wait);
            /** 现在开始处理$car***/
            foreach($info_wait as $k => $v){
                $where=[
                    ['delete_flag','=','Y'],
                    ['area','=',$v['area']],
                    ['warehouse_id','=',$info->warehouse_id],
                ];

                $area_info = WmsWarehouseArea::where($where)->value('group_code');

                if($area_info){
                    if($abcd<$errorNum){
                        $strs .= '数据中的第'.$a."行车辆已存在".'</br>';
                        $cando='N';
                        $abcd++;
                    }
                }

                $list=[];
                if($cando =='Y'){
                    $list['self_id']            =generate_id('area_');
                    $list['area']               = $v['area'];
                    $list['warehouse_id']       = $info->warehouse_id;
                    $list['warehouse_name']     = $info->warehouse_name;
                    $list['group_code']         = $info->group_code;
                    $list['group_name']         = $info->group_name;
                    $list['create_user_id']     = $user_info->admin_id;
                    $list['create_user_name']   = $user_info->name;
                    $list['create_time']        = $list['update_time']=$now_time;
                    $list['warm_id']            = $info->self_id;
                    $list['warm_name']          = $info->warm_name;
                    $list['file_id']            =$file_id;
                    $datalist[]=$list;
                }

                $a++;
            }


            $operationing->new_info=$datalist;

            //dump($operationing);

           // dd($datalist);

            if($cando == 'N'){
                $msg['code'] = 306;
                $msg['msg'] = $strs;
                return $msg;
            }
            $count=count($datalist);
            $id= WmsWarehouseArea::insert($datalist);




            if($id){
                $msg['code']=200;
                /** 告诉用户，你一共导入了多少条数据，其中比如插入了多少条，修改了多少条！！！*/
                $msg['msg']='操作成功，您一共导入'.$count.'条数据';

                return $msg;
            }else{
                $msg['code']=307;
                $msg['msg']='操作失败';
                return $msg;
            }
        }else{
            $erro = $validator->errors()->all();
            $msg['msg'] = null;
            foreach ($erro as $k => $v) {
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            $msg['code'] = 300;
            return $msg;
        }


    }

    /***    订单明细详情     /tms/order/details
     */
    public function  details(Request $request,Details $details){
        $tms_money_type    =array_column(config('tms.tms_money_type'),'name','key');
        $tms_order_status_type    =array_column(config('tms.tms_order_status_type'),'pay_status_text','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        $tms_control_type        =array_column(config('tms.tms_control_type'),'name','key');
        $self_id=$request->input('self_id');
//        $self_id = 'order_202106231710070766328312';

        $select = ['self_id','group_code','group_name','company_name','create_user_name','create_time','use_flag','order_type','order_status','gather_address_id','gather_contacts_id','gather_name','gather_tel','gather_sheng','gather_shi','gather_qu','gather_time','send_time',
            'gather_address','send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_shi','send_qu','send_address','remark','total_money','price','pick_money','send_money','good_name','good_number','good_weight','good_volume','pick_flag','send_flag','info'
            ,'good_info','clod','line_info','pay_type','pay_state'];

        $list_select=['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
            ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','line_gather_address_id','line_gather_contacts_id','line_gather_name','line_gather_tel',
            'line_gather_sheng','line_gather_shi','line_gather_qu','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name' , 'line_gather_address','remark',
            'line_gather_address_longitude','line_gather_address_latitude','line_send_address_id','line_send_contacts_id','line_send_name','line_send_tel', 'line_send_sheng','line_send_shi',
            'line_send_qu','line_send_sheng_name','line_send_shi_name','line_send_qu_name','line_send_address','line_send_address_longitude','line_send_address_latitude','clod','pick_flag','send_flag',
            'pay_type','order_id','pay_status','pay_time','receiver_type','gather_name','gather_tel','send_name','send_tel','receipt_flag'
        ];
        $where = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];

        $select1=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
            'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address',
            'send_sheng_name','send_shi_name','send_qu_name','send_address','order_id',
            'good_info','good_number','good_weight','good_volume','total_money','on_line_money'];

        $select2 = ['self_id','carriage_id','order_dispatch_id'];
        $select3 = ['self_id','company_id','company_name','carriage_flag','total_money'];
        $select4 = ['carriage_id','car_number','contacts','tel','price','car_id'];
        $selectList = ['self_id','receipt','order_id','total_user_id','group_code','group_name'];
        $info = TmsOrder::with(['TmsOrderDispatch' => function($query) use($list_select,$selectList,$select1,$select2,$select3,$select4){
            $query->select($list_select);
            $query->with(['tmsCarriageDispatch'=>function($query)use($select1,$select2,$select3,$select4){
                $query->where('delete_flag','=','Y');
                $query->select($select2);
                $query->with(['tmsCarriage'=>function($query)use($select3){
                    $query->where('delete_flag','=','Y');
                    $query->select($select3);
                }]);
                $query->with(['tmsCarriageDriver'=>function($query)use($select4){
                    $query->where('delete_flag','=','Y');
                    $query->select($select4);
                }]);
            }]);
            $query->with(['tmsReceipt'=>function($query)use($selectList) {
                $query->where('delete_flag', '=', 'Y');
                $query->select($selectList);
            }]);

        }])->where($where)->select($select)->first();

//        DD($info->toArray());


        if($info){
            $info->order_status_show = $tms_order_status_type[$info->order_status] ?? null;
            $info->order_type_show   = $tms_order_type[$info->order_type] ??null;
            if ($info->pay_state == 'Y' && $info->pay_type == 'offline'){
                $info->pay_state = '已付款';
            }elseif($info->pay_type == 'online'){
                $info->pay_state = '已付款';
            }elseif($info->pay_type == 'offline' && $info->pay_state == 'N'){
                $info->pay_state = '未付款';
            }elseif(!$info->pay_type && $info->pay_state == 'N'){
                $info->pay_state = '未付款';
            }
            $receipt_info = [];
            $receipt_info_list= [];
            foreach ($info->TmsOrderDispatch as $k =>$v){

                $v->pay_type_show = $tms_pay_type[$v->pay_type]??null;
                $v->good_info     = json_decode($v->good_info,true);
                $temperture = json_decode($v->clod,true);
                foreach ($temperture as $key => $value){
                    $temperture[$key] = $tms_control_type[$value];
                }
                $info->receipt_flag = $v->receipt_flag;
                $v->temperture = implode(',',$temperture);
                if ($v->tmsReceipt){
//                    $info->receipt = json_decode($v->tmsReceipt->receipt,true);
                    $receipt_info = img_for($v->tmsReceipt->receipt,'more');
                    $receipt_info_list[] = $receipt_info;

                }
                $car_list = [];
                if ($v->tmsCarriageDispatch){
                    if ($v->tmsCarriageDispatch->tmsCarriageDriver){
                        foreach ($v->tmsCarriageDispatch->tmsCarriageDriver as $kk => $vv){
                            $carList['car_id'] = $vv->car_id;
                            $carList['car_number'] = $vv->car_number;
                            $carList['tel'] = $vv->tel;
                            $carList['contacts'] = $vv->contacts;
                            $car_list[] = $carList;
                        }
                        $info->car_info = $car_list;
                    }
                }
            }

            /** 零担发货收货仓**/
            $line_info = [];
            $pick_store_info = [];
            $send_store_info = [];
            if($info->line_info){
                $info->line_info = json_decode($info->line_info,true);
//                dd($info->line_info);
                $pick_store['pick_store'] = $info->line_info['send_sheng_name'].$info->line_info['send_shi_name'].$info->line_info['send_qu_name'].$info->line_info['send_address'];
                $pick_store['contacts']   = $info->line_info['send_name'];
                $pick_store['tel']   = $info->line_info['send_tel'];
                $pick_store_info[] = $pick_store;
                $send_store['send_store'] = $info->line_info['gather_sheng_name'].$info->line_info['gather_shi_name'].$info->line_info['gather_qu_name'].$info->line_info['gather_address'];
                $send_store['contacts']   = $info->line_info['gather_name'];
                $send_store['tel']   = $info->line_info['gather_tel'];
                $send_store_info[] = $send_store;
                $info->shift_number = $info->line_info['shift_number'];
                $info->trunking = $info->line_info['trunking'].'天';
            }
            $info->pick_store_info = $pick_store_info;
            $info->send_store_info = $send_store_info;
            $info->receipt = $receipt_info_list;
            $order_info              = json_decode($info->info,true);
            $send_info = [];
            $gather_info = [];
            foreach ($order_info as $kkk => $vvv){
//                dd($vvv);
                if ($info->pick_flag == 'Y'){
                    $send['address_info'] = $vvv['send_sheng_name'].$vvv['send_shi_name'].$vvv['send_qu_name'];
                    $send['contacts']     = $vvv['send_name'];
                    $send['tel']     = $vvv['send_tel'];
                    $send_info[] =$send;
                }

                if ($info->send_flag == 'Y'){
                    $gather['address_info'] = $vvv['gather_sheng_name'].$vvv['gather_shi_name'].$vvv['gather_qu_name'];
                    $gather['contacts'] = $vvv['gather_name'];
                    $gather['tel']  = $vvv['gather_tel'];
                    $gather['good_number'] = $vvv['good_number'];
                    $gather['good_weight'] = $vvv['good_weight'];
                    $gather['good_volume'] = $vvv['good_volume'];
                    $gather['good_cold']   = $tms_control_type[$vvv['clod']];
                    $gather['good_name']   = $vvv['good_name'];
                    $gather_info[] = $gather;
                }
                $vvv['clod'] =  $tms_control_type[$vvv['clod']];
                if ($info->order_type == 'vehicle'){
                    $order_info[$kkk]['good_weight'] = ($vvv['good_weight']/1000).'吨';
                }
            }
            $info->info = $order_info;
//            dd($send_info,$gather_info);
            $info->send_info = $send_info;
            $info->gather_info = $gather_info;
            $info->self_id_show = substr($info->self_id,15);
            $info->good_info         = json_decode($info->good_info,true);
            $info->clod              = json_decode($info->clod,true);
            /** 如果需要对数据进行处理，请自行在下面对 $info 进行处理工作*/
            $info->total_money = number_format($info->total_money/100, 2);
            $info->price       = number_format($info->price/100, 2);
            $info->pick_money  = number_format($info->pick_money/100, 2);
            $info->send_money  = number_format($info->send_money/100, 2);
            if ($info->order_type == 'vehicle'){
                $info->good_weight = ($info->good_weight/1000).'吨';
            }
            $info->color = '#FF7A1A';
            $info->order_id_show = '订单编号'.$info->self_id_show;
            $order_details = [];
            $receipt_list = [];
            $car_info = [];
            $order_details1['name'] = '订单金额';
            $order_details1['value'] = '¥'.$info->total_money;
            $order_details1['color'] = '#FF7A1A';
            $order_details2['name'] = '是否付款';
            $order_details2['value'] = $info->pay_state;
            $order_details2['color'] = '#FF7A1A';

            $order_details4['name'] = '收货时间';
            $order_details4['value'] = $info->gather_time;
            $order_details4['color'] = '#000000';
            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl'){
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
                $order_details3['name'] = '提货时间';
                $order_details3['value'] = $info->send_time;
                $order_details3['color'] = '#000000';
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
            $order_details7['name'] = '班次号';
            $order_details7['value'] = $info->shift_number;
            $order_details7['color'] = '#000000';
            $order_details8['name'] = '时效';
            $order_details8['value'] = $info->trunking;
            $order_details8['color'] = '#000000';

            $order_details9['name'] = '运输信息';
            $order_details9['value'] = $info->car_info;

            $order_details10['name'] = '回单信息';
            $order_details10['value'] = $info->receipt;

            $order_details[] = $order_details1;
            $order_details[]= $order_details2;

            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl'){
                $order_details[] = $order_details3;
                $order_details[]= $order_details4;
                $order_details[]= $order_details5;
                $order_details[]= $order_details6;
            }else{
                $order_details[]= $order_details7;
                $order_details[]= $order_details8;
                $order_details[]= $order_details3;
                $order_details[]= $order_details4;
                $order_details[]= $order_details5;
                $order_details[]= $order_details6;
            }
            if(!empty($info->car_info)){
                $car_info[] = $order_details9;
            }
            if (!empty($info->receipt)){
                $receipt_list[] = $order_details10;
            }
//            dd($info->toArray());
            $data['info']=$info;
            $data['order_details'] = $order_details;
            $data['receipt_list'] = $receipt_list;
            $data['car_info'] = $car_info;
            $log_flag='Y';
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

    /**
     *取消订单(3pl)  /tms/order/orderCancel
     * */
    public function orderCancel(Request $request){
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
             $order = TmsOrder::where('self_id',$order_id)->select(['self_id','order_status','total_money','pay_type','group_code'])->first();
            if ($order->order_status == 3){
                $msg['code'] = 303;
                $msg['msg'] = '该订单已被承接，取消请联系客服';
                return $msg;
            }
             if ($order->order_status == 4 || $order->order_status == 5){
                $msg['code'] = 303;
                $msg['msg'] = '此订单运输中不可以取消';
                return $msg;
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
            $id = TmsOrder::where('self_id',$order_id)->update($order_update);
            /*** 修改可调度订单为已取消**/
            $dispatch_list = TmsOrderDispatch::where('order_id',$order_id)->select('self_id')->get();
            $dispatch_id_list = array_column($dispatch_list->toArray(),'self_id');
            TmsOrderDispatch::whereIn('self_id',$dispatch_id_list)->update($order_update);
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
            $money_update['delete_flag'] = 'N';
            $money_update['update_time'] = $now_time;
            $money_list = TmsOrderCost::where('order_id',$order_id)->select('self_id')->get();
            $money_id_list = array_column($money_list->toArray(),'self_id');
            TmsOrderCost::where($money_where)->whereIn('self_id',$money_id_list)->select(['self_id','money','delete_flag'])->update($money_update);

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
    确认完成（内部订单） /tms/order/orderDone
     **/
    public function orderDone(Request $request){
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
        $input['self_id']       = $self_id       = 'order_202102251709515094516145';
         **/

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where = [
                ['self_id','=',$self_id],
                ['order_status','!=',7]
            ];
            $select = ['self_id','order_status','total_money'];
            $order = TmsOrder::where($where)->select($select)->first();
            if ($order->order_status == 6){
                $msg['code'] = 301;
                $msg['msg'] = '订单已完成';
                return $msg;
            }
            $update['update_time'] = $now_time;
            $update['order_status'] = 6;
            $id = TmsOrder::where($where)->update($update);

            /** 查找所有的运输单 修改运输状态**/
            $TmsOrderDispatch = TmsOrderDispatch::where('order_id',$self_id)->select('self_id')->get();
            if ($TmsOrderDispatch){
                $dispatch_list = array_column($TmsOrderDispatch->toArray(),'self_id');
                $orderStatus = TmsOrderDispatch::where('delete_flag','=','Y')->whereIn('self_id',$dispatch_list)->update($update);

                /*** 订单完成后，如果订单是在线支付，添加运费到承接司机或3pl公司余额 **/
                if ($orderStatus){
//                    if ($order->pay_type == 'online'){
//                        dd($dispatch_list);
                    foreach ($dispatch_list as $key => $value){

                        $carriage_order = TmsOrderDispatch::where('self_id','=',$value)->first();
                        $idit = substr($carriage_order->receiver_id,0,5);
                        if ($idit == 'user_'){
                            $wallet_where = [
                                ['total_user_id','=',$carriage_order->receiver_id]
                            ];
                            $data['wallet_type'] = 'user';
                            $data['total_user_id'] = $carriage_order->receiver_id;
                        }else{
                            $wallet_where = [
                                ['group_code','=',$carriage_order->receiver_id]
                            ];
                            $data['wallet_type'] = '3PLTMS';
                            $data['group_code'] = $carriage_order->receiver_id;
                        }
                        $wallet = UserCapital::where($wallet_where)->select(['self_id','money'])->first();

                        $money['money'] = $wallet->money + $carriage_order->on_line_money;
                        $data['money'] = $carriage_order->on_line_money;
                        if ($carriage_order->group_code == $carriage_order->receiver_id){
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

    /***
     货主公司下单 /tms/order/add_order
     **/
    public function add_order(Request $request,Tms $tms){
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_order';

        $operationing->access_cause     ='创建/修改订单';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $project_type = $user_info->type;
//        dump($user_info);
        $input      =$request->all();
//        /** 接收数据*/
        $self_id       = $request->input('self_id');
        $order_type    = $request->input('order_type');//订单类型 vehicle  lcl   line
        $line_id       = $request->input('line_id');//线路 id
        $pick_flag     = $request->input('pick_flag');//是否提货、装货
        $send_flag     = $request->input('send_flag');//是否配送、卸货
        $pick_money    = $request->input('pick_money');//提货、装货费
        $send_money    = $request->input('send_money');//配送、卸货费
        $price         = $request->input('price');//运输费
        $line_price    = $request->input('line_price')??0;//运输费
        $total_money   = $request->input('total_money');//总计费用
        $good_name_n   = $request->input('good_name');
        $good_number_n = $request->input('good_number');
        $good_weight_n = $request->input('good_weight');
        $good_volume_n = $request->input('good_volume');
        $dispatcher    = $request->input('dispatcher') ?? [];
        $clod          = $request->input('clod');
        $more_money    = $request->input('more_money') ? $request->input('more_money') - 0 : null ;//多点费用
        $gather_time    = $request->input('gather_time')??null;
        $send_time    = $request->input('send_time')??null;
        $pay_type    = $request->input('pay_type');
        $car_type    = $request->input('car_type')??''; //车型
        $remark    = $request->input('remark')??''; //备注
        $app_flag    = $request->input('app_flag')??''; //备注
        $depart_time    = $request->input('depart_time')??null; //干线发车时间
        $reduce_price   = $request->input('reduce_price');
        $payer          = $request->input('payer');//付款方：发货人     收货人 receiver
        /*** 虚拟数据
        //$input['self_id']   = $self_id='';
        $input['order_type']  = $order_type='line';  //vehicle  lcl   line
        $input['line_id']     = $line_id='line_202105171354383118797387';
        $input['pick_flag']   = $pick_flag='N';
        $input['send_flag']   = $send_flag='Y';
        $input['pick_money']  = $pick_money='0';
        $input['price']       = $price='200';
        $input['send_money']  = $send_money='0';
        $input['total_money'] = $total_money='300';
        $input['more_money']  = $more_money=20;//多点费用
        $input['good_name']   = $good_name_n='GDFG';
        $input['good_number'] = $good_number_n=20;
        $input['good_weight'] = $good_weight_n=5000;
        $input['good_volume'] = $good_volume_n=5;
        $input['clod']        = $clod='refrigeration';
        $input['gather_time']        = $gather_time='2021-05-02 15:00';
        $input['send_time']        = $send_time='2021-04-30 15:00';
        $input['pay_type']        = $pay_type='offline';
        $input['car_type']        = $car_type='type_202102051755118039490396';
        $input['remark']        = $remark='';
        $input['depart_time'] = $depart_time = '2021-04-30 15:00';

        $input['dispatcher']  = $dispatcher = [
        '0'=>[
        'send_address_id'=>'',
        'send_qu'=>'43',
        'send_qu_name'=>'嘉定区',
        'send_shi_name'=>'上海市',
        'send_address'=>'小浪底001',
        'send_contacts_id'=>'',
        'send_address_longitude'=>'121.471732',
        'send_address_latitude'=>'31.231518',
        'send_name'=>'张三001',
        'send_tel'=>'001',
        'good_name'=>'产品名称001',
        'good_number'=>'10',
        'good_weight'=>'55',
        'good_volume'=>'12',
        'clod'=>'refrigeration',
        'gather_address_id'=>'',
        'gather_qu'=>'43',
        'gather_qu_name'=>'东城区',
        'gather_shi_name'=>'北京市',
        'gather_address'=>'小浪底001_ga',
        'gather_address_longitude'=>'121.471732',
        'gather_address_latitude'=>'31.231518',
        'gather_name'=>'张三',
        'gather_tel'=>'123456',
        ],

        '1'=>[
        'send_address_id'=>'',

        'send_qu'=>'43',
        'send_qu_name'=>'',
        'send_shi_name'=>'',
        'send_address'=>'小浪底002',
        'send_address_longitude'=>'121.471732',
        'send_address_latitude'=>'31.231518',
        'send_name'=>'张三002',
        'send_tel'=>'002',
        'good_name'=>'产品名称002',
        'good_number'=>'20',
        'good_weight'=>'55',
        'good_volume'=>'13',
        'clod'=>'freeze',
        'gather_address_id'=>'',
        'gather_qu'=>'43',
        'gather_qu_name'=>'',
        'gather_shi_name'=>'',
        'gather_address'=>'小浪底002_ga',
        'gather_address_longitude'=>'121.471732',
        'gather_address_latitude'=>'31.231518',
        'gather_name'=>'张三002_ga',
        'gather_tel'=>'002_ga',
        ],

        '2'=>[
        'send_address_id'=>'',
        'send_qu'=>'43',
        'send_qu_name'=>'',
        'send_shi_name'=>'',
        'send_address'=>'小浪底003',
        'send_address_longitude'=>'121.471732',
        'send_address_latitude'=>'31.231518',
        'send_name'=>'张三003',
        'send_tel'=>'003',
        'good_name'=>'产品名称003',
        'good_number'=>'50.22',
        'good_weight'=>'55.22',
        'good_volume'=>'14.22',
        'clod'=>'freeze',
        'gather_address_id'=>'',
        'gather_qu'=>'43',
        'gather_qu_name'=>'',
        'gather_shi_name'=>'',
        'gather_address'=>'小浪底003_ga',
        'gather_address_longitude'=>'121.471732',
        'gather_address_latitude'=>'31.231518',
        'gather_name'=>'张三003_ga',
        'gather_tel'=>'123456_ga',
        ],
        ];
         **/
        $rules = [
            'order_type'=>'required',
        ];
        $message = [
            'order_type.required'=>'请选择订单类型',
        ];


        switch ($project_type){
            case 'company':
                $company_id     = null;
                $company_name   = null;
                $group_code     = $user_info->group_code;
                $group_name     = $user_info->group_name;
                $total_user_id  = null;
                $receiver_id    = null;
                break;
            case 'TMS3PL':
                $company_id     = null;
                $company_name   = null;
                $group_code     = $user_info->group_code;
                $group_name     = $user_info->group_name;
                $total_user_id  = null;
                $receiver_id    = null;
                break;
            default:
                $company_id     = null;
                $company_name   = null;
                $group_code     = null;
                $group_name     = null;
                $total_user_id  = null;
                $receiver_id    = null;
                break;
        }
//        $company_id     = null;
//        $company_name   = null;
//        $group_code     = $user_info->group_code;
//        $group_name     =$user_info->group_name;
//        $total_user_id = null;
//        $receiver_id = null;


        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where_group=[
                ['delete_flag','=','Y'],
                ['self_id','=',$group_code],
            ];
            $group_info    =SystemGroup::where($where_group)->select('group_code','group_name')->first();
            /***开始做二次效验**/
            if ($order_type == 'vehicle' || $order_type == 'lcl') {
                if (count($dispatcher) == 0) {
                    $msg['code'] = 302;
                    $msg['msg'] = '请填写订单信息！';
                    return $msg;
                }
            }
            if ($order_type == 'line') {
                if ($pick_flag =='N' && $send_flag =='N') {
                    if (empty($good_name_n)) {
                        $msg['code'] = 306;
                        $msg['msg'] = '货物名称不能为空！';
                        return $msg;
                    }

                    if (empty($good_number_n) || $good_number_n <= 0) {
                        $msg['code'] = 307;
                        $msg['msg'] = '货物件数错误！';
                        return $msg;
                    }

                    if (empty($good_weight_n) || $good_weight_n <= 0) {
                        $msg['code'] = 308;
                        $msg['msg'] = '货物重量错误！';
                        return $msg;
                    }

                    if (empty($good_volume_n) || $good_volume_n <= 0) {
                        $msg['code'] = 309;
                        $msg['msg'] = '货物体积错误！';
                        return $msg;
                    }

                    if (empty($clod)) {
                        $msg['code'] = 309;
                        $msg['msg'] = '请选择温度！';
                        return $msg;
                    }
                }

                $send_t = '提货';
                $pick_t = '配送';
            } else {
                $send_t = '发货';
                $pick_t = '收货';
            }
            /** 处理一下发货地址  及联系人**/
            foreach ($dispatcher as $k => $v){
                if ($order_type == 'vehicle' || $order_type == 'lcl' || ($order_type == 'line' && $send_flag == 'Y')) {
                    $gather_address = $tms->address_contact($v['gather_address_id'],$v['gather_qu'],$v['gather_address'],$v['gather_name'],$v['gather_tel'],$group_info,$user_info,$now_time);

                    if(empty($gather_address)){
                        $msg['code'] = 303;
                        $msg['msg'] = $pick_t.'地址不存在';
                        return $msg;
                    }
                } else if($order_type == 'line' && $send_flag == 'N') {
                    $gather_address = [
                        'self_id'           => '',
                        'sheng'             => '',
                        'sheng_name'        => '',
                        'shi'               => '',
                        'shi_name'          => '',
                        'qu'                => '',
                        'qu_name'           => '',
                        'address'           => '',
                        'longitude'         => '',
                        'dimensionality'    => '',
                        'contacts'  => '',
                        'tel'  => ''
                    ];
                    $gather_address = (Object)$gather_address;
                }
                if ($order_type == 'vehicle' || $order_type == 'lcl' || ($order_type == 'line' && $pick_flag == 'Y')) {
                    $send_address=$tms->address_contact($v['send_address_id'],$v['send_qu'],$v['send_address'],$v['send_name'],$v['send_tel'],$group_info,$user_info,$now_time);
                    if(empty($send_address)){
                        $msg['code'] = 303;
                        $msg['msg'] = $send_t.'地址不存在';
                        return $msg;
                    }
                } else if($order_type == 'line' && $pick_flag == 'N') {
                    $send_address = [
                        'self_id'        => '',
                        'sheng'          => '',
                        'sheng_name'     => '',
                        'shi'            => '',
                        'shi_name'       => '',
                        'qu'             => '',
                        'qu_name'        => '',
                        'address'        => '',
                        'longitude'      => '',
                        'dimensionality' => '',
                        'contacts' => '',
                        'tel'      => ''
                    ];
                    $send_address = (Object)$send_address;
                }
                if (empty($v['good_name'])) {
                    $msg['code'] = 306;
                    $msg['msg']  = '货物名称不能为空！';
                    return $msg;
                }

                if (empty($v['good_number']) || $v['good_number'] <= 0) {
                    $msg['code'] = 307;
                    $msg['msg']  = '货物件数错误！';
                    return $msg;
                }

                if (empty($v['good_weight']) || $v['good_weight'] <= 0) {
                    $msg['code'] = 308;
                    $msg['msg']  = '货物重量错误！';
                    return $msg;
                }

                if (empty($v['good_volume']) || $v['good_volume'] <= 0) {
                    $msg['code'] = 309;
                    $msg['msg']  = '货物体积错误！';
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
            /** 处理一下发货地址  及联系人 结束**/

            /** 开始处理正式的数据*/
            $lki    = [];
            $lki2   = [];
            $gather = [];               //定义一个收货地址的集合
            $send   = [];                 //定义一个送货地址的集合
            $good_name   = [];
            $good_number = 0;
            $good_weight = 0;
            $good_volume = 0;
            $clodss = [];

            $abccxxxxx = [];
            foreach ($dispatcher as $k => $v){
                $gather[] = $v['gather_address_id'];
                $send[] = $v['send_address_id'];
                $good_number+=$v['good_number'];
                $good_weight+=$v['good_weight'];
                $good_volume+=$v['good_volume'];
                $good_name[] = $v['good_name'];
                $clodss[] = $v['clod'];
                $abcc222['good_name']   = $v['good_name'];
                $abcc222['good_number'] = $v['good_number'];
                $abcc222['good_weight'] = $v['good_weight'];
                $abcc222['good_volume'] = $v['good_volume'];
                $abcc222['clod']        = $v['clod'];
                $abccxxxxx[] = $abcc222;
            }

            if($pick_flag == 'N' && $send_flag == 'N' && $order_type == 'line'){
                $abcc222 = [];
                $clodss = [];
                $abccxxxxx = [];
                $abcc222['good_name']   = $good_name_n;
                $abcc222['good_number'] = $good_number_n;
                $abcc222['good_weight'] = $good_weight_n;
                $abcc222['good_volume'] = $good_volume_n;
                $abcc222['clod']        = $clod;
                $abccxxxxx[] = $abcc222;
                $good_number = $good_number_n;
                $good_weight = $good_weight_n;
                $good_volume = $good_volume_n;
                $clodss[] = $clod;
            }

            $gather = array_unique($gather);
            $send   = array_unique($send);
            $clodss = array_unique($clodss);

            foreach ($gather as $k => $v){
                $lki[$k]['good_number'] = 0;
                $lki[$k]['good_weight'] = 0;
                $lki[$k]['good_volume'] = 0;
                $abccxx = [];
                $clod = [];
                foreach ($dispatcher as $kk => $vv){
                    if($v == $vv['gather_address_id']){
                        $lki[$k]['good_number']+=$vv['good_number'];
                        $lki[$k]['good_weight']+=$vv['good_weight'];
                        $lki[$k]['good_volume']+=$vv['good_volume'];
                        $lki[$k]['gather_address_id'] = $vv['gather_address_id'];

                        $lki[$k]['gather_qu'] = $vv['gather_qu'];
                        $lki[$k]['gather_qu_name'] = $vv['gather_qu_name'];
                        $lki[$k]['gather_address'] = $vv['gather_address'];
                        $lki[$k]['gather_address_longitude'] = $vv['gather_address_longitude'];
                        $lki[$k]['gather_address_latitude'] = $vv['gather_address_latitude'];
                        $lki[$k]['gather_sheng'] = $vv['gather_sheng'];
                        $lki[$k]['gather_sheng_name'] = $vv['gather_sheng_name'];
                        $lki[$k]['gather_shi'] = $vv['gather_shi'];
                        $lki[$k]['gather_shi_name'] = $vv['gather_shi_name'];
                        $lki[$k]['gather_contacts_name'] = $vv['gather_contacts_name'];
                        $lki[$k]['gather_contacts_tel'] = $vv['gather_contacts_tel'];
                        $abcc['good_name'] = $vv['good_name'];
                        $abcc['good_number'] = $vv['good_number'];
                        $abcc['good_weight'] = $vv['good_weight'];
                        $abcc['good_volume'] = $vv['good_volume'];
                        $abcc['clod']        = $vv['clod'];
                        $abccxx[] = $abcc;
                        $clod[] = $vv['clod'];
                    }
                }
                $lki[$k]['clod'] = $clod;
                $lki[$k]['good_josn'] = json_encode($abccxx,JSON_UNESCAPED_UNICODE);
            }
    //            dd($lki);
            $good_info = json_encode($abccxxxxx,JSON_UNESCAPED_UNICODE);

            foreach ($send as $k => $v){
                $lki2[$k]['good_number'] = 0;
                $lki2[$k]['good_weight'] = 0;
                $lki2[$k]['good_volume'] = 0;
                $abccxx2 = [];
                $clod2 = [];
                foreach ($dispatcher as $kk => $vv){
                    if($v == $vv['send_address_id']){
                        $lki2[$k]['good_number']+=$vv['good_number'];
                        $lki2[$k]['good_weight']+=$vv['good_weight'];
                        $lki2[$k]['good_volume']+=$vv['good_volume'];
                        $lki2[$k]['send_address_id'] = $vv['send_address_id'];

                        $lki2[$k]['send_qu'] = $vv['send_qu'];
                        $lki2[$k]['send_qu_name'] = $vv['send_qu_name'];
                        $lki2[$k]['send_address'] = $vv['send_address'];
                        $lki2[$k]['send_address_longitude'] = $vv['send_address_longitude'];
                        $lki2[$k]['send_address_latitude'] = $vv['send_address_latitude'];
                        $lki2[$k]['send_sheng'] = $vv['send_sheng'];
                        $lki2[$k]['send_sheng_name'] = $vv['send_sheng_name'];
                        $lki2[$k]['send_shi'] = $vv['send_shi'];
                        $lki2[$k]['send_shi_name'] = $vv['send_shi_name'];
                        $lki2[$k]['send_contacts_name'] = $vv['send_contacts_name'];
                        $lki2[$k]['send_contacts_tel'] = $vv['send_contacts_tel'];
                        $abcc2['good_name']  = $vv['good_name'];
                        $abcc2['good_number'] = $vv['good_number'];
                        $abcc2['good_weight'] = $vv['good_weight'];
                        $abcc2['good_volume'] = $vv['good_volume'];
                        $abcc2['clod']        = $vv['clod'];
                        $abccxx2[] = $abcc2;
                        $clod2[] = $vv['clod'];
                    }
                }

                $lki2[$k]['good_josn'] = json_encode($abccxx2,JSON_UNESCAPED_UNICODE);
                $lki2[$k]['clod'] = $clod2;
            }

            /***现在处理收货地址的控制**/
            $order_id = generate_id('order_');
            /***现在处理费用的部分控制**/
            $money=[];
            /** 货到付款 **/

            switch ($order_type){
                case 'line':
                    // $line_id= 'line_202101061755043806385994';
                    $where_line = [
                        ['delete_flag','=','Y'],
                        ['self_id','=',$line_id],
                    ];
                    $select_line = ['self_id','shift_number','type','price','use_flag','group_name','type','group_code','special','min_number','max_number','unit_price','start_price','max_price',
                        'pick_price','send_price','pick_type','more_price','send_type','all_weight','all_volume','trunking','control',
                        'send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_sheng_name','send_shi','send_shi_name',
                        'send_qu','send_qu_name','send_address','send_address_longitude','send_address_latitude',
                        'gather_address_id','gather_contacts_id','gather_name','gather_tel','depart_time',
                        'gather_sheng','gather_sheng_name','gather_shi','gather_shi_name','gather_qu','gather_qu_name','gather_address','gather_address_longitude','gather_address_latitude'];
                    $selectList = ['line_id','yuan_self_id'];

                    $line_info = TmsLine::with(['tmsLineList' => function($query)use($selectList,$select_line){
                        $query->where('delete_flag','=','Y');
                        $query->select($selectList);
                        $query->with(['tmsLine' => function($query)use($select_line){
                            $query->select($select_line);
                        }]);
                    }])->where($where_line)->select($select_line)->first();
                    // dd($line_info->toArray());
                    if(empty($line_info)){
                        $msg['code'] = 310;
                        $msg['msg']  = '线路不存在';
                        return $msg;
                    }

                    $inserttt = [];
                    $sort = 1;
                    /** 以上是提货 的调度的地方 **/
                    if($pick_flag == 'Y'){
                        foreach ($lki2 as $k => $v){
                            $list['self_id']                  = generate_id('patch_');
                            $list['order_id']                 = $order_id;
                            $list['company_id']                 = $company_id;
                            $list['company_name']               = $company_name;
                            $list['receiver_id']                = $line_info->group_code;
                            if($user_info->group_code != $line_info['group_code']) {
                                $list['receiver_id'] = '1234';
                            }
                            if ($line_info->special == 1 && $line_info->carriage_group_code){
                                $list['receiver_id']                = $line_info->carriage_group_code;
                            }
                            $list['group_code']                 = $group_code;
                            $list['group_name']                 = $group_name;
                            $list['total_user_id']              = $total_user_id;
    //                            $list['create_user_id']           = $total_user_id;
    //                            $list['create_user_name']         = $token_name;
                            $list['create_time']              = $list['update_time']=$now_time;
                            $list['gather_address_id']        = $line_info['send_address_id'];

                            $list['gather_name']              = $line_info['send_name'];
                            $list['gather_tel']               = $line_info['send_tel'];
                            $list['gather_sheng']             = $line_info['send_sheng'];
                            $list['gather_sheng_name']        = $line_info['send_sheng_name'];
                            $list['gather_shi']               = $line_info['send_shi'];
                            $list['gather_shi_name']          = $line_info['send_shi_name'];
                            $list['gather_qu']                = $line_info['send_qu'];
                            $list['gather_qu_name']           = $line_info['send_qu_name'];
                            $list['gather_address']           = $line_info['send_address'];
                            $list['gather_address_longitude'] = $line_info['send_address_longitude'];
                            $list['gather_address_latitude']  = $line_info['send_address_latitude'];

                            $list['send_address_id']          = $v['send_address_id'];

                            $list['send_name']                = $v['send_contacts_name'];
                            $list['send_tel']                 = $v['send_contacts_tel'];
                            $list['send_sheng']               = $v['send_sheng'];
                            $list['send_sheng_name']          = $v['send_sheng_name'];
                            $list['send_shi']                 = $v['send_shi'];
                            $list['send_shi_name']            = $v['send_shi_name'];
                            $list['send_qu']                  = $v['send_qu'];
                            $list['send_qu_name']             = $v['send_qu_name'];
                            $list['send_address']             = $v['send_address'];
                            $list['send_address_longitude']   = $v['send_address_longitude'];
                            $list['send_address_latitude']    = $v['send_address_latitude'];

                            $list['line_gather_address_id']        = $line_info['send_address_id'];

                            $list['line_gather_name']              = $line_info['send_name'];
                            $list['line_gather_tel']               = $line_info['send_tel'];
                            $list['line_gather_sheng']             = $line_info['send_sheng'];
                            $list['line_gather_sheng_name']        = $line_info['send_sheng_name'];
                            $list['line_gather_shi']               = $line_info['send_shi'];
                            $list['line_gather_shi_name']          = $line_info['send_shi_name'];
                            $list['line_gather_qu']                = $line_info['send_qu'];
                            $list['line_gather_qu_name']           = $line_info['send_qu_name'];
                            $list['line_gather_address']           = $line_info['send_address'];
                            $list['line_gather_address_longitude'] = $line_info['send_address_longitude'];
                            $list['line_gather_address_latitude']  = $line_info['send_address_latitude'];

                            $list['line_send_address_id']          = $v['send_address_id'];

                            $list['line_send_name']                = $v['send_contacts_name'];
                            $list['line_send_tel']                 = $v['send_contacts_tel'];
                            $list['line_send_sheng']               = $v['send_sheng'];
                            $list['line_send_sheng_name']          = $v['send_sheng_name'];
                            $list['line_send_shi']                 = $v['send_shi'];
                            $list['line_send_shi_name']            = $v['send_shi_name'];
                            $list['line_send_qu']                  = $v['send_qu'];
                            $list['line_send_qu_name']             = $v['send_qu_name'];
                            $list['line_send_address']             = $v['send_address'];
                            $list['line_send_address_longitude']   = $v['send_address_longitude'];
                            $list['line_send_address_latitude']    = $v['send_address_latitude'];

                            $list['good_number']              = $v['good_number'];
                            $list['good_weight']              = $v['good_weight'];
                            $list['good_volume']              = $v['good_volume'];
                            $list['dispatch_flag']            = 'Y';
                            $list['sort']                     = $sort++;
                            $list['order_type']               = $order_type;
                            $list['pick_flag']                = $pick_flag;
                            $list['send_flag']                = $send_flag;
                            $list['good_info']                = $v['good_josn'];
                            $list['clod']                     = json_encode($v['clod'],JSON_UNESCAPED_UNICODE);
                            $list['pay_type']                 = $pay_type;
                            $list['remark']                   = $remark;
                            $list['order_status'] = 1;
                            $list['send_time']                = $send_time;
                            $list['gather_time']              = $depart_time;
                            $list['reduce_price']             = 0;
                            $list['total_money']              = $pick_money*100;
                            $list['on_line_money']            = $pick_money*100;
                            if($user_info->group_code != $line_info['group_code']){
                                $list['total_money']              = 200*100;
                                $list['on_line_money']            = 200*100;
                            }
                            if ($k>0){
                                $list['total_money']              = $line_info['more_price'];
                                $list['on_line_money']            = $line_info['more_price'];
                            }
                            if ($project_type == 'customer'){
                                $list['order_status'] = 3;
                            }
                            $list['payer']                   = $payer;
                            $inserttt[]=$list;

                            /** 存储费用 **/
                            if ($pay_type == 'offline'){
                                switch ($project_type){
                                    case 'user':
                                        $money['fk_total_user_id']           = $user_info->total_user_id;
                                        $money['fk_type']                    = 'USER';
                                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                        break;
                                    case 'customer':
                                        $money['shouk_group_code']           = $user_info->group_code;
                                        $money['shouk_type']                 = 'GROUP_CODE';
                                        $money['fk_company_id']              = $user_info->company_id;
                                        $money['fk_type']                    = 'COMPANY';
                                        $money['ZIJ_company_id']             = $user_info->company_id;
                                        break;
                                    case 'company':
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        break;
                                    case 'TMS3PL':
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        break;
                                }
                            }else{
                                switch ($project_type){
                                    case 'user':
                                        if($user_info->group_code == $line_info['group_code']) {
                                            $money_['shouk_group_code'] = $line_info->group_code;
                                            $money_['shouk_type'] = 'GROUP_CODE';
                                            $money_['fk_group_code'] = '1234';
                                            $money_['fk_type'] = 'PLATFORM';
                                            $money_['ZIJ_group_code'] = '1234';
                                            $money_['delete_flag'] = 'N';
                                        }
                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_total_user_id']           = $user_info->total_user_id;
                                        $money['fk_type']                    = 'USER';
                                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'customer':
                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_company_id']             = $user_info->company_id;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'company':
                                        $money_['shouk_group_code']           = $line_info->group_code;
                                        $money_['shouk_type']                 = 'GROUP_CODE';
                                        $money_['fk_group_code']              = '1234';
                                        $money_['fk_type']                    = 'PLATFORM';
                                        $money_['ZIJ_group_code']             = '1234';
                                        $money_['delete_flag']                = 'N';

                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'TMS3PL':
                                        if($user_info->group_code == $line_info['group_code']) {
                                            $money_['shouk_group_code'] = $line_info->group_code;
                                            $money_['shouk_type'] = 'GROUP_CODE';
                                            $money_['fk_group_code'] = '1234';
                                            $money_['fk_type'] = 'PLATFORM';
                                            $money_['ZIJ_group_code'] = '1234';
                                            $money_['delete_flag'] = 'N';
                                        }
                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        $money['delete_flag']                = 'N';
                                        break;
                                }
                            }

                            $money['self_id']                    = generate_id('order_money_');
                            $money['order_id']                   = $order_id;
                            $money['dispatch_id']                = $list['self_id'];
                            $money['create_time']                = $now_time;
                            $money['update_time']                = $now_time;
                            $money['money']                      = $pick_money*100;
                            if($user_info->group_code != $line_info['group_code']) {
                                $money['money'] = 200 * 100;
                            }
                            $money['money_type']                 = 'gather';
                            $money['type']                       = 'out';
                            $money['settle_flag']                = 'W';
                            $money_list[] = $money;

                            $money_['self_id']                    = generate_id('order_money_');
                            $money_['order_id']                   = $order_id;
                            $money_['dispatch_id']                = $list['self_id'];
                            $money_['create_time']                = $now_time;
                            $money_['update_time']                = $now_time;
                            $money_['money']                      = $pick_money*100;
                            if($user_info->group_code != $line_info['group_code']){
                                $money_['money']                      = 200*100;
                            }
                            $money_['money_type']                 = 'gather';
                            $money_['type']                       = 'in';
                            $money_['settle_flag']                = 'W';
                            if($user_info->group_code == $line_info['group_code']) {
                                $money_list_[] = $money_;
                            }
                        }

                    }

                    /** 以上是收货的调度的地方 结束**/

                    /** 以下是处理线路中的调度的地方**/
                    if($line_info['type'] == 'combination'){
                        // dd($line_info->tmsLineList->toArray());
                        foreach ($line_info->tmsLineList as $k => $v){
                            $list['self_id']                    = generate_id('patch_');
                            $list['order_id']                   = $order_id;
                            $list['company_id']                 = $company_id;
                            $list['company_name']               = $company_name;
                            $list['receiver_id']                = $line_info->group_code;
                            if ($line_info->special == 1 && $line_info->carriage_group_code){
                                $list['receiver_id']                = $line_info->carriage_group_code;
                            }
                            $list['group_code']                 = $group_code;
                            $list['group_name']                 = $group_name;
                            $list['total_user_id']              = $total_user_id;
    //                            $list['create_user_id']             = $total_user_id;
    //                            $list['create_user_name']           = $token_name;
                            $list['create_time']                = $list['update_time'] = $now_time;
                            $list['gather_address_id']          = $v['tmsLine']['gather_address_id'];

                            $list['gather_name']                = $v['tmsLine']['gather_name'];
                            $list['gather_tel']                 = $v['tmsLine']['gather_tel'];
                            $list['gather_sheng']               = $v['tmsLine']['gather_sheng'];
                            $list['gather_sheng_name']          = $v['tmsLine']['gather_sheng_name'];
                            $list['gather_shi']                 = $v['tmsLine']['gather_shi'];
                            $list['gather_shi_name']            = $v['tmsLine']['gather_shi_name'];
                            $list['gather_qu']                  = $v['tmsLine']['gather_qu'];
                            $list['gather_qu_name']             = $v['tmsLine']['gather_qu_name'];
                            $list['gather_address']             = $v['tmsLine']['gather_address'];
                            $list['gather_address_longitude']   = $v['tmsLine']['gather_address_longitude'];
                            $list['gather_address_latitude']    = $v['tmsLine']['gather_address_latitude'];
                            $list['send_address_id']            = $v['tmsLine']['send_address_id'];

                            $list['send_name']                  = $v['tmsLine']['send_name'];
                            $list['send_tel']                   = $v['tmsLine']['send_tel'];
                            $list['send_sheng']                 = $v['tmsLine']['send_sheng'];
                            $list['send_sheng_name']            = $v['tmsLine']['send_sheng_name'];
                            $list['send_shi']                   = $v['tmsLine']['send_shi'];
                            $list['send_shi_name']              = $v['tmsLine']['send_shi_name'];
                            $list['send_qu']                    = $v['tmsLine']['send_qu'];
                            $list['send_qu_name']               = $v['tmsLine']['send_qu_name'];
                            $list['send_address']               = $v['tmsLine']['send_address'];
                            $list['send_address_longitude']     = $v['tmsLine']['send_address_longitude'];
                            $list['send_address_latitude']      = $v['tmsLine']['send_address_latitude'];

                            $list['line_gather_address_id']          = $v['tmsLine']['gather_address_id'];
                            $list['line_gather_name']                = $v['tmsLine']['gather_name'];
                            $list['line_gather_tel']                 = $v['tmsLine']['gather_tel'];
                            $list['line_gather_sheng']               = $v['tmsLine']['gather_sheng'];
                            $list['line_gather_sheng_name']          = $v['tmsLine']['gather_sheng_name'];
                            $list['line_gather_shi']                 = $v['tmsLine']['gather_shi'];
                            $list['line_gather_shi_name']            = $v['tmsLine']['gather_shi_name'];
                            $list['line_gather_qu']                  = $v['tmsLine']['gather_qu'];
                            $list['line_gather_qu_name']             = $v['tmsLine']['gather_qu_name'];
                            $list['line_gather_address']             = $v['tmsLine']['gather_address'];
                            $list['line_gather_address_longitude']   = $v['tmsLine']['gather_address_longitude'];
                            $list['line_gather_address_latitude']    = $v['tmsLine']['gather_address_latitude'];
                            $list['line_send_address_id']            = $v['tmsLine']['send_address_id'];

                            $list['line_send_name']                  = $v['tmsLine']['send_name'];
                            $list['line_send_tel']                   = $v['tmsLine']['send_tel'];
                            $list['line_send_sheng']                 = $v['tmsLine']['send_sheng'];
                            $list['line_send_sheng_name']            = $v['tmsLine']['send_sheng_name'];
                            $list['line_send_shi']                   = $v['tmsLine']['send_shi'];
                            $list['line_send_shi_name']              = $v['tmsLine']['send_shi_name'];
                            $list['line_send_qu']                    = $v['tmsLine']['send_qu'];
                            $list['line_send_qu_name']               = $v['tmsLine']['send_qu_name'];
                            $list['line_send_address']               = $v['tmsLine']['send_address'];
                            $list['line_send_address_longitude']     = $v['tmsLine']['send_address_longitude'];
                            $list['line_send_address_latitude']      = $v['tmsLine']['send_address_latitude'];


                            $list['good_number']                = $good_number;
                            $list['good_weight']                = $good_weight;
                            $list['good_volume']                = $good_volume;
                            $list['order_type']                 = $order_type;
                            $list['sort']                       = $sort++;
                            $list['good_info']                  = $good_info;
                            $list['pick_flag']                  = $pick_flag;
                            $list['send_flag']                  = $send_flag;
                            $list['dispatch_flag']              = 'Y';
                            $list['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                            $list['pay_type']                   = $pay_type;
                            $list['remark']                     = $remark;
                            $list['order_status']               = 1;
                            $list['send_time']                  = $depart_time;
                            $list['gather_time']                = date('Y-m-d H:i:s',strtotime($depart_time)+24*$line_info->trunking*3600);
                            $list['reduce_price']               = $reduce_price;
                            $list['total_money']                = $line_price*100;
                            $list['on_line_money']              = $line_price*100;
    //                            if($pick_flag == 'Y'){
    //                                $list['total_money']              = $total_money*100 -$line_info->pick_price;
    //                                $list['on_line_money']            = $total_money*100 -$line_info->pick_price;
    //                            }
    //                            if ($send_flag == 'Y'){
    //                                $list['total_money']              = $total_money*100 - $line_info->send_price;
    //                                $list['on_line_money']            = $total_money*100 - $line_info->send_price;
    //                            }
    //                            if ($pick_flag == 'Y' && $send_flag == 'Y'){
    //                                $list['total_money']              = $total_money*100 - $line_info->send_price - $line_info->pick_price;
    //                                $list['on_line_money']            = $total_money*100 - $line_info->send_price - $line_info->pick_price;
    //                            }

                            if ($project_type == 'customer'){
                                $list['order_status'] = 3;
                            }
                            $list['payer']                     = $payer;
                            $inserttt[]=$list;
                            /** 存储费用 **/
                            if ($pay_type == 'offline'){
                                switch ($project_type){
                                    case 'user':
                                        $money['fk_total_user_id']           = $user_info->total_user_id;
                                        $money['fk_type']                    = 'USER';
                                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                        break;
                                    case 'customer':
                                        $money['shouk_group_code']           = $user_info->group_code;
                                        $money['shouk_type']                 = 'GROUP_CODE';
                                        $money['fk_company_id']              = $user_info->company_id;
                                        $money['fk_type']                    = 'COMPANY';
                                        $money['ZIJ_company_id']             = $user_info->company_id;
                                        break;
                                    case 'company':
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        break;
                                    case 'TMS3PL':
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        break;
                                }
                            }else{
                                switch ($project_type){
                                    case 'user':
                                        $money_['shouk_group_code']           = $line_info->group_code;
                                        $money_['shouk_type']                 = 'GROUP_CODE';
                                        $money_['fk_group_code']              = '1234';
                                        $money_['fk_type']                    = 'PLATFORM';
                                        $money_['ZIJ_group_code']             = '1234';
                                        $money_['delete_flag']                = 'N';

                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_total_user_id']           = $user_info->total_user_id;
                                        $money['fk_type']                    = 'USER';
                                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'customer':
                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_company_id']             = $user_info->company_id;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'company':
                                        $money_['shouk_group_code']           = $line_info->group_code;
                                        $money_['shouk_type']                 = 'GROUP_CODE';
                                        $money_['fk_group_code']              = '1234';
                                        $money_['fk_type']                    = 'PLATFORM';
                                        $money_['ZIJ_group_code']             = '1234';
                                        $money_['delete_flag']                = 'N';

                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'TMS3PL':
                                        $money_['shouk_group_code']           = $line_info->group_code;
                                        $money_['shouk_type']                 = 'GROUP_CODE';
                                        $money_['fk_group_code']              = '1234';
                                        $money_['fk_type']                    = 'PLATFORM';
                                        $money_['ZIJ_group_code']             = '1234';
                                        $money_['delete_flag']                = 'N';

                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        $money['delete_flag']                = 'N';
                                        break;
                                }
                            }
                            $money['self_id']                    = generate_id('order_money_');
                            $money['order_id']                   = $order_id;
                            $money['dispatch_id']                = $list['self_id'];
                            $money['create_time']                = $now_time;
                            $money['update_time']                = $now_time;
                            $money['money']                      = $price*100;
                            $money['money_type']                 = 'freight';
                            $money['type']                       = 'out';
                            $money['settle_flag']                = 'W';
                            $money_list[] = $money;

                            $money_['self_id']                    = generate_id('order_money_');
                            $money_['order_id']                   = $order_id;
                            $money_['dispatch_id']                = $list['self_id'];
                            $money_['create_time']                = $now_time;
                            $money_['update_time']                = $now_time;
                            $money_['money']                      = $price*100;
                            $money_['money_type']                 = 'freight';
                            $money_['type']                       = 'in';
                            $money_['settle_flag']                = 'W';
                            $money_list_[] = $money_;
                        }

                    }else{
                        $list['self_id']                    = generate_id('patch_');
                        $list['order_id']                   = $order_id;
                        $list['company_id']                 = $company_id;
                        $list['company_name']               = $company_name;
                        $list['receiver_id']                = $line_info->group_code;
                        if ($line_info->special == 1 && $line_info->carriage_group_code){
                            $list['receiver_id']                = $line_info->carriage_group_code;
                        }
                        $list['group_code']                 = $group_code;
                        $list['group_name']                 = $group_name;
                        $list['total_user_id']              = $total_user_id;
    //                        $list['create_user_id']             = $total_user_id;
    //                        $list['create_user_name']           = $token_name;
                        $list['create_time']                = $list['update_time'] = $now_time;
                        $list['gather_address_id']          = $line_info->gather_address_id;

                        $list['gather_name']                = $line_info->gather_name;
                        $list['gather_tel']                 = $line_info->gather_tel;
                        $list['gather_sheng']               = $line_info->gather_sheng;
                        $list['gather_sheng_name']          = $line_info->gather_sheng_name;
                        $list['gather_shi']                 = $line_info->gather_shi;
                        $list['gather_shi_name']            = $line_info->gather_shi_name;
                        $list['gather_qu']                  = $line_info->gather_qu;
                        $list['gather_qu_name']             = $line_info->gather_qu_name;
                        $list['gather_address']             = $line_info->gather_address;
                        $list['gather_address_longitude']   = $line_info->gather_address_longitude;
                        $list['gather_address_latitude']    = $line_info->gather_address_latitude;
                        $list['send_address_id']            = $line_info->send_address_id;

                        $list['send_name']                  = $line_info->send_name;
                        $list['send_tel']                   = $line_info->send_tel;
                        $list['send_sheng']                 = $line_info->send_sheng;
                        $list['send_sheng_name']            = $line_info->send_sheng_name;
                        $list['send_shi']                   = $line_info->send_shi;
                        $list['send_shi_name']              = $line_info->send_shi_name;
                        $list['send_qu']                    = $line_info->send_qu;
                        $list['send_qu_name']               = $line_info->send_qu_name;
                        $list['send_address']               = $line_info->send_address;
                        $list['send_address_longitude']     = $line_info->send_address_longitude;
                        $list['send_address_latitude']      = $line_info->send_address_latitude;

                        $list['line_gather_address_id']          = $line_info->gather_address_id;
                        $list['line_gather_name']                = $line_info->gather_name;
                        $list['line_gather_tel']                 = $line_info->gather_tel;
                        $list['line_gather_sheng']               = $line_info->gather_sheng;
                        $list['line_gather_sheng_name']          = $line_info->gather_sheng_name;
                        $list['line_gather_shi']                 = $line_info->gather_shi;
                        $list['line_gather_shi_name']            = $line_info->gather_shi_name;
                        $list['line_gather_qu']                  = $line_info->gather_qu;
                        $list['line_gather_qu_name']             = $line_info->gather_qu_name;
                        $list['line_gather_address']             = $line_info->gather_address;
                        $list['line_gather_address_longitude']   = $line_info->gather_address_longitude;
                        $list['line_gather_address_latitude']    = $line_info->gather_address_latitude;
                        $list['line_send_address_id']            = $line_info->send_address_id;

                        $list['line_send_name']                  = $line_info->send_name;
                        $list['line_send_tel']                   = $line_info->send_tel;
                        $list['line_send_sheng']                 = $line_info->send_sheng;
                        $list['line_send_sheng_name']            = $line_info->send_sheng_name;
                        $list['line_send_shi']                   = $line_info->send_shi;
                        $list['line_send_shi_name']              = $line_info->send_shi_name;
                        $list['line_send_qu']                    = $line_info->send_qu;
                        $list['line_send_qu_name']               = $line_info->send_qu_name;
                        $list['line_send_address']               = $line_info->send_address;
                        $list['line_send_address_longitude']     = $line_info->send_address_longitude;
                        $list['line_send_address_latitude']      = $line_info->send_address_latitude;

                        $list['good_number']                = $good_number ;
                        $list['good_weight']                = $good_weight ;
                        $list['good_volume']                = $good_volume ;
                        $list['dispatch_flag']              = 'Y';
                        $list['sort']                       = $sort++;
                        $list['order_type']                 = $order_type;
                        $list['pick_flag']                  = $pick_flag;
                        $list['send_flag']                  = $send_flag;
                        $list['good_info']                  = $good_info;
                        $list['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                        $list['pay_type']                   = $pay_type;
                        $list['remark']                   = $remark;
                        $list['order_status'] = 1;
                        $list['send_time']                = $depart_time;
                        $list['gather_time']              = date('Y-m-d H:i:s',strtotime($depart_time)+24*$line_info->trunking*3600);
                        $list['reduce_price']             = $reduce_price;
                        $list['total_money']              = $line_price*100;
                        $list['on_line_money']              = $line_price*100;
    //                        if($pick_flag == 'Y'){
    //                            $list['total_money']              = $total_money*100 -$line_info->pick_price;
    //                            $list['on_line_money']              = $total_money*100 -$line_info->pick_price;
    //                        }
    //                        if ($send_flag == 'Y'){
    //                            $list['total_money']              = $total_money*100 - $line_info->send_price;
    //                            $list['on_line_money']              = $total_money*100 - $line_info->send_price;
    //                        }
    //                        if ($pick_flag == 'Y' && $send_flag == 'Y'){
    //                            $list['total_money']              = $total_money*100 - $line_info->send_price - $line_info->pick_price;
    //                            $list['on_line_money']              = $total_money*100 - $line_info->send_price - $line_info->pick_price;
    //                        }
                        if ($project_type == 'customer'){
                            $list['order_status'] = 3;
                        }
                        $list['payer']                   = $payer;
                        $inserttt[]=$list;

                        /** 存储费用 **/
                        if ($pay_type == 'offline'){
                            switch ($project_type){
                                case 'user':
                                    $money['fk_total_user_id']           = $user_info->total_user_id;
                                    $money['fk_type']                    = 'USER';
                                    $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                    break;
                                case 'customer':
                                    $money['shouk_group_code']           = $user_info->group_code;
                                    $money['shouk_type']                 = 'GROUP_CODE';
                                    $money['fk_company_id']              = $user_info->company_id;
                                    $money['fk_type']                    = 'COMPANY';
                                    $money['ZIJ_company_id']             = $user_info->company_id;
                                    break;
                                case 'company':
                                    $money['fk_group_code']              = $user_info->group_code;
                                    $money['fk_type']                    = 'GROUP_CODE';
                                    $money['ZIJ_group_code']             = $user_info->group_code;
                                    break;
                                case 'TMS3PL':
                                    $money['fk_group_code']              = $user_info->group_code;
                                    $money['fk_type']                    = 'GROUP_CODE';
                                    $money['ZIJ_group_code']             = $user_info->group_code;
                                    break;
                            }
                        }else{
                            switch ($project_type){
                                case 'user':
                                    $money_['shouk_group_code']           = $line_info->group_code;
                                    $money_['shouk_type']                 = 'GROUP_CODE';
                                    $money_['fk_group_code']              = '1234';
                                    $money_['fk_type']                    = 'PLATFORM';
                                    $money_['ZIJ_group_code']             = '1234';
                                    $money_['delete_flag']                = 'N';

                                    $money['shouk_group_code']           = '1234';
                                    $money['shouk_type']                 = 'PLATFORM';
                                    $money['fk_total_user_id']           = $user_info->total_user_id;
                                    $money['fk_type']                    = 'USER';
                                    $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                    $money['delete_flag']                = 'N';
                                    break;
                                case 'customer':
                                    $money['shouk_group_code']           = '1234';
                                    $money['shouk_type']                 = 'PLATFORM';
                                    $money['fk_group_code']              = $user_info->group_code;
                                    $money['fk_type']                    = 'GROUP_CODE';
                                    $money['ZIJ_company_id']             = $user_info->company_id;
                                    $money['delete_flag']                = 'N';
                                    break;
                                case 'company':
                                    $money_['shouk_group_code']           = $line_info->group_code;
                                    $money_['shouk_type']                 = 'GROUP_CODE';
                                    $money_['fk_group_code']              = '1234';
                                    $money_['fk_type']                    = 'PLATFORM';
                                    $money_['ZIJ_group_code']             = '1234';
                                    $money_['delete_flag']                = 'N';

                                    $money['shouk_group_code']           = '1234';
                                    $money['shouk_type']                 = 'PLATFORM';
                                    $money['fk_group_code']              = $user_info->group_code;
                                    $money['fk_type']                    = 'GROUP_CODE';
                                    $money['ZIJ_group_code']             = $user_info->group_code;
                                    $money['delete_flag']                = 'N';
                                    break;
                                case 'TMS3PL':
                                    $money_['shouk_group_code']           = $line_info->group_code;
                                    $money_['shouk_type']                 = 'GROUP_CODE';
                                    $money_['fk_group_code']              = '1234';
                                    $money_['fk_type']                    = 'PLATFORM';
                                    $money_['ZIJ_group_code']             = '1234';
                                    $money_['delete_flag']                = 'N';

                                    $money['shouk_group_code']           = '1234';
                                    $money['shouk_type']                 = 'PLATFORM';
                                    $money['fk_group_code']              = $user_info->group_code;
                                    $money['fk_type']                    = 'GROUP_CODE';
                                    $money['ZIJ_group_code']             = $user_info->group_code;
                                    $money['delete_flag']                = 'N';
                                    break;
                            }
                        }
                        $money['self_id']                    = generate_id('order_money_');
                        $money['order_id']                   = $order_id;
                        $money['dispatch_id']                = $list['self_id'];
                        $money['create_time']                = $now_time;
                        $money['update_time']                = $now_time;
                        $money['money']                      = $price*100;
                        $money['money_type']                 = 'freight';
                        $money['type']                       = 'out';
                        $money['settle_flag']                = 'W';
                        $money_list[] = $money;

                        $money_['self_id']                    = generate_id('order_money_');
                        $money_['order_id']                   = $order_id;
                        $money_['dispatch_id']                = $list['self_id'];
                        $money_['create_time']                = $now_time;
                        $money_['update_time']                = $now_time;
                        $money_['money']                      = $price*100;
                        $money_['money_type']                 = 'freight';
                        $money_['type']                       = 'in';
                        $money_['settle_flag']                = 'W';
                        $money_list_[] = $money_;

                    }

                    /** 以上是处理线路中的调度的地方 结束**/

                    /** 以上是收的调度的地方 **/
                    if($send_flag == 'Y'){
                        foreach ($lki as $k => $v){
                            $list['self_id']                    = generate_id('patch_');
                            $list['order_id']                   = $order_id;
                            $list['company_id']                 = $company_id;
                            $list['company_name']               = $company_name;
                            $list['receiver_id']                = $line_info->group_code;
                            if($user_info->group_code != $line_info['group_code']) {
                                $list['receiver_id'] = '1234';
                            }
                            if ($line_info->special == 1 && $line_info->carriage_id){
                                $list['receiver_id']                = $line_info->carriage_id;
                            }
                            $list['group_code']                 = $group_code;
                            $list['group_name']                 = $group_name;
                            $list['total_user_id']              = $total_user_id;
    //                            $list['create_user_id']             = $total_user_id;
    //                            $list['create_user_name']           = $token_name;
                            $list['create_time']                = $list['update_time'] = $now_time;

                            $list['send_address_id']            = $line_info['gather_address_id'];

                            $list['send_name']                  = $line_info['gather_name'];
                            $list['send_tel']                   = $line_info['gather_tel'];
                            $list['send_sheng']                 = $line_info['gather_sheng'];
                            $list['send_sheng_name']            = $line_info['gather_sheng_name'];
                            $list['send_shi']                   = $line_info['gather_shi'];
                            $list['send_shi_name']              = $line_info['gather_shi_name'];
                            $list['send_qu']                    = $line_info['gather_qu'];
                            $list['send_qu_name']               = $line_info['gather_qu_name'];
                            $list['send_address']               = $line_info['gather_address'];
                            $list['send_address_longitude']     = $line_info['gather_address_longitude'];
                            $list['send_address_latitude']      = $line_info['gather_address_latitude'];
                            $list['gather_address_id']          = $v['gather_address_id'];
                            $list['gather_name']                = $v['gather_contacts_name'];
                            $list['gather_tel']                 = $v['gather_contacts_tel'];
                            $list['gather_sheng']               = $v['gather_sheng'];
                            $list['gather_sheng_name']          = $v['gather_sheng_name'];
                            $list['gather_shi']                 = $v['gather_shi'];
                            $list['gather_shi_name']            = $v['gather_shi_name'];
                            $list['gather_qu']                  = $v['gather_qu'];
                            $list['gather_qu_name']             = $v['gather_qu_name'];
                            $list['gather_address']             = $v['gather_address'];
                            $list['gather_address_longitude']   = $v['gather_address_longitude'];
                            $list['gather_address_latitude']    = $v['gather_address_latitude'];

                            $list['line_gather_address_id']        = $line_info['send_address_id'];
                            $list['line_gather_name']              = $line_info['send_name'];
                            $list['line_gather_tel']               = $line_info['send_tel'];
                            $list['line_gather_sheng']             = $line_info['send_sheng'];
                            $list['line_gather_sheng_name']        = $line_info['send_sheng_name'];
                            $list['line_gather_shi']               = $line_info['send_shi'];
                            $list['line_gather_shi_name']          = $line_info['send_shi_name'];
                            $list['line_gather_qu']                = $line_info['send_qu'];
                            $list['line_gather_qu_name']           = $line_info['send_qu_name'];
                            $list['line_gather_address']           = $line_info['send_address'];
                            $list['line_gather_address_longitude'] = $line_info['send_address_longitude'];
                            $list['line_gather_address_latitude']  = $line_info['send_address_latitude'];
                            $list['line_send_address_id']          = $v['gather_address_id'];
                            $list['line_send_name']                = $v['gather_contacts_name'];
                            $list['line_send_tel']                 = $v['gather_contacts_tel'];
                            $list['line_send_sheng']               = $v['gather_sheng'];
                            $list['line_send_sheng_name']          = $v['gather_sheng_name'];
                            $list['line_send_shi']                 = $v['gather_shi'];
                            $list['line_send_shi_name']            = $v['gather_shi_name'];
                            $list['line_send_qu']                  = $v['gather_qu'];
                            $list['line_send_qu_name']             = $v['gather_qu_name'];
                            $list['line_send_address']             = $v['gather_address'];
                            $list['line_send_address_longitude']   = $v['gather_address_longitude'];
                            $list['line_send_address_latitude']    = $v['gather_address_latitude'];

                            $list['good_number']                = $v['good_number'];
                            $list['good_weight']                = $v['good_weight'];
                            $list['good_volume']                = $v['good_volume'];
                            $list['dispatch_flag']              = 'Y';
                            $list['sort']                       = $sort++;
                            $list['order_type']                 = $order_type;
                            $list['pick_flag']                  = $pick_flag;
                            $list['send_flag']                  = $send_flag;
                            $list['good_info']                  = $v['good_josn'];
                            $list['clod']                       = json_encode($v['clod'],JSON_UNESCAPED_UNICODE);
                            $list['pay_type']                   = $pay_type;
                            $list['remark']                     = $remark;
                            $list['order_status'] = 1;
                            $list['send_time']                = date('Y-m-d H:i:s',strtotime($depart_time)+24*$line_info->trunking*3600);
                            $list['gather_time']              = null;
                            $list['reduce_price']             = 0;
                            $list['total_money']              = $line_info['send_price'];
                            $list['on_line_money']            = $line_info['send_price'];
                            if($user_info->group_code != $line_info['group_code']){
                                $list['total_money']              = 200*100;
                                $list['on_line_money']            = 200*100;
                            }
                            if ($line_info->special == 1){
                                $list['total_money'] = line_count_price($line_info,$list['good_number'])*100;
                                $list['on_line_money'] = line_count_price($line_info,$list['good_number'])*100;
                            }
                            if ($project_type == 'customer'){
                                $list['order_status'] = 3;
                            }
                            $list['payer']                    = $payer;
                            $inserttt[]=$list;

                            /** 存储费用 **/
                            if ($pay_type == 'offline'){
                                switch ($project_type){
                                    case 'user':
                                        $money['fk_total_user_id']           = $user_info->total_user_id;
                                        $money['fk_type']                    = 'USER';
                                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                        break;
                                    case 'customer':
                                        $money['shouk_group_code']           = $user_info->group_code;
                                        $money['shouk_type']                 = 'GROUP_CODE';
                                        $money['fk_company_id']              = $user_info->company_id;
                                        $money['fk_type']                    = 'COMPANY';
                                        $money['ZIJ_company_id']             = $user_info->company_id;
                                        break;
                                    case 'company':
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        break;
                                    case 'TMS3PL':
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        break;
                                }
                            }else{
                                switch ($project_type){
                                    case 'user':
                                        if($user_info->group_code == $line_info['group_code']) {
                                            $money_['shouk_group_code'] = $line_info->group_code;
                                            $money_['shouk_type'] = 'GROUP_CODE';
                                            $money_['fk_group_code'] = '1234';
                                            $money_['fk_type'] = 'PLATFORM';
                                            $money_['ZIJ_group_code'] = '1234';
                                            $money_['delete_flag'] = 'N';
                                        }
                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_total_user_id']           = $user_info->total_user_id;
                                        $money['fk_type']                    = 'USER';
                                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'customer':
                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_company_id']             = $user_info->company_id;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'company':
                                        $money_['shouk_group_code']           = $line_info->group_code;
                                        $money_['shouk_type']                 = 'GROUP_CODE';
                                        $money_['fk_group_code']              = '1234';
                                        $money_['fk_type']                    = 'PLATFORM';
                                        $money_['ZIJ_group_code']             = '1234';
                                        $money_['delete_flag']                = 'N';

                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        $money['delete_flag']                = 'N';
                                        break;
                                    case 'TMS3PL':
                                        if($user_info->group_code == $line_info['group_code']) {
                                            $money_['shouk_group_code'] = $line_info->group_code;
                                            $money_['shouk_type'] = 'GROUP_CODE';
                                            $money_['fk_group_code'] = '1234';
                                            $money_['fk_type'] = 'PLATFORM';
                                            $money_['ZIJ_group_code'] = '1234';
                                            $money_['delete_flag'] = 'N';
                                        }
                                        $money['shouk_group_code']           = '1234';
                                        $money['shouk_type']                 = 'PLATFORM';
                                        $money['fk_group_code']              = $user_info->group_code;
                                        $money['fk_type']                    = 'GROUP_CODE';
                                        $money['ZIJ_group_code']             = $user_info->group_code;
                                        $money['delete_flag']                = 'N';
                                        break;
                                }
                            }
                            $money['self_id']                    = generate_id('order_money_');
                            $money['order_id']                   = $order_id;
                            $money['dispatch_id']                = $list['self_id'];
                            $money['create_time']                = $now_time;
                            $money['update_time']                = $now_time;
                            $money['money']                      = $send_money*100;
                            if($user_info->group_code != $line_info['group_code']) {
                                $money['money'] = 200 * 100;
                            }
                            $money['money_type']                 = 'send';
                            $money['type']                       = 'out';
                            $money['settle_flag']                = 'W';
                            $money_list[] = $money;

                            $money_['self_id']                    = generate_id('order_money_');
                            $money_['order_id']                   = $order_id;
                            $money_['dispatch_id']                = $list['self_id'];
                            $money_['create_time']                = $now_time;
                            $money_['update_time']                = $now_time;
                            $money_['money']                      = $send_money*100;
                            if($user_info->group_code != $line_info['group_code']) {
                                $money_['money'] = 200 * 100;
                            }
                            $money_['money_type']                 = 'send';
                            $money_['type']                       = 'in';
                            $money_['settle_flag']                = 'W';
                            if($user_info->group_code == $line_info['group_code']) {
                                $money_list_[] = $money_;
                            }
                        }
                    }

                    /** 以上是送货的调度的地方 结束**/
                    $data['self_id']          = $order_id;       //order ID
                    $data['group_code']       = $group_code;
                    $data['group_name']       = $group_name;
                    $data['total_user_id']    = $total_user_id;
                    $data['pay_type']         = $pay_type;
    //                    $data['create_user_id']   = $total_user_id;
    //                    $data['create_user_name'] = $token_name;
                    $data['create_time']      = $data['update_time'] = $now_time;
                    $data['company_id']       = $company_id;
                    $data['company_name']     = $company_name;
                    $data['order_type']       = $order_type;
                    $data['pick_money']       = ($pick_money - 0)*100;
                    $data['send_money']       = ($send_money - 0)*100;
                    if($user_info->group_code != $line_info['group_code']){
                        $data['pick_money']       = 200*100;
                        $data['send_money']       = 200*100;
                    }
                    $data['price']            = ($price - 0)*100;
                    $data['more_money']       = $more_money;
                    // 配送
                    if($send_flag == 'Y'){
                        $data['gather_address_id']          = $lki[0]['gather_address_id'];

                        $data['gather_name']                = $lki[0]['gather_contacts_name'];
                        $data['gather_tel']                 = $lki[0]['gather_contacts_tel'];
                        $data['gather_sheng']               = $lki[0]['gather_sheng'];
                        $data['gather_sheng_name']          = $lki[0]['gather_sheng_name'];
                        $data['gather_shi']                 = $lki[0]['gather_shi'];
                        $data['gather_shi_name']            = $lki[0]['gather_shi_name'];
                        $data['gather_qu']                  = $lki[0]['gather_qu'];
                        $data['gather_qu_name']             = $lki[0]['gather_qu_name'];
                        $data['gather_address']             = $lki[0]['gather_address'];
                        $data['gather_address_longitude']   = $lki[0]['gather_address_longitude'];
                        $data['gather_address_latitude']    = $lki[0]['gather_address_latitude'];
                    }else{
                        $data['gather_address_id']          = $line_info['gather_address_id'];

                        $data['gather_name']                = $line_info['gather_name'];
                        $data['gather_tel']                 = $line_info['gather_tel'];
                        $data['gather_sheng']               = $line_info['gather_sheng'];
                        $data['gather_sheng_name']          = $line_info['gather_sheng_name'];
                        $data['gather_shi']                 = $line_info['gather_shi'];
                        $data['gather_shi_name']            = $line_info['gather_shi_name'];
                        $data['gather_qu']                  = $line_info['gather_qu'];
                        $data['gather_qu_name']             = $line_info['gather_qu_name'];
                        $data['gather_address']             = $line_info['gather_address'];
                        $data['gather_address_longitude']   = $line_info['gather_address_longitude'];
                        $data['gather_address_latitude']    = $line_info['gather_address_latitude'];
                    }
                    //提货
                    if($pick_flag == 'Y'){
                        $data['send_address_id']            = $lki2[0]['send_address_id'];
                        $data['send_name']                  = $lki2[0]['send_contacts_name'];
                        $data['send_tel']                   = $lki2[0]['send_contacts_tel'];
                        $data['send_sheng']                 = $lki2[0]['send_sheng'];
                        $data['send_sheng_name']            = $lki2[0]['send_sheng_name'];
                        $data['send_shi']                   = $lki2[0]['send_shi'];
                        $data['send_shi_name']              = $lki2[0]['send_shi_name'];
                        $data['send_qu']                    = $lki2[0]['send_qu'];
                        $data['send_qu_name']               = $lki2[0]['send_qu_name'];
                        $data['send_address']               = $lki2[0]['send_address'];
                        $data['send_address_longitude']     = $lki2[0]['send_address_longitude'];
                        $data['send_address_latitude']      = $lki2[0]['send_address_latitude'];
                    }else{
                        $data['send_address_id']            = $line_info->send_address_id;
                        $data['send_name']                  = $line_info->send_name;
                        $data['send_tel']                   = $line_info->send_tel;
                        $data['send_sheng']                 = $line_info->send_sheng;
                        $data['send_sheng_name']            = $line_info->send_sheng_name;
                        $data['send_shi']                   = $line_info->send_shi;
                        $data['send_shi_name']              = $line_info->send_shi_name;
                        $data['send_qu']                    = $line_info->send_qu;
                        $data['send_qu_name']               = $line_info->send_qu_name;
                        $data['send_address']               = $line_info->send_address;
                        $data['send_address_longitude']     = $line_info->send_address_longitude;
                        $data['send_address_latitude']      = $line_info->send_address_latitude;

                    }

                    $data['good_number'] = $good_number;
                    $data['good_weight'] = $good_weight;
                    $data['good_volume'] = $good_volume;
                    $data['pick_flag']   = $pick_flag;
                    $data['send_flag']   = $send_flag;
                    $data['total_money'] = ($total_money - 0)*100;
                    $data['line_id']     = $line_id;
                    $data['line_info']   = json_encode($line_info,JSON_UNESCAPED_UNICODE);
                    $data['info']        = json_encode($dispatcher,JSON_UNESCAPED_UNICODE);
                    $data['good_info']   = $good_info;
                    $data['clod']        = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                    $data['remark']      = $remark;
                    $data['app_flag']                   = $app_flag;
                    $data['send_time']   = $send_time;
                    $data['reduce_price'] = $reduce_price;
                    $data['payer']       = $payer;
                    $wheres['self_id'] = $self_id;

    //                    dd(123);
                    $old_info = TmsOrder::where($wheres)->first();



                    if($old_info) {
                        $orderid = $self_id;
                        $data['update_time'] = $now_time;
                        $id = TmsOrder::where($wheres)->update($data);
                    }else{
    //                        $data['self_id']          = generate_id('order_');
    //                        $data['create_user_id']   = $total_user_id;
    //                        $data['create_user_name'] = $token_name;
                        $data['create_time']      = $data['update_time'] = $now_time;
                        $orderid = $data['self_id'];
                        $data['order_status'] = 1;
                        if ($project_type == 'customer'){
                            $list['order_status'] = 3;
                            $data['order_status'] = 3;
                        }
                        $id = TmsOrder::insert($data);
                        TmsOrderDispatch::insert($inserttt);
                        TmsOrderCost::insert($money_list);
                        TmsOrderCost::insert($money_list_);

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
                    break;
                default:
                    $wheres['self_id'] = $self_id;
                    $old_info=TmsOrder::where($wheres)->first();

                    if($old_info){
                        // $operationing->access_cause='修改订单';
                        // $operationing->operation_type='update';

                    }else{
                        // $operationing->access_cause='新建订单';
                        // $operationing->operation_type='create';
                    }

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
                    $data['good_number']                = $good_number;
                    $data['good_weight']                = $good_weight;
                    $data['good_volume']                = $good_volume;
                    $data['pick_flag']                  = $pick_flag;
                    $data['send_flag']                  = $send_flag;
                    $data['total_money']                = ($total_money - 0) * 100;
                    $data['info']                       = json_encode($dispatcher,JSON_UNESCAPED_UNICODE);
                    $data['good_info']                  = $good_info;
                    $data['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                    $data['pick_money']                 = ($pick_money - 0)*100;
                    $data['send_money']                 = ($send_money - 0)*100;
                    $data['price']                      = ($price - 0)*100;
                    $data['more_money']                 = $more_money;
                    $data['pay_type']                   = $pay_type;
                    $data['car_type']                   = $car_type;
                    $data['remark']                   = $remark;
                    $data['app_flag']                   = $app_flag;
                    $data['reduce_price']             = $reduce_price;
                    $data['payer']                      = $payer;
                    /*** 现在根据用户的这个是否提货产生出可调度的数据出来以及费用出来**/
                    $inserttt=[];

                    /** 做一个调度数据出来*/
                    $list['order_type']                 = $order_type;
                    $list['order_id']                   = $order_id;
                    $list['company_id']                 = $company_id;
                    $list['company_name']               = $company_name;
                    $list['receiver_id']                = $receiver_id;
                    $list['group_code']                 = $group_code;
                    $list['group_name']                 = $group_name;
                    $list['total_user_id']              = $total_user_id;
                    $list['gather_time']                = $gather_time;
                    $list['gather_address_id']          = $dispatcher[0]['gather_address_id'];
                    $list['gather_name']                = $dispatcher[0]['gather_contacts_name'];
                    $list['gather_tel']                 = $dispatcher[0]['gather_contacts_tel'];
                    $list['gather_sheng']               = $dispatcher[0]['gather_sheng'];
                    $list['gather_sheng_name']          = $dispatcher[0]['gather_sheng_name'];
                    $list['gather_shi']                 = $dispatcher[0]['gather_shi'];
                    $list['gather_shi_name']            = $dispatcher[0]['gather_shi_name'];
                    $list['gather_qu']                  = $dispatcher[0]['gather_qu'];
                    $list['gather_qu_name']             = $dispatcher[0]['gather_qu_name'];
                    $list['gather_address']             = $dispatcher[0]['gather_address'];
                    $list['gather_address_longitude']   = $dispatcher[0]['gather_address_longitude'];
                    $list['gather_address_latitude']    = $dispatcher[0]['gather_address_latitude'];
                    $list['send_time']                  = $send_time;
                    $list['send_address_id']            = $dispatcher[0]['send_address_id'];

                    $list['send_name']                  = $dispatcher[0]['send_contacts_name'];
                    $list['send_tel']                   = $dispatcher[0]['send_contacts_tel'];
                    $list['send_sheng']                 = $dispatcher[0]['send_sheng'];
                    $list['send_sheng_name']            = $dispatcher[0]['send_sheng_name'];
                    $list['send_shi']                   = $dispatcher[0]['send_shi'];
                    $list['send_shi_name']              = $dispatcher[0]['send_shi_name'];
                    $list['send_qu']                    = $dispatcher[0]['send_qu'];
                    $list['send_qu_name']               = $dispatcher[0]['send_qu_name'];
                    $list['send_address']               = $dispatcher[0]['send_address'];
                    $list['send_address_longitude']     = $dispatcher[0]['send_address_longitude'];
                    $list['send_address_latitude']      = $dispatcher[0]['send_address_latitude'];

                    $list['line_gather_address_id']          = $dispatcher[0]['gather_address_id'];
    //                    $list['line_gather_contacts_id']         = $dispatcher[0]['gather_contacts_id'];
                    $list['line_gather_name']                = $dispatcher[0]['gather_contacts_name'];
                    $list['line_gather_tel']                 = $dispatcher[0]['gather_contacts_tel'];
                    $list['line_gather_sheng']               = $dispatcher[0]['gather_sheng'];
                    $list['line_gather_sheng_name']          = $dispatcher[0]['gather_sheng_name'];
                    $list['line_gather_shi']                 = $dispatcher[0]['gather_shi'];
                    $list['line_gather_shi_name']            = $dispatcher[0]['gather_shi_name'];
                    $list['line_gather_qu']                  = $dispatcher[0]['gather_qu'];
                    $list['line_gather_qu_name']             = $dispatcher[0]['gather_qu_name'];
                    $list['line_gather_address']             = $dispatcher[0]['gather_address'];
                    $list['line_gather_address_longitude']   = $dispatcher[0]['gather_address_longitude'];
                    $list['line_gather_address_latitude']    = $dispatcher[0]['gather_address_latitude'];
                    $list['line_send_address_id']            = $dispatcher[0]['send_address_id'];
                    $list['line_send_name']                  = $dispatcher[0]['send_contacts_name'];
                    $list['line_send_tel']                   = $dispatcher[0]['send_contacts_tel'];
                    $list['line_send_sheng']                 = $dispatcher[0]['send_sheng'];
                    $list['line_send_sheng_name']            = $dispatcher[0]['send_sheng_name'];
                    $list['line_send_shi']                   = $dispatcher[0]['send_shi'];
                    $list['line_send_shi_name']              = $dispatcher[0]['send_shi_name'];
                    $list['line_send_qu']                    = $dispatcher[0]['send_qu'];
                    $list['line_send_qu_name']               = $dispatcher[0]['send_qu_name'];
                    $list['line_send_address']               = $dispatcher[0]['send_address'];
                    $list['line_send_address_longitude']     = $dispatcher[0]['send_address_longitude'];
                    $list['line_send_address_latitude']      = $dispatcher[0]['send_address_latitude'];

                    $list['on_line_flag']               = 'Y';
                    $list['on_line_money']              = $total_money*100;
                    $list['good_number']                = $good_number;
                    $list['good_weight']                = $good_weight;
                    $list['good_volume']                = $good_volume;
                    $list['dispatch_flag']              = 'Y';
                    $list['pick_flag']                  = $pick_flag;
                    $list['send_flag']                  = $send_flag;
                    $list['info']                       = json_encode($dispatcher,JSON_UNESCAPED_UNICODE);
                    $list['good_info']                  = $good_info;
                    $list['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
                    $list['pay_type']                   = $pay_type;
                    $list['remark']                   = $remark;
                    $list['car_type']                   = $car_type;
                    $list['reduce_price']               = $reduce_price;
                    if ($pay_type == 'offline'){
                        $list['order_status'] = 2;
                    }
                    $list['payer']                      = $payer;
    //                    if ($project_type == 'customer'){
    //                        $list['order_status'] = 3;
    //                    }
                    if($old_info){

                    }else{
                        $list['self_id']          = generate_id('patch_');
                        $list['total_user_id']    = $total_user_id;
    //                        $list['create_user_id']   = $total_user_id;
    //                        $list['create_user_name'] = $token_name;
                        $list['create_time']      = $list['update_time'] = $now_time;
                    }
                    /** 存储费用 **/
                    if ($pay_type == 'offline'){
                        switch ($project_type){
                            case 'user':
                                $money['fk_total_user_id']           = $user_info->total_user_id;
                                $money['fk_type']                    = 'USER';
                                $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                break;
                            case 'customer':
                                $money['shouk_group_code']           = $user_info->group_code;
                                $money['shouk_type']                 = 'GROUP_CODE';
                                $money['fk_company_id']              = $user_info->company_id;
                                $money['fk_type']                    = 'COMPANY';
                                $money['ZIJ_company_id']             = $user_info->company_id;
                                break;
                            case 'company':
                                $money['fk_group_code']              = $user_info->group_code;
                                $money['fk_type']                    = 'GROUP_CODE';
                                $money['ZIJ_group_code']             = $user_info->group_code;
                                break;
                            case 'TMS3PL':
                                $money['fk_group_code']              = $user_info->group_code;
                                $money['fk_type']                    = 'GROUP_CODE';
                                $money['ZIJ_group_code']             = $user_info->group_code;
                                break;
                        }
                    }else{
                        switch ($project_type){
                            case 'user':
                                $money['shouk_group_code']           = '1234';
                                $money['shouk_type']                 = 'PLATFORM';
                                $money['fk_total_user_id']           = $user_info->total_user_id;
                                $money['fk_type']                    = 'USER';
                                $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                                $money['delete_flag']                = 'N';
                                break;
                            case 'customer':
                                $money['shouk_group_code']           = '1234';
                                $money['shouk_type']                 = 'PLATFORM';
                                $money['fk_group_code']              = $user_info->group_code;
                                $money['fk_type']                    = 'GROUP_CODE';
                                $money['ZIJ_company_id']             = $user_info->company_id;
                                $money['delete_flag']                = 'N';
                                break;
                            case 'company':
                                $money['shouk_group_code']           = '1234';
                                $money['shouk_type']                 = 'PLATFORM';
                                $money['fk_group_code']              = $user_info->group_code;
                                $money['fk_type']                    = 'GROUP_CODE';
                                $money['ZIJ_group_code']             = $user_info->group_code;
                                $money['delete_flag']                = 'N';
                                break;
                            case 'TMS3PL':
                                $money['shouk_group_code']           = '1234';
                                $money['shouk_type']                 = 'PLATFORM';
                                $money['fk_group_code']              = $user_info->group_code;
                                $money['fk_type']                    = 'GROUP_CODE';
                                $money['ZIJ_group_code']             = $user_info->group_code;
                                $money['delete_flag']                = 'N';
                                break;
                        }
                    }
                    $money['self_id']                    = generate_id('order_money_');
                    $money['order_id']                   = $order_id;
                    $money['dispatch_id']                = $list['self_id'];
                    $money['create_time']                = $now_time;
                    $money['update_time']                = $now_time;
                    $money['money']                      = $total_money*100;
                    $money['money_type']                 = 'freight';
                    $money['type']                       = 'in';
                    $money['settle_flag']                = 'W';
                    $inserttt[] = $list;
                    if($old_info){
                        $orderid = $self_id;
                        $data['update_time'] = $now_time;
                        $id = TmsOrder::where($wheres)->update($data);
                        // $operationing->access_cause='修改订单';
                        // $operationing->operation_type='update';
                    }else{
                        $data['self_id']          = $order_id;
                        $data['group_code']       = $group_code;
                        $data['group_name']       = $group_name;
                        $data['company_id']       = $company_id;
                        $data['company_name']       = $company_name;
                        $data['total_user_id']   = $total_user_id;
    //                        $data['create_user_name'] = $token_name;
                        $data['create_time']      = $data['update_time'] = $now_time;
                        $orderid = $data['self_id'];
                        if ($pay_type == 'offline'){
                            $data['order_status'] = 2;
                        }
    //                        if ($project_type == 'customer'){
    //                            $data['order_status'] = 3;
    //                        }
                        $id = TmsOrder::insert($data);
                        TmsOrderDispatch::insert($inserttt);
                        TmsOrderCost::insert($money);

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
                    break;
            }

            /***二次效验结束**/
        }else{
            //前端用户验证没有通过
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg'] = null;
            foreach ($erro as $k => $v){
                $kk = $k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }

}

    public function addUserFreeRide(Request $request,Tms $tms){
        $project_type       =$request->get('project_type');
        $now_time   = date('Y-m-d H:i:s',time());
        $table_name = 'tms_order';
        $user_info  = $request->get('user_info');//接收中间件产生的参数
//        $project_type = 'company';
        $total_user_id  = $user_info->total_user_id;
//        dd($user_info);
        $input      =$request->all();

        /** 接收数据*/
        $self_id       = $request->input('self_id');
        $order_type    = $request->input('order_type');//订单类型 vehicle  lcl   line
        $line_id       = $request->input('line_id');//线路 id
        $pick_flag     = $request->input('pick_flag');//是否提货、装货
        $send_flag     = $request->input('send_flag');//是否配送、卸货
        $price         = $request->input('price');//运输费
        $line_price    = $request->input('line_price')??0;//运输费
        $total_money   = $request->input('total_money');//总计费用
        $more_money    = $request->input('more_money');
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
        $reduce_price    = $request->input('reduce_price');//立减金额
        $user_type       = $request->input('user_type');
        $carpool       = $request->input('carpool');
        /*** 虚拟数据
        //$input['self_id']   = $self_id='';
        $input['order_type']  = $order_type='vehicle';  //vehicle  lcl   line
        $input['line_id']     = $line_id='line_202106031747275293691321';
        $input['pick_flag']   = $pick_flag='Y';
        $input['send_flag']   = $send_flag='Y';
        $input['pick_money']  = $pick_money='0';
        $input['price']       = $price='400';
        $input['send_money']  = $send_money='0';
        $input['total_money'] = $total_money='400';
        $input['more_money']  = $more_money=20;//多点费用
        $input['good_name']   = $good_name_n='冰淇淋';
        $input['good_number'] = $good_number_n=2000;
        $input['good_weight'] = $good_weight_n=2;
        $input['good_volume'] = $good_volume_n=5;
        $input['clod']        = $clod='refrigeration';
        $input['gather_time']        = $gather_time='2021-08-27 15:00';
        $input['send_time']        = $send_time='2021-08-26 15:00';
        $input['pay_type']        = $pay_type='offline';
        $input['car_type']        = $car_type='type_202102051755118034654564';
        $input['remark']        = $remark='';
        $input['depart_time'] = $depart_time = '2021-04-30 15:00';
        $input['reduce_price'] = $reduce_price = '20';

        $input['dispatcher']  = $dispatcher = [
        '0'=>[
        'send_address_id'=>'',
        'send_qu'=>'154',
        'send_qu_name'=>'嘉定区',
        'send_shi_name'=>'上海市',
        'send_address'=>'江桥镇金园四路333号',
        'send_contacts_id'=>'',
        'send_address_longitude'=>'',
        'send_address_latitude'=>'',
        'send_name'=>'王先生',
        'send_tel'=>'15893289182',
        'good_name'=>'冰淇淋',
        'good_number'=>'2000',
        'good_weight'=>'2',
        'good_volume'=>'5',
        'clod'=>'refrigeration',
        'clod_name'=>'冷冻',
        'gather_address_id'=>'',
        'gather_qu'=>'154',
        'gather_qu_name'=>'闵行区',
        'gather_shi_name'=>'上海市',
        'gather_address'=>'新虹街道绥宁路628-3号',
        'gather_address_longitude'=>'',
        'gather_address_latitude'=>'',
        'gather_name'=>'张经理',
        'gather_tel'=>'18623716061',
        ],

        //             '1'=>[
        //                 'send_address_id'=>'',
        //                 'send_qu'=>'43',
        //                 'send_qu_name'=>'',
        //                 'send_shi_name'=>'',
        //                 'send_address'=>'小浪底002',
        //                 'send_address_longitude'=>'121.471732',
        //                 'send_address_latitude'=>'31.231518',
        //                 'send_name'=>'张三002',
        //                 'send_tel'=>'002',
        //                 'good_name'=>'产品名称002',
        //                 'good_number'=>'20',
        //                 'good_weight'=>'55',
        //                 'good_volume'=>'13',
        //                 'clod'=>'freeze',
        //                 'clod_name'=>'冷藏',
        //                 'gather_address_id'=>'',
        //                 'gather_qu'=>'43',
        //                 'gather_qu_name'=>'',
        //                 'gather_shi_name'=>'',
        //                 'gather_address'=>'小浪底002_ga',
        //                 'gather_address_longitude'=>'121.471732',
        //                 'gather_address_latitude'=>'31.231518',
        //                 'gather_name'=>'张三002_ga',
        //                 'gather_tel'=>'002_ga',
        //             ],

        //             '2'=>[
        //                 'send_address_id'=>'',
        //                 'send_qu'=>'43',
        //                 'send_qu_name'=>'',
        //                 'send_shi_name'=>'',
        //                 'send_address'=>'小浪底003',
        //                 'send_address_longitude'=>'121.471732',
        //                 'send_address_latitude'=>'31.231518',
        //                 'send_name'=>'张三003',
        //                 'send_tel'=>'003',
        //                 'good_name'=>'产品名称003',
        //                 'good_number'=>'50.22',
        //                 'good_weight'=>'55.22',
        //                 'good_volume'=>'14.22',
        //                 'clod'=>'freeze',
        //                 'clod_name'=>'冷藏',
        //                 'gather_address_id'=>'',
        //                 'gather_qu'=>'43',
        //                 'gather_qu_name'=>'',
        //                 'gather_shi_name'=>'',
        //                 'gather_address'=>'小浪底003_ga',
        //                 'gather_address_longitude'=>'121.471732',
        //                 'gather_address_latitude'=>'31.231518',
        //                 'gather_name'=>'张三003_ga',
        //                 'gather_tel'=>'123456_ga',
        //             ],
        ];
         **/
        $rules = [
            'order_type'=>'required',
        ];
        $message = [
            'order_type.required'=>'必须选择',
        ];

        switch ($project_type){
            case 'user':
                $company_id     = null;
                $company_name   = null;
                $group_code     = null;
                $group_name     = null;
                $receiver_id    = null;
                $total_user_id  = $user_info->total_user_id;
                break;
            case 'company':
                $company_id     = null;
                $company_name   = null;
                $group_code     = $user_info->group_code;
                $group_name     = $user_info->group_name;
                $total_user_id  = null;
                $receiver_id    = null;
                break;
            case 'TML3PL':
                $company_id     = null;
                $company_name   = null;
                $group_code     = $user_info->group_code;
                $group_name     = $user_info->group_name;
                $total_user_id  = null;
                $receiver_id    = null;
                break;
            default:
                $company_id    = null;
                $company_name    =null;
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
            if ($order_type == 'vehicle' || $order_type == 'lcl') {
                if (count($dispatcher) == 0) {
                    $msg['code'] = 302;
                    $msg['msg'] = '请填写订单信息！';
                    return $msg;
                }
            }
            /** 处理一下发货地址  及联系人**/
            foreach ($dispatcher as $k => $v){
                if ($project_type == 'company'){
                    $gather_address = $tms->address_contact($v['gather_address_id'],$v['gather_qu'],$v['gather_address'],$v['gather_name'],$v['gather_tel'],$group_info,$user_info,$now_time);
                }else{
                    $gather_address = $tms->address_contact($v['gather_address_id'],$v['gather_qu'],$v['gather_address'],$v['gather_name'],$v['gather_tel'],'',$user_info,$now_time);
                }
                if(empty($gather_address)){
                    $msg['code'] = 303;
                    $msg['msg'] = '地址不存在';
                    return $msg;
                }

                if ($project_type == 'company'){
                    $send_address=$tms->address_contact($v['send_address_id'],$v['send_qu'],$v['send_address'],$v['send_name'],$v['send_tel'],$group_info,$user_info,$now_time);
                }else{
                    $send_address=$tms->address_contact($v['send_address_id'],$v['send_qu'],$v['send_address'],$v['send_name'],$v['send_tel'],'',$user_info,$now_time);
                }
                if(empty($send_address)){
                    $msg['code'] = 303;
                    $msg['msg'] = '地址不存在';
                    return $msg;
                }

                if (empty($v['good_name'])) {
                    $msg['code'] = 306;
                    $msg['msg']  = '货物名称不能为空！';
                    return $msg;
                }

                if (empty($v['good_number']) || $v['good_number'] <= 0) {
                    $msg['code'] = 307;
                    $msg['msg']  = '货物件数错误！';
                    return $msg;
                }

                if (empty($v['good_weight']) || $v['good_weight'] <= 0) {
                    $msg['code'] = 308;
                    $msg['msg']  = '货物重量错误！';
                    return $msg;
                }

                if (empty($v['good_volume']) || $v['good_volume'] <= 0) {
                    $msg['code'] = 309;
                    $msg['msg']  = '货物体积错误！';
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
            /** 处理一下发货地址  及联系人 结束**/

            /** 开始处理正式的数据*/
            $lki    = [];
            $lki2   = [];
            $gather = [];               //定义一个收货地址的集合
            $send   = [];                 //定义一个送货地址的集合
            $good_name   = [];
            $good_number = 0;
            $good_weight = 0;
            $good_volume = 0;
            $clodss = [];

            $abccxxxxx = [];
            foreach ($dispatcher as $k => $v){
                $gather[] = $v['gather_address_id'];
                $send[] = $v['send_address_id'];
                $good_number+=$v['good_number'];
                $good_weight+=$v['good_weight'];
                $good_volume+=$v['good_volume'];
                $good_name[] = $v['good_name'];
                $clodss[] = $v['clod'];
                $abcc222['good_name']   = $v['good_name'];
                $abcc222['good_number'] = $v['good_number'];
                $abcc222['good_weight'] = $v['good_weight'];
                $abcc222['good_volume'] = $v['good_volume'];
                $abcc222['clod']        = $v['clod'];
                $abccxxxxx[] = $abcc222;
            }

            if($pick_flag == 'N' && $send_flag == 'N' && $order_type == 'line'){
                $abcc222 = [];
                $clodss = [];
                $abccxxxxx = [];
                $abcc222['good_name']   = $good_name_n;
                $abcc222['good_number'] = $good_number_n;
                $abcc222['good_weight'] = $good_weight_n;
                $abcc222['good_volume'] = $good_volume_n;
                $abcc222['clod']        = $clod;
                $abccxxxxx[] = $abcc222;
                $good_number = $good_number_n;
                $good_weight = $good_weight_n;
                $good_volume = $good_volume_n;
                $clodss[] = $clod;
            }

            $gather = array_unique($gather);
            $send   = array_unique($send);
            $clodss = array_unique($clodss);

            foreach ($gather as $k => $v){
                $lki[$k]['good_number'] = 0;
                $lki[$k]['good_weight'] = 0;
                $lki[$k]['good_volume'] = 0;
                $abccxx = [];
                $clod = [];
                foreach ($dispatcher as $kk => $vv){
                    if($v == $vv['gather_address_id']){
                        $lki[$k]['good_number']+=$vv['good_number'];
                        $lki[$k]['good_weight']+=$vv['good_weight'];
                        $lki[$k]['good_volume']+=$vv['good_volume'];
                        $lki[$k]['gather_address_id'] = $vv['gather_address_id'];

                        $lki[$k]['gather_qu'] = $vv['gather_qu'];
                        $lki[$k]['gather_qu_name'] = $vv['gather_qu_name'];
                        $lki[$k]['gather_address'] = $vv['gather_address'];
                        $lki[$k]['gather_address_longitude'] = $vv['gather_address_longitude'];
                        $lki[$k]['gather_address_latitude'] = $vv['gather_address_latitude'];
                        $lki[$k]['gather_sheng'] = $vv['gather_sheng'];
                        $lki[$k]['gather_sheng_name'] = $vv['gather_sheng_name'];
                        $lki[$k]['gather_shi'] = $vv['gather_shi'];
                        $lki[$k]['gather_shi_name'] = $vv['gather_shi_name'];
                        $lki[$k]['gather_contacts_name'] = $vv['gather_contacts_name'];
                        $lki[$k]['gather_contacts_tel'] = $vv['gather_contacts_tel'];
                        $abcc['good_name'] = $vv['good_name'];
                        $abcc['good_number'] = $vv['good_number'];
                        $abcc['good_weight'] = $vv['good_weight'];
                        $abcc['good_volume'] = $vv['good_volume'];
                        $abcc['clod']        = $vv['clod'];
                        $abccxx[] = $abcc;
                        $clod[] = $vv['clod'];
                    }
                }
                $lki[$k]['clod'] = $clod;
                $lki[$k]['good_josn'] = json_encode($abccxx,JSON_UNESCAPED_UNICODE);
            }
//            dd($lki);
            $good_info = json_encode($abccxxxxx,JSON_UNESCAPED_UNICODE);

            foreach ($send as $k => $v){
                $lki2[$k]['good_number'] = 0;
                $lki2[$k]['good_weight'] = 0;
                $lki2[$k]['good_volume'] = 0;
                $abccxx2 = [];
                $clod2 = [];
                foreach ($dispatcher as $kk => $vv){
                    if($v == $vv['send_address_id']){
                        $lki2[$k]['good_number']+=$vv['good_number'];
                        $lki2[$k]['good_weight']+=$vv['good_weight'];
                        $lki2[$k]['good_volume']+=$vv['good_volume'];
                        $lki2[$k]['send_address_id'] = $vv['send_address_id'];

                        $lki2[$k]['send_qu'] = $vv['send_qu'];
                        $lki2[$k]['send_qu_name'] = $vv['send_qu_name'];
                        $lki2[$k]['send_address'] = $vv['send_address'];
                        $lki2[$k]['send_address_longitude'] = $vv['send_address_longitude'];
                        $lki2[$k]['send_address_latitude'] = $vv['send_address_latitude'];
                        $lki2[$k]['send_sheng'] = $vv['send_sheng'];
                        $lki2[$k]['send_sheng_name'] = $vv['send_sheng_name'];
                        $lki2[$k]['send_shi'] = $vv['send_shi'];
                        $lki2[$k]['send_shi_name'] = $vv['send_shi_name'];
                        $lki2[$k]['send_contacts_name'] = $vv['send_contacts_name'];
                        $lki2[$k]['send_contacts_tel'] = $vv['send_contacts_tel'];
                        $abcc2['good_name']  = $vv['good_name'];
                        $abcc2['good_number'] = $vv['good_number'];
                        $abcc2['good_weight'] = $vv['good_weight'];
                        $abcc2['good_volume'] = $vv['good_volume'];
                        $abcc2['clod']        = $vv['clod'];
                        $abccxx2[] = $abcc2;
                        $clod2[] = $vv['clod'];
                    }
                }

                $lki2[$k]['good_josn'] = json_encode($abccxx2,JSON_UNESCAPED_UNICODE);
                $lki2[$k]['clod'] = $clod2;
            }

            /***现在处理收货地址的控制**/
            $order_id = generate_id('order_');
            /***现在处理费用的部分控制**/
            $money=[];
            /** 货到付款 **/
//            $param = TmsParam::where('type',2)->select('discount','type')->first();
            $wheres['self_id'] = $self_id;
            $old_info=TmsOrder::where($wheres)->first();

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
            $data['good_number']                = $good_number;
            $data['good_weight']                = $good_weight;
            $data['good_volume']                = $good_volume;
            $data['pick_flag']                  = $pick_flag;
            $data['send_flag']                  = $send_flag;
            $data['total_money']                = ($total_money - 0) * 100;
            $data['info']                       = json_encode($dispatcher,JSON_UNESCAPED_UNICODE);
            $data['good_info']                  = $good_info;
            $data['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
//                    $data['pick_money']                 = ($pick_money - 0)*100;
//                    $data['send_money']                 = ($send_money - 0)*100;
            $data['price']                      = ($price - 0)*100;
            $data['more_money']                 = $more_money;
            $data['pay_type']                   = $pay_type;

            $data['remark']                     = $remark;
            $data['user_type']                  = $user_type;
            $data['carpool']                    = $carpool;
            /*** 现在根据用户的这个是否提货产生出可调度的数据出来以及费用出来**/
            $inserttt=[];

            /** 做一个调度数据出来*/
            $list['order_type']                 = $order_type;
            $list['order_id']                   = $order_id;
            $list['company_id']                 = $company_id;
            $list['company_name']               = $company_name;
            $list['receiver_id']                = $receiver_id;
            $list['group_code']                 = $group_code;
            $list['group_name']                 = $group_name;
            $list['total_user_id']              = $total_user_id;
            $list['gather_time']                = $gather_time;
            $list['gather_address_id']          = $dispatcher[0]['gather_address_id'];
            $list['gather_name']                = $dispatcher[0]['gather_contacts_name'];
            $list['gather_tel']                 = $dispatcher[0]['gather_contacts_tel'];
            $list['gather_sheng']               = $dispatcher[0]['gather_sheng'];
            $list['gather_sheng_name']          = $dispatcher[0]['gather_sheng_name'];
            $list['gather_shi']                 = $dispatcher[0]['gather_shi'];
            $list['gather_shi_name']            = $dispatcher[0]['gather_shi_name'];
            $list['gather_qu']                  = $dispatcher[0]['gather_qu'];
            $list['gather_qu_name']             = $dispatcher[0]['gather_qu_name'];
            $list['gather_address']             = $dispatcher[0]['gather_address'];
            $list['gather_address_longitude']   = $dispatcher[0]['gather_address_longitude'];
            $list['gather_address_latitude']    = $dispatcher[0]['gather_address_latitude'];
            $list['send_time']                  = $send_time;
            $list['send_address_id']            = $dispatcher[0]['send_address_id'];

            $list['send_name']                  = $dispatcher[0]['send_contacts_name'];
            $list['send_tel']                   = $dispatcher[0]['send_contacts_tel'];
            $list['send_sheng']                 = $dispatcher[0]['send_sheng'];
            $list['send_sheng_name']            = $dispatcher[0]['send_sheng_name'];
            $list['send_shi']                   = $dispatcher[0]['send_shi'];
            $list['send_shi_name']              = $dispatcher[0]['send_shi_name'];
            $list['send_qu']                    = $dispatcher[0]['send_qu'];
            $list['send_qu_name']               = $dispatcher[0]['send_qu_name'];
            $list['send_address']               = $dispatcher[0]['send_address'];
            $list['send_address_longitude']     = $dispatcher[0]['send_address_longitude'];
            $list['send_address_latitude']      = $dispatcher[0]['send_address_latitude'];

            $list['line_gather_address_id']          = $dispatcher[0]['gather_address_id'];
//                    $list['line_gather_contacts_id']         = $dispatcher[0]['gather_contacts_id'];
            $list['line_gather_name']                = $dispatcher[0]['gather_contacts_name'];
            $list['line_gather_tel']                 = $dispatcher[0]['gather_contacts_tel'];
            $list['line_gather_sheng']               = $dispatcher[0]['gather_sheng'];
            $list['line_gather_sheng_name']          = $dispatcher[0]['gather_sheng_name'];
            $list['line_gather_shi']                 = $dispatcher[0]['gather_shi'];
            $list['line_gather_shi_name']            = $dispatcher[0]['gather_shi_name'];
            $list['line_gather_qu']                  = $dispatcher[0]['gather_qu'];
            $list['line_gather_qu_name']             = $dispatcher[0]['gather_qu_name'];
            $list['line_gather_address']             = $dispatcher[0]['gather_address'];
            $list['line_gather_address_longitude']   = $dispatcher[0]['gather_address_longitude'];
            $list['line_gather_address_latitude']    = $dispatcher[0]['gather_address_latitude'];
            $list['line_send_address_id']            = $dispatcher[0]['send_address_id'];
            $list['line_send_name']                  = $dispatcher[0]['send_contacts_name'];
            $list['line_send_tel']                   = $dispatcher[0]['send_contacts_tel'];
            $list['line_send_sheng']                 = $dispatcher[0]['send_sheng'];
            $list['line_send_sheng_name']            = $dispatcher[0]['send_sheng_name'];
            $list['line_send_shi']                   = $dispatcher[0]['send_shi'];
            $list['line_send_shi_name']              = $dispatcher[0]['send_shi_name'];
            $list['line_send_qu']                    = $dispatcher[0]['send_qu'];
            $list['line_send_qu_name']               = $dispatcher[0]['send_qu_name'];
            $list['line_send_address']               = $dispatcher[0]['send_address'];
            $list['line_send_address_longitude']     = $dispatcher[0]['send_address_longitude'];
            $list['line_send_address_latitude']      = $dispatcher[0]['send_address_latitude'];

            $list['on_line_flag']               = 'Y';
            $list['on_line_money']              = $total_money*100;
            $list['good_number']                = $good_number;
            $list['good_weight']                = $good_weight;
            $list['good_volume']                = $good_volume;
            $list['dispatch_flag']              = 'Y';
            $list['pick_flag']                  = $pick_flag;
            $list['send_flag']                  = $send_flag;
            $list['info']                       = json_encode($dispatcher,JSON_UNESCAPED_UNICODE);
            $list['good_info']                  = $good_info;
            $list['clod']                       = json_encode($clodss,JSON_UNESCAPED_UNICODE);
            $list['pay_type']                   = $pay_type;
            $list['remark']                     = $remark;
            $list['user_type']                  = $user_type;
            $list['reduce_price']               = $reduce_price;
            $list['carpool']                    = $carpool;
            if ($pay_type == 'offline'){
                $list['order_status'] = 2;
            }

            if($old_info){

            }else{
                $list['self_id']          = generate_id('patch_');
                $list['total_user_id']    = $total_user_id;
                $list['create_time']      = $list['update_time'] = $now_time;
            }
            /** 存储费用 **/
            if ($pay_type == 'offline'){
                switch ($project_type){
                    case 'user':
                        $money['fk_total_user_id']           = $user_info->total_user_id;
                        $money['fk_type']                    = 'USER';
                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                        break;
                    case 'customer':
                        $money['shouk_group_code']           = $user_info->group_code;
                        $money['shouk_type']                 = 'GROUP_CODE';
                        $money['fk_company_id']              = $user_info->company_id;
                        $money['fk_type']                    = 'COMPANY';
                        $money['ZIJ_company_id']             = $user_info->company_id;
                        break;
                    case 'company':
                        $money['fk_group_code']              = $user_info->group_code;
                        $money['fk_type']                    = 'GROUP_CODE';
                        $money['ZIJ_group_code']             = $user_info->group_code;
                        break;
                }
            }else{
                switch ($project_type){
                    case 'user':
                        $money['shouk_group_code']           = '1234';
                        $money['shouk_type']                 = 'PLATFORM';
                        $money['fk_total_user_id']           = $user_info->total_user_id;
                        $money['fk_type']                    = 'USER';
                        $money['ZIJ_total_user_id']          = $user_info->total_user_id;
                        $money['delete_flag']                = 'N';
                        break;
                    case 'customer':
                        $money['shouk_group_code']           = '1234';
                        $money['shouk_type']                 = 'PLATFORM';
                        $money['fk_group_code']              = $user_info->group_code;
                        $money['fk_type']                    = 'GROUP_CODE';
                        $money['ZIJ_company_id']             = $user_info->company_id;
                        $money['delete_flag']                = 'N';
                        break;
                    case 'company':
                        $money['shouk_group_code']           = '1234';
                        $money['shouk_type']                 = 'PLATFORM';
                        $money['fk_group_code']              = $user_info->group_code;
                        $money['fk_type']                    = 'GROUP_CODE';
                        $money['ZIJ_group_code']             = $user_info->group_code;
                        $money['delete_flag']                = 'N';
                        break;
                }
            }
            $money['self_id']                    = generate_id('order_money_');
            $money['order_id']                   = $order_id;
            $money['dispatch_id']                = $list['self_id'];
            $money['create_time']                = $now_time;
            $money['update_time']                = $now_time;
            $money['money']                      = $total_money*100;
            $money['money_type']                 = 'freight';
            $money['type']                       = 'in';
            $money['settle_flag']                = 'W';
            $inserttt[] = $list;
            if($old_info){
                $orderid = $self_id;
                $data['update_time'] = $now_time;
                $id = TmsOrder::where($wheres)->update($data);
            }else{
                $data['self_id']          = $order_id;
                $data['group_code']       = $group_code;
                $data['group_name']       = $group_name;
                $data['company_id']       = $company_id;
                $data['company_name']       = $company_name;
                $data['total_user_id']   = $total_user_id;
                $data['create_time']      = $data['update_time'] = $now_time;
                $orderid = $data['self_id'];
                if ($pay_type == 'offline'){
                    $data['order_status'] = 2;
                }
                $id = TmsOrder::insert($data);
                TmsOrderDispatch::insert($inserttt);
                TmsOrderCost::insert($money);

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
            /***二次效验结束**/
        }else{
            //前端用户验证没有通过
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg'] = null;
            foreach ($erro as $k => $v){
                $kk = $k+1;
                $msg['msg'].=$kk.'：'.$v;
            }
            return $msg;
        }
    }

}
?>

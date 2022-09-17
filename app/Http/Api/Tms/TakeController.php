<?php
namespace App\Http\Api\Tms;

use App\Http\Controllers\DetailsController as Details;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsCar;
use App\Models\Tms\TmsCarriage;
use App\Models\Tms\TmsCarriageDispatch;
use App\Models\Tms\TmsCarriageDriver;
use App\Models\Tms\TmsFastCarriage;
use App\Models\Tms\TmsFastCarriageDriver;
use App\Models\Tms\TmsFastDispatch;
use App\Models\Tms\TmsFastReceipt;
use App\Models\Tms\TmsGroup;
use App\Models\Tms\TmsLittleOrder;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderCost;
use App\Models\Tms\TmsOrderDispatch;
use App\Http\Controllers\Controller;
use App\Models\Tms\TmsOrderMoney;
use App\Models\Tms\TmsReceipt;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Stmt\DeclareDeclare;
use App\Http\Controllers\TmsController as Tms;

class TakeController extends Controller{
    /**
     *  APP订单列表订单状态  /api/take/orderList
     * */
    public function orderList(){
        $order_state_type        =config('tms.take_state_type');
        $data['page_info']      =$order_state_type;
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }
    /***    上线订单分页      /api/take/onlinePage
     */
    public function onlinePage(Request $request){
        $pay_status     =config('tms.tms_order_status_type');
        $dispatch_status     =config('tms.tms_dispatch_type');
        $online_status     =config('tms.tms_online_type');
        /** 接收中间件参数**/

        $carriage_flag    =array_column(config('tms.carriage_flag'),'name','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        $tms_control_type = array_column(config('tms.tms_control_type'),'name','key');
        $tms_line_type    = array_column(config('tms.tms_line_type'),'name','key');
        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $group_code     =$request->input('group_code');
        $startcity      =$request->input('startcity')??'';
        $endcity        =$request->input('endcity')??'';
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $where=[
            ['on_line_flag','=','Y'],
            ['pay_type','=','online'],
            ['order_status','=',2]
        ];
        $where1 = [
            ['on_line_flag','=','Y'],
            ['pay_type','=','offline'],
            ['order_status','=',2]
        ];
        if ($startcity){
            $where[] = ['send_shi_name','=',$startcity];
            $where1[] = ['send_shi_name','=',$startcity];
        }
        if ($endcity){
            $where[] = ['gather_shi_name','=',$endcity];
            $where1[] = ['gather_shi_name','=',$endcity];
        }
        $select=['self_id','order_type','order_status','receiver_id','clod','gather_time','send_time','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
            ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','line_gather_address_id','line_gather_contacts_id','line_gather_name','line_gather_tel',
            'line_gather_sheng','line_gather_shi','line_gather_qu','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name' , 'line_gather_address','order_id',
            'line_gather_address_longitude','line_gather_address_latitude','line_send_address_id','line_send_contacts_id','line_send_name','line_send_tel', 'line_send_sheng','line_send_shi',
            'line_send_qu','line_send_sheng_name','line_send_shi_name','line_send_qu_name','line_send_address','line_send_address_longitude','line_send_address_latitude','total_user_id','car_type'
        ];
        $select1 = ['self_id','parame_name'];
        $data['total']=TmsOrderDispatch::where($where)->orWhere($where1)->whereNull('receiver_id')->count(); //总的数据量
        $data['items']=TmsOrderDispatch::with(['tmsCarType'=>function($query)use($select1){
            $query->select($select1);
        }])

        ->where($where)->orWhere($where1)->whereNull('receiver_id')
            ->offset($firstrow)->limit($listrows)->orderBy('update_time', 'desc')
            ->select($select)->get();
        $data['group_show']='Y';

        foreach ($data['items'] as $k=>$v) {
            $v->order_type_show=$tms_order_type[$v->order_type]??null;
            $v->total_money = number_format($v->total_money/100);
            $v->on_line_money = number_format($v->on_line_money/100);
            $temperture = json_decode($v->clod);
            foreach ($temperture as $kk => $vv){
                $temperture[$kk]    = $tms_control_type[$vv] ?? null;
            }
            $v->clod = implode(',',$temperture);
            $v->order_type       = $tms_line_type[$v->order_type] ?? null;
            $v->order_id_show    = substr($v->self_id,15);
            $v->send_time        = date('m-d H:i',strtotime($v->send_time));
            $v->gather_time      = date('m-d H:i',strtotime($v->gather_time));
            if ($v->tmsCarType){
                $v->car_type_show = $v->tmsCarType->parame_name;
            }
            $v->start_time_show = '装车时间 '.$v->send_time;
            $v->end_time_show = '送达时间 '.$v->gather_time;
            if ($v->tmsCarType){
                $v->car_show = '车型 '.$v->tmsCarType->parame_name;
            }
            $v->temperture_show = '温度 '.$v->clod;
            $v->background_color_show = '#0088F4';
            $v->text_color_show = '#000000';
            if($v->order_type == 'vehicle' || $v->order_type == 'lcl' || $v->order_type == 'lift'){
                $v->background_color_show = '#E4F3FF';
                $v->order_type_font_color = '#0088F4';
                if ($v->order_type == 'vehicle'){
                    $v->background_color_show = '#0088F4';
                    $v->order_type_font_color = '#FFFFFF';
                }
            }
        }
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /***    上线订单详情     /api/take/details
     */
    public function  details(Request $request,Details $details){
        $self_id=$request->input('self_id');
        $table_name='tms_order_dispatch';
        $select=['self_id','order_type','order_status','company_name','dispatch_flag','receiver_id','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
            ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','line_gather_address_id','line_gather_contacts_id','line_gather_name','line_gather_tel',
            'line_gather_sheng','line_gather_shi','line_gather_qu','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name' , 'line_gather_address','info','gather_time','send_time','send_tel','send_name','gather_name','gather_tel',
            'line_gather_address_longitude','line_gather_address_latitude','line_send_address_id','line_send_contacts_id','line_send_name','line_send_tel', 'line_send_sheng','line_send_shi','remark','receipt_flag','order_id','kilometre',
            'line_send_qu','line_send_sheng_name','line_send_shi_name','line_send_qu_name','line_send_address','line_send_address_longitude','line_send_address_latitude','clod','pick_flag','send_flag','pay_type','pay_status'
        ];
//        $self_id='patch_202106231821248642627728';
//        $info=$details->details($self_id,$table_name,$select);
        $selectList = ['self_id','receipt','order_id','total_user_id','group_code','group_name'];
        $select2 = ['self_id','carriage_id','order_dispatch_id'];
        $select3 = ['self_id','company_id','company_name','carriage_flag','total_money'];
        $select4 = ['carriage_id','car_number','contacts','tel','price','car_id'];
        $info = TmsOrderDispatch::with(['tmsReceipt'=>function($query)use($selectList,$select2,$select3,$select4){
            $query->where('delete_flag','=','Y');
            $query->select($selectList);
        }])
            ->with(['tmsCarriageDispatch'=>function($query)use($select2,$select3,$select4){
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
            }])
            ->where('self_id',$self_id)->select($select)->first();

        if($info){
            $tms_order_type          =array_column(config('tms.tms_order_type'),'name','key');
            $tms_control_type        =array_column(config('tms.tms_control_type'),'name','key');
            $tms_pay_type            =array_column(config('tms.pay_type'),'name','key');

            /** 如果需要对数据进行处理，请自行在下面对 $info 进行处理工作*/
            $info->total_money=number_format($info->total_money/100,2);
            $info->on_line_money=number_format($info->on_line_money/100,2);
            $info->good_info=json_decode($info->good_info,true);
            $info->clod=json_decode($info->clod,true);
            $info->info=json_decode($info->info,true);
            $info->order_type_show=$tms_order_type[$info->order_type]??null;
            $info->pay_type_show = $tms_pay_type[$info->pay_type]??null;
            if ($info->pay_status == 'Y' && $info->pay_type == 'offline'){
                $info->pay_state = '已付款';
            }elseif($info->pay_type == 'online'){
                $info->pay_state = '已付款';
            }elseif($info->pay_type == 'offline' && $info->pay_status == 'N'){
                $info->pay_state = '未付款';
            }elseif(!$info->pay_type && $info->pay_status == 'N'){
                $info->pay_state = '未付款';
            }
            $info->payStatus = $info->pay_status;
            $info->pay_status = $tms_pay_type[$info->pay_type];
            $info_clod = $info->clod;
            $info->self_id_show  = substr($info->self_id,15);
            foreach ($info_clod as $key => $value){
                $info_clod[$key]=$tms_control_type[$value]??null;
            }
            $info->clod = $info_clod;
            $info_good_info = $info->good_info;
            foreach ($info_good_info as $k => $v){
                $info_good_info[$k]['clod']=$tms_control_type[$v['clod']]??null;
            }
            $info->good_info = $info_good_info;

            $temperture = $info->clod;
            foreach ($temperture as $key => $value){
                $temperture[$key] = $value;
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
            $info->temperture = implode(',',$temperture);
            if ($info->tmsReceipt){
                $receipt_info = img_for($info->tmsReceipt->receipt,'more');
                $info->receipt = $receipt_info;
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
            $order_details1['name'] = '订单金额';
            $order_details1['value'] = '¥'.$info->on_line_money;
            $order_details1['color'] = '#FF7A1A';
            $order_details7['name'] = '是否付款';
            $order_details7['value'] = $info->pay_state;
            $order_details7['color'] = '#FF7A1A';
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
                $order_details3['name'] = '提货时间';
                $order_details3['value'] = $info->send_time;
                $order_details5['color'] = '#000000';
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

            $order_details[] = $order_details1;
            $order_details[]= $order_details7;

            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl' || $info->order_type == 'lift'){
                if ($info->kilometre){
                    $order_details[] = $order_details2;
                }
                $order_details[] = $order_details3;
                $order_details[]= $order_details4;
                $order_details[]= $order_details5;
                $order_details[]= $order_details6;
            }else{
//                $order_details[]= $order_details7;
//                $order_details[]= $order_details8;
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
     * 已接单列表      /api/take/orderPage
     */
    public function orderPage(Request $request){
        $now_time     = date('Y-m-d H:i:s',time());
        $user_info    = $request->get('user_info');
        $pay_status     =config('tms.tms_order_status_type');
        $order_status     = $request->post('status');//接收中间件产生的参数
        $project_type     = $request->get('project_type');
        $button_info      = $request->get('buttonInfo');

        $select=['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
            ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','line_gather_address_id','line_gather_contacts_id','line_gather_name','line_gather_tel',
            'line_gather_sheng','line_gather_shi','line_gather_qu','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name' , 'line_gather_address','clod','create_time','receipt_flag','order_id',
            'line_gather_address_longitude','line_gather_address_latitude','line_send_address_id','line_send_contacts_id','line_send_name','line_send_tel', 'line_send_sheng','line_send_shi','send_time','gather_time',
            'line_send_qu','line_send_sheng_name','line_send_shi_name','line_send_qu_name','line_send_address','line_send_address_longitude','line_send_address_latitude','pick_flag','send_flag','car_type','pay_type','pay_status'
        ];
        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'use_flag','value'=>'Y'],
            ['type'=>'=','name'=>'receiver_id','value'=>$user_info->total_user_id],
            ['type'=>'!=','name'=>'order_type','value'=>'lift'],
        ];
        $select2 = ['self_id','parame_name'];
        $where  = get_list_where($search);
        $data['info'] = TmsOrderDispatch::with(['tmsCarType' => function($query) use($select2){
            $query->select($select2);
        }])->where($where);
        if ($order_status){
            if ($order_status == 1){
                $data['info'] = $data['info']->whereIn('order_status',[2,3]);
            }elseif($order_status == 2){
                $data['info'] = $data['info']->whereIn('order_status',[4,5]);
            }else{
                $data['info'] = $data['info']->where('order_status',6);
            }
        }
        $data['info'] = $data['info']->orderBy('create_time','desc')->select($select)->get();
//        dd($data['info']);

        $tms_pick_type    = array_column(config('tms.tms_pick_type'),'name','key');
        $tms_send_type    = array_column(config('tms.tms_send_type'),'name','key');
        $tms_control_type = array_column(config('tms.tms_control_type'),'name','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');

        foreach ($data['info'] as $k=>$v) {
            $v->on_line_money      = number_format($v->on_line_money/100,2);
            $v->self_id_show       = substr($v->self_id,15);
            $v->send_time          = date('m-d H:i',strtotime($v->send_time));
            $temperture = json_decode($v->clod);
            foreach ($temperture as $kk => $vv){
                $temperture[$kk]    = $tms_control_type[$vv] ?? null;
            }
            if ($v->order_type == 'vehicle' || $v->order_type == 'lift'){
                if ($v->tmsCarType){
                    $v->car_type_show = $v->tmsCarType->parame_name;
                }
            }
            $v->temperture = $temperture;
            $v->order_type_show=$tms_order_type[$v->order_type]??null;
            $v->pay_status_color=$pay_status[$v->order_status-1]['pay_status_color']??null;
            $v->pay_status_text=$pay_status[$v->order_status-1]['pay_status_text']??null;

            if($v->order_type == 'vehicle' || $v->order_type == 'lift'){
                $v->picktime_show = '装车时间 '.$v->send_time;
            }else{
                $v->picktime_show = '提货时间 '.$v->send_time;
            }

            $v->temperture_show ='温度 '.$v->temperture[0];
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
            switch ($project_type){
                case 'carriage':
                    foreach ($button_info as $key => $value){
                        if ($value->id == 120 ){
                            $button1[] = $value;
                        }
                        if ($value->id == 121){
                            $button1[] = $value;
                        }
                        if ($value->id == 122){
                            $button2[] = $value;
                        }
//                        if ($value->id == 123){
//                            $button2[] = $value;
//                        }
                        if ($value->id == 123){
                            $button2[] = $value;
                            $button3[] = $value;
                            $button5[] = $value;
                        }
                        if ($value->id == 142){
                            $button4[] = $value;
                        }
                        if ($value->id == 229){
                            $button5[] = $value;
                            $button6[] = $value;
                        }
                        if ($v->order_status == 2 || $v->order_status == 3){
                            $v->button  = $button1;
                        }
                        if ($v->order_status == 4 ){
                            $v->button  = $button2;
                        }
                        if ($v->order_status == 5 && $v->receipt_flag == 'N'){
                            $v->button  = $button3;
                        }
                        if ($v->order_status == 5 && $v->receipt_flag == 'N' && $v->pay_type == 'offline' && $v->pay_status == 'N'){
                            $v->button = $button5;
                        }
                        if ($v->order_status == 5 && $v->receipt_flag == 'Y' && $v->pay_type == 'offline' && $v->pay_status == 'N'){
                            $v->button = $button6;
                        }
                        if ($v->order_status == 6  && $v->pay_type == 'offline' && $v->pay_status == 'N' && $v->receipt_flag == 'N'){
                            $v->button = $button6;
                        }
//                        if ($v->receipt_flag == 'Y'){
//                            $v->button  = $button4;
//                        }
                        if ($v->receipt_flag == 'Y' && $v->pay_type == 'online'){
                            $v->button  = [];
                        }

                    }
                    break;
                case 'customer':

                    break;
            }
        }
        $msg['code'] = 200;
        $msg['msg']  = '数据拉取成功！';
        $msg['data'] = $data;
        return $msg;
    }

    /**
     * 接单       /api/take/addTake
     * */
    public function addTake(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $total_user_id = $user_info->total_user_id;
        $token_name    = $user_info->token_name;
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $dispatch_id       = $request->input('dispatch_id');

        /*** 虚拟数据
        $input['dispatch_id']           =$dispatch_id='dispatch_202101282034423534882996';
         **/
        $rules = [
            'dispatch_id'=>'required',
        ];
        $message = [
            'dispatch_id.required'=>'请选择接取的订单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];

            $select=['self_id','order_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address',
                'send_sheng_name','send_shi_name','send_qu_name','send_address',
                'good_info','good_number','good_weight','good_volume','total_money','on_line_money','total_user_id','group_code'];
            $select1 = ['tel','self_id','total_user_id'];
            $select2 = ['total_user_id','group_code'];
            $wait_info=TmsOrderDispatch::with(['userReg'=>function($query)use($select1) {
                $query->where('delete_flag','Y');
                $query->select($select1);
            }])
                ->with(['userIdentity'=>function($query)use($select2,$select1) {
                    $query->where('delete_flag','Y');
                    $query->select($select2);
                    $query->with(['userId' => function($query)use($select1) {
                        $query->select($select1);
                    }]);
                }])
                ->where($where)->select($select)->first();

            $order_where = [
                ['self_id','=',$wait_info->order_id]
            ];
            $order_update['order_status'] = 3;


            if($wait_info->on_line_flag == 'N' && $wait_info->receiver_id){
                $msg['code'] = 304;
                $msg['msg'] = '您选择的订单已被承接';
                return $msg;
            }
            if ($wait_info->total_user_id == $user_info->total_user_id){
                $msg['code'] = 305;
                $msg['msg'] = '不可以接自己的订单';
                return $msg;
            }

            $update['receiver_id']       =$user_info->total_user_id;
            $update['dispatch_flag']     ='Y';
            $update['on_line_flag']      ='N';
            $update['order_status']      = 3;
            $update['update_time']        =$now_time;
            DB::beginTransaction();
            try {
                $order = TmsOrder::where($order_where)->update($order_update);
                $id = TmsOrderDispatch::where($where)->update($update);

                /** 保存应收及应付对象**/
                if ($wait_info->pay_type == 'online'){
                    $money['shouk_total_user_id']           = $user_info->total_user_id;
                    $money['shouk_type']                    = 'USER';
                    $money['fk_group_code']                 = '1234';
                    $money['fk_type']                       = 'PLATFORM';
                    $money['ZIJ_total_user_id']             = $user_info->total_user_id;
                    $money['order_id']                      = $wait_info->order_id;
                    $money['create_time']                   = $now_time;
                    $money['update_time']                   = $now_time;
                    $money['money']                         = $wait_info->on_line_money*100;
                    $money['money_type']                    = 'freight';
                    $money['type']                          = 'out';
                    TmsOrderCost::insert($money);
                }else{
                    $money_where = [
                        ['order_id','=',$wait_info->order_id],
                        ['delete_flag','=','Y'],

                    ];
                    $money['shouk_total_user_id']           = $user_info->total_user_id;
                    $money['shouk_type']                    = 'USER';
                    TmsOrderCost::where($money_where)->update($money);
                }

                /*** 发送短信通知用户已有司机接单**/
                if($wait_info->total_user_id){
                    if ($wait_info->userReg){
                        $templateCode = 'SMS_250970604';
                        $message = message_send($wait_info->userReg->tel,$wait_info->send_shi_name,$wait_info->gather_shi_name,$templateCode);
                    }
                }else{
                    if ($wait_info->userIdentity){
                        $a = $wait_info->userIdentity;
                        foreach($a as $key =>$value ){
                            foreach($value->userId as $k =>$v){
                                $tel[] = $v['tel'];
                            }
                        }
                        $templateCode = 'SMS_250970604';
                        $message = message_send(implode(',',array_unique($tel)),$wait_info->send_shi_name,$wait_info->gather_shi_name,$templateCode);
                    }
                }
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
     * 取消接单 /api/take/order_cancel
     * */
    public function order_cancel(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $dispatch_id       = $request->input('dispatch_id');

        /*** 虚拟数据
        $input['dispatch_id']           =$dispatch_id='dispatch_202101282034423534882996';
         **/
        $rules = [
            'dispatch_id'=>'required',
        ];
        $message = [
            'dispatch_id.required'=>'请取消的订单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];
            $select=['self_id','order_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address',
                'send_sheng_name','send_shi_name','send_qu_name','send_address',
                'good_info','good_number','good_weight','good_volume','total_money','on_line_money'];
            $wait_info=TmsOrderDispatch::where($where)->select($select)->first();
            $order_where = [
                ['self_id','=',$wait_info->order_id]
            ];
            $order_update['order_status'] = 2;
            $order = TmsOrder::where($order_where)->update($order_update);

            if ($wait_info->order_status == 4){
                $msg['code'] = 305;
                $msg['msg'] = '此订单已调度不可以取消';
                return $msg;
            }

            $update['receiver_id']       = null;
            $update['dispatch_flag']     ='N';
            $update['on_line_flag']      ='Y';
            $update['order_status']      = 2;
            $update['update_time']        =$now_time;
            $id = TmsOrderDispatch::where($where)->update($update);

            /** 设置费用表里的收款对象为null **/
            $money_where = [
                ['order_id','=',$wait_info->order_id],
                ['delete_flag','=','Y'],
                ['shouk_type','=','USER']
            ];
            if ($wait_info->pay_type == 'online'){
                 $money['delete_flag'] = 'N';
            }else{
                $money['shouk_total_user_id']           = null;
                $money['shouk_type']                    = null;
            }

            TmsOrderCost::where($money_where)->update($money);

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
     * 调度  /api/take/dispatch_order
     * */
    public function dispatch_order(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $dispatch_id       = $request->input('dispatch_id');
        $car_id       = $request->input('car_id');
        $contacts       = $request->input('contacts');
        $tel            = $request->input('tel');

        /*** 虚拟数据
        $input['dispatch_id']           =$dispatch_id='dispatch_202101282034423534882996';
        $input['car_id']                =$car_id='car_202101281717140184141326';
        $input['contacts']              =$contacts='李白';
        $input['tel']                   =$tel='13256454879';
         **/
        $rules = [
            'dispatch_id'=>'required',
            'car_id'=>'required',

        ];
        $message = [
            'dispatch_id.required'=>'请选择调度订单',
            'car_id.required'=>'请选择车辆',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];
            $car_where=[
                ['use_flag','=','Y'],
                ['delete_flag','=','Y'],
                ['self_id','=',$car_id],
            ];
            $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','order_id',
                'send_sheng_name','send_shi_name','send_qu_name','send_address',
                'good_info','good_number','good_weight','good_volume','total_money','on_line_money'];
            $wait_info=TmsOrderDispatch::where($where)->select($select)->first();
            $car = TmsCar::where($car_where)->first();
            //调度订单修改订单状态
            $order_where = [
                ['self_id','=',$wait_info->order_id]
            ];
            $order_update['order_status'] = 4;
            $order_update['update_time']  = $now_time;
            $order = TmsOrder::where($order_where)->update($order_update);
            TmsOrderDispatch::where($where)->update($order_update);

            $carriage_id = generate_id('carriage_');

            $list['self_id']            =generate_id('c_d_');
            $list['order_dispatch_id']        = $dispatch_id;
            $list['carriage_id']        = $carriage_id;
            $list['total_user_id']         = $user_info->total_user_id;
            $list['create_user_id']     = $user_info->total_user_id;
            $list['create_time']        =$list['update_time']=$now_time;


            $order_list['self_id']            =generate_id('driver_');
            $order_list['carriage_id']        = $carriage_id;
            $order_list['total_user_id']         = $user_info->total_user_id;
            $order_list['create_user_id']     = $user_info->total_user_id;
            $order_list['create_time']        =$order_list['update_time']=$now_time;
            $order_list['car_id']   = $car_id;


            $order_list['car_number']   =  $car->car_number;
            $order_list['contacts']   =  $contacts;
            $order_list['tel']   = $tel;
            $order_list['price'] = $wait_info->total_money;

            /**保存应付费用**/

            $data['self_id']            = $carriage_id;
            $data['create_user_id']     = $user_info->admin_id;
            $data['create_user_name']   = $user_info->name;
            $data['create_time']        = $data['update_time']=$now_time;
            $data['total_user_id']        = $user_info->total_user_id;
            $data['total_money']        = $wait_info->on_line_money;
            $data['carriage_flag']   =  'compose';
            $data['order_status']   =  2;

            $id = TmsCarriage::insert($data);
            TmsCarriageDispatch::insert($list);

            TmsCarriageDriver::insert($order_list);

            if($order){
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
     * 取消调度(司机) /api/take/dispatch_cancel
     * */
    public function dispatch_cancel(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $dispatch_id       = $request->input('dispatch_id');
        /*** 虚拟数据
        $input['dispatch_id']           =$dispatch_id='dispatch_202101282034423534882996';
         **/
        $rules = [
            'dispatch_id'=>'required',
        ];
        $message = [
            'dispatch_id.required'=>'请选择调度订单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];

            $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address',
                'send_sheng_name','send_shi_name','send_qu_name','send_address','order_id',
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
                    if (explode('',$arr) == 3){
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

            //修改当前运输单状态
            $dispatch_order['order_status']  = 3;
            $dispatch_order['dispatch_flag'] = 'Y';
            $dispatch_order['update_time']   = $now_time;
            $id = TmsOrderDispatch::where($where)->update($dispatch_order);


            //删除所有关联运输数据
            $list['delete_flag']         = 'N';
            $list['update_time']         = $now_time;

            TmsCarriageDispatch::where('self_id',$wait_info->tmsCarriageDispatch->self_id)->update($list);
            foreach ($wait_info->tmsCarriageDispatch->tmsCarriage as $key => $value){
                TmsCarriage::where('self_id',$value->self_id)->update($list);
            }
            foreach($wait_info->tmsCarriageDispatch->tmsCarriageDriver as $k => $v){
                TmsCarriageDriver::where($v->self_id)->update($list);
            }

            /*** 取消应付款**/

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
     * 运输完成  /api/take/carriage_done
     * */
    public function carriage_done(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $dispatch_id       = $request->input('self_id');
        /*** 虚拟数据
        $input['self_id']           =$dispatch_id='patch_202105091028035791605429';
         **/
        $rules = [
            'self_id'=>'required',
        ];
        $message = [
            'self_id.required'=>'请选择调度订单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];

            $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','order_status','order_id',
                'send_sheng_name','send_shi_name','send_qu_name','send_address',
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
            //调度订单修改订单状态
            $order_where = [
                ['self_id','=',$wait_info->order_id]
            ];
            $dispatch_where = [
                ['order_id','=',$wait_info->order_id]
            ];

            //判断是否所有的运输单状态是否都为调度，是修改订单状态为已完成
            $tmsOrderDispatch = TmsOrderDispatch::where($dispatch_where)->select(['self_id'])->get();
            if ($tmsOrderDispatch){
                $dispatch_list = array_column($tmsOrderDispatch->toArray(),'self_id');
                $orderStatus = TmsOrderDispatch::where('self_id','!=',$dispatch_id)->whereIn('self_id',$dispatch_list)->select(['order_status'])->get();
                $arr = array_unique(array_column($orderStatus->toArray(),'order_status'));
                if (count($arr) >= 1){
                    if (implode('',$arr) == 5){
                        $order_update['order_status'] = 5;
                        $order_update['update_time']  = $now_time;
                        $order = TmsOrder::where($order_where)->update($order_update);
                    }
                }else{
                    $order_update['order_status'] = 5;
                    $order_update['update_time']  = $now_time;
                    $order = TmsOrder::where($order_where)->update($order_update);
                }
            }
            //修改当前运输单状态
            $dispatch_order['order_status']  = 5;
            $dispatch_order['update_time']   = $now_time;
            $id = TmsOrderDispatch::where($where)->update($dispatch_order);
            $carriage_order['order_status']  = 4;
            $carriage_order['update_time']   = $now_time;
            foreach ($wait_info->tmsCarriageDispatch->tmsCarriage as $key => $value){
                TmsCarriage::where('self_id',$value->self_id)->update($carriage_order);
            }

            /*** 添加冻结余额**/
            $wallet = UserCapital::where('total_user_id',$wait_info->receiver_id)->select(['self_id', 'money','wait_money'])->first();

            $money['wait_money'] = $wallet->wait_money + $wait_info->on_line_money;
            $data['money'] = $wait_info->on_line_money;

            $money['update_time'] = date('Y-m-d H:i:s',time());
            UserCapital::where('total_user_id',$wait_info->receiver_id)->update($money);

            $data['self_id'] = generate_id('wallet_');
            $data['produce_type'] = 'in';
            $data['capital_type'] = 'wallet';
            $data['create_time'] = date('Y-m-d H:i:s',time());
            $data['update_time'] = date('Y-m-d H:i:s',time());
            $data['total_user_id'] = $wait_info->receiver_id;
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
    * 洗数据公司资金
    * */
    public function get_code(){
        $company = SystemGroup::select(['self_id','group_code','group_name'])->get();
//        dd($company);
        foreach ($company as $key => $value){
//            dd($value->group_code);
            $where = [
                ['group_code','=',$value['group_code']]
            ];
            $wallet = UserCapital::where($where)->select(['self_id'])->first();
//            dd($wallet);
            if (empty($wallet)){
                $capital_data['self_id']        = generate_id('capital_');
                $capital_data['total_user_id']  = null;
                $capital_data['group_code']    =$value->group_code;
                $capital_data['group_name']    =$value->group_name;
                $capital_data['update_time'] = date('Y-m-d H:i:s',time());
                UserCapital::insert($capital_data);						//写入用户资金表
            }
        }
    }

    /**
     * 上传回单  /api/take/upload_receipt
     * */
    public function upload_receipt(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $order_id       = $request->input('order_id');
        $receipt       = $request->input('receipt');

        /*** 虚拟数据
        $input['order_id']           =$order_id='dispatch_202101282034423534882996';
         **/
        $rules = [
            'order_id'=>'required',
            'receipt'=>'required',
        ];
        $message = [
            'order_id.required'=>'请选择调度订单',
            'receipt.required'=>'请选择要上传回单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$order_id],
            ];
            $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','order_status',
                'gather_address', 'send_sheng_name','send_shi_name','send_qu_name','send_address','good_info','good_number','good_weight','good_volume','total_money','on_line_money'];
            $wait_info=TmsOrderDispatch::where($where)->select($select)->first();
            if(!in_array($wait_info->order_status,[5,6])){
                $msg['code']=301;
                $msg['msg']='请确认订单已送达';
                return $msg;
            }
            $dispatch_update['receipt_flag'] = 'Y';
            $dispatch_update['update_time'] = $now_time;
            TmsOrderDispatch::where($where)->update($dispatch_update);
            $data['self_id'] = generate_id('receipt_');
            $data['receipt'] = img_for($receipt,'in');
            $data['order_id'] = $order_id;
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

    /*
     * /api/take/liftOrder
     * */
    public function liftOrder(Request $request){
        $now_time     = date('Y-m-d H:i:s',time());
        $user_info    = $request->get('user_info');
        $pay_status     =config('tms.tms_order_status_type');
        $order_status     = $request->post('status');//接收中间件产生的参数
        $project_type     = $request->get('project_type');
        $button_info      = $request->get('buttonInfo');

        $select=['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
            ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','line_gather_address_id','line_gather_contacts_id','line_gather_name','line_gather_tel',
            'line_gather_sheng','line_gather_shi','line_gather_qu','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name' , 'line_gather_address','clod','create_time','receipt_flag','order_id',
            'line_gather_address_longitude','line_gather_address_latitude','line_send_address_id','line_send_contacts_id','line_send_name','line_send_tel', 'line_send_sheng','line_send_shi','send_time','gather_time',
            'line_send_qu','line_send_sheng_name','line_send_shi_name','line_send_qu_name','line_send_address','line_send_address_longitude','line_send_address_latitude','pick_flag','send_flag','car_type','pay_type','pay_status'
        ];
        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'use_flag','value'=>'Y'],
            ['type'=>'=','name'=>'receiver_id','value'=>$user_info->total_user_id],
            ['type'=>'=','name'=>'order_type','value'=>'lift'],
        ];
        $select2 = ['self_id','parame_name'];
        $where  = get_list_where($search);
        $data['info'] = TmsOrderDispatch::with(['tmsCarType' => function($query) use($select2){
            $query->select($select2);
        }])->where($where);
        if ($order_status){
            if ($order_status == 1){
                $data['info'] = $data['info']->whereIn('order_status',[2,3]);
            }elseif($order_status == 2){
                $data['info'] = $data['info']->whereIn('order_status',[4,5]);
            }else{
                $data['info'] = $data['info']->where('order_status',6);
            }
        }
        $data['info'] = $data['info']->orderBy('create_time','desc')->select($select)->get();
//        dd($data['info']);

        $tms_pick_type    = array_column(config('tms.tms_pick_type'),'name','key');
        $tms_send_type    = array_column(config('tms.tms_send_type'),'name','key');
        $tms_control_type = array_column(config('tms.tms_control_type'),'name','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');

        foreach ($data['info'] as $k=>$v) {
            $v->on_line_money      = number_format($v->on_line_money/100,2);
            $v->self_id_show       = substr($v->self_id,15);
            $v->send_time          = date('m-d H:i',strtotime($v->send_time));
            $temperture = json_decode($v->clod);
            foreach ($temperture as $kk => $vv){
                $temperture[$kk]    = $tms_control_type[$vv] ?? null;
            }
            if ($v->order_type == 'vehicle' || $v->order_type == 'lift'){
                if ($v->tmsCarType){
                    $v->car_type_show = $v->tmsCarType->parame_name;
                }
            }
            $v->temperture = $temperture;
            $v->order_type_show=$tms_order_type[$v->order_type]??null;
            $v->pay_status_color=$pay_status[$v->order_status-1]['pay_status_color']??null;
            $v->pay_status_text=$pay_status[$v->order_status-1]['pay_status_text']??null;

            if($v->order_type == 'vehicle' || $v->order_type == 'lift'){
                $v->picktime_show = '装车时间 '.$v->send_time;
            }else{
                $v->picktime_show = '提货时间 '.$v->send_time;
            }

            $v->temperture_show ='温度 '.$v->temperture[0];
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
            switch ($project_type){
                case 'carriage':
                    foreach ($button_info as $key => $value){
                        if ($value->id == 120 ){
                            $button1[] = $value;
                        }
//                        if ($value->id == 121){
//                            $button1[] = $value;
//                        }
                        if ($value->id == 122){
                            $button2[] = $value;
                        }
//                        if ($value->id == 123){
//                            $button2[] = $value;
//                        }
                        if ($value->id == 123){
                            $button2[] = $value;
                            $button3[] = $value;
                            $button5[] = $value;
                        }
                        if ($value->id == 142){
                            $button4[] = $value;
                        }
                        if ($value->id == 229){
                            $button5[] = $value;
                            $button6[] = $value;
                        }
                        if ($v->order_status == 2 || $v->order_status == 3){
                            $v->button  = $button1;
                        }
                        if ($v->order_status == 4 ){
                            $v->button  = $button2;
                        }
                        if ($v->order_status == 5 && $v->receipt_flag == 'N'){
                            $v->button  = $button3;
                        }
                        if ($v->order_status == 5 && $v->receipt_flag == 'N' && $v->pay_type == 'offline' && $v->pay_status == 'N'){
                            $v->button = $button5;
                        }
                        if ($v->order_status == 5 && $v->receipt_flag == 'Y' && $v->pay_type == 'offline' && $v->pay_status == 'N'){
                            $v->button = $button6;
                        }
                        if ($v->order_status == 6  && $v->pay_type == 'offline' && $v->pay_status == 'N' && $v->receipt_flag == 'N'){
                            $v->button = $button6;
                        }
//                        if ($v->receipt_flag == 'Y'){
//                            $v->button  = $button4;
//                        }
                        if ($v->receipt_flag == 'Y' && $v->pay_type == 'online'){
                            $v->button  = [];
                        }

                    }
                    break;
                case 'customer':

                    break;
            }
        }
        $msg['code'] = 200;
        $msg['msg']  = '数据拉取成功！';
        $msg['data'] = $data;
        return $msg;
    }

    /*
     * 顺风车调度 /api/take/liftDispatch
     * */
    public function liftDispatch(Request $request,Tms $tms){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $input              =$request->all();

        /** 接收数据*/
        $dispatch_list      = $request->input('dispatch_list');
        $carriage_flag      = $request->input('carriage_flag');
        $total_price 		= $request->input('total_price');//应付费用
        $car_info 			= $request->input('car_info');//司机车辆信息

        /*** 虚拟数据
        $input['dispatch_list']     =$dispatch_list='dispatch_202101311523511804451337,dispatch_202101311514279957651368';
        $input['carriage_flag']        =$carriage_flag='driver';  // 自己oneself，个体司机driver，承运商carriers 组合compose'
        $input['car_info']         =$car_info = [['car_id'=>'','type'=>'lease','car_number'=>'沪V12784','contacts'=>'刘伯温','tel'=>'19819819819','price'=>500,'company_id'='company_202102241023099922958143'],
        ['car_id'=>'','type'=>'oneself','car_number'=>'沪V44561','contacts'=>'赵匡胤','tel'=>'16868686868','price'=>100,'company_id'='']];
        $input['total_price'] = $total_price = 200;
         * ***/
        $rules=[
            'dispatch_list'=>'required',
            'car_info'=>'required',
        ];
        $message=[
            'dispatch_list.required'=>'必须选择最少一个可调度单',
            'car_info.required'=>'请选择车辆',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {

            $where=[
                ['delete_flag','=','Y'],
            ];
            $select=['self_id','company_name','create_time','create_time','group_name','dispatch_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','total_user_id',
                'send_sheng_name','send_shi_name','send_qu_name','send_address','carpool',
                'good_info','good_number','good_weight','good_volume'];
            $select1 = ['order_id'];
            $wait_info=TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$dispatch_list))->select($select)->get();
            foreach($wait_info as $key => $value ){
                if ($value->carpool == 'N' && count($wait_info) > 1){
                    $msg['code'] = 304;
                    $msg['msg'] = '订单：'.$value->self_id.'不接受拼车，请重新调度';
                    return $msg;
                }
            }
            $orderList=TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$dispatch_list))->select($select1)->get();
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
                    $list['total_user_id']       = $user_info->total_user_id;
                    $list['create_user_id']     = $user_info->admin_id;
                    $list['create_user_name']   = $user_info->name;
                    $list['create_time']        =$list['update_time']=$now_time;

                    $datalist[]=$list;
                }
                $a++;
            }
                foreach ($car_info as $key => $value){
                    $order_list['self_id']            =generate_id('driver_');
                    $order_list['carriage_id']        = $carriage_id;
                    $order_list['total_user_id']         = $user_info->total_user_id;
                    $order_list['create_time']        =$order_list['update_time']=$now_time;
                    $order_list['car_id']   = $value['car_id'];
                    $order_list['car_number']   =  $value['car_number'];
                    $order_list['contacts']   =  $value['contacts'];
                    $order_list['tel']   = $value['tel'];
                    $order_list['price'] = $value['price']*100;
                    $order_info[]=$order_list;
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
            $data['total_user_id']      = $user_info->total_user_id;
            $data['total_money']        = $total_price*100;
            $data['carriage_flag']      = 'compose';
            $data['order_status']       = 2;
            DB::beginTransaction();
            try{
                $id=TmsCarriage::insert($data);
                TmsCarriageDispatch::insert($datalist);
                TmsOrderCost::insert($order_money);
                TmsCarriageDriver::insert($order_info);

                $data_update['dispatch_flag']      ='N';
                $data_update['order_status']       = 4;
                $data_update['update_time']        =$now_time;
                TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$dispatch_list))->update($data_update);
                if ($orderList){
                    $id_list = array_column($orderList->toArray(),'order_id');
                    $tmsOrder['order_status'] = 4;
                    $tmsOrder['update_time']  = $now_time;
                    TmsOrder::whereIn('self_id',$id_list)->update($tmsOrder);
                }
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
     * 承运端快捷订单接单列表
     * */
    public function takeOrder(Request $request){
        /** 接收中间件参数**/

        $carriage_flag    =array_column(config('tms.carriage_flag'),'name','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        $tms_control_type = array_column(config('tms.tms_control_type'),'name','key');
        $tms_line_type    = array_column(config('tms.tms_line_type'),'name','key');
        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $group_code     =$request->input('group_code');
        $startcity      =$request->input('startcity')??'';
        $endcity        =$request->input('endcity')??'';
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $where=[
            ['on_line_flag','=','Y'],
            ['pay_type','=','online'],
            ['order_status','=',2]
        ];
        $where1 = [
            ['on_line_flag','=','Y'],
            ['pay_type','=','offline'],
            ['order_status','=',2]
        ];
        if ($startcity){
            $where[] = ['send_shi_name','=',$startcity];
            $where1[] = ['send_shi_name','=',$startcity];
        }
        if ($endcity){
            $where[] = ['gather_shi_name','=',$endcity];
            $where1[] = ['gather_shi_name','=',$endcity];
        }
        $select=['self_id','order_type','order_status','receiver_id','clod','gather_time','send_time','group_code','group_name','use_flag',
            'on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
            ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume', 'gather_address_id',
            'gather_contacts_id','gather_name','gather_tel', 'gather_sheng','gather_shi','gather_qu', 'gather_address_longitude','gather_address_latitude',
            'send_address_id','send_contacts_id', 'send_name','send_tel','send_qu','send_address','send_address_longitude','send_address_latitude',
            'total_user_id',
        ];
        $select1 = ['self_id','parame_name'];
        $data['total']=TmsLittleOrder::where($where)->orWhere($where1)->whereNull('receiver_id')->count(); //总的数据量
        $data['items']=TmsLittleOrder::with(['tmsCarType'=>function($query)use($select1){
            $query->select($select1);
        }])

            ->where($where)->orWhere($where1)->whereNull('receiver_id')
            ->offset($firstrow)->limit($listrows)->orderBy('update_time', 'desc')
            ->select($select)->get();
        $data['group_show']='Y';

        foreach ($data['items'] as $k=>$v) {
            $v->order_type_show=$tms_order_type[$v->order_type]??null;
            $v->total_money = number_format($v->total_money/100);
            $temperture = json_decode($v->clod);
            foreach ($temperture as $kk => $vv){
                $temperture[$kk]    = $tms_control_type[$vv] ?? null;
            }
            $v->clod = implode(',',$temperture);
            $v->order_type       = $tms_line_type[$v->order_type] ?? null;
            $v->order_id_show    = substr($v->self_id,15);
            $v->send_time        = date('m-d H:i',strtotime($v->send_time));
            $v->gather_time      = date('m-d H:i',strtotime($v->gather_time));
            if ($v->tmsCarType){
                $v->car_type_show = $v->tmsCarType->parame_name;
            }
            $v->start_time_show = '装车时间 '.$v->send_time;
            $v->end_time_show = '送达时间 '.$v->gather_time;
            if ($v->tmsCarType){
                $v->car_show = '车型 '.$v->tmsCarType->parame_name;
            }
            $v->temperture_show = '温度 '.$v->clod;
            $v->background_color_show = '#0088F4';
            $v->text_color_show = '#000000';
            if($v->order_type == 'vehicle' || $v->order_type == 'lcl' || $v->order_type == 'lift'){
                $v->background_color_show = '#E4F3FF';
                $v->order_type_font_color = '#0088F4';
                if ($v->order_type == 'vehicle'){
                    $v->background_color_show = '#0088F4';
                    $v->order_type_font_color = '#FFFFFF';
                }
            }
        }
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    public function fastOrderPage(Request $request){
        $now_time     = date('Y-m-d H:i:s',time());
        $user_info    = $request->get('user_info');
        $pay_status     =config('tms.tms_order_status_type');
        $order_status     = $request->post('status');//接收中间件产生的参数
        $project_type     = $request->get('project_type');
        $button_info      = $request->get('buttonInfo');

        $select=['self_id','order_type','order_status','group_code','group_name','use_flag','on_line_flag','total_money','good_info','pay_status',
            'good_number','good_weight','good_volume','gather_address_id','gather_contacts_id','gather_name','gather_tel','gather_sheng','gather_shi',
            'gather_qu','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','clod','create_time','receipt_flag','gather_address_longitude',
            'gather_address_latitude','send_address_id','send_contacts_id','send_name','send_tel', 'send_sheng','send_shi','send_time','gather_time',
            'send_qu','send_sheng_name','send_shi_name','send_qu_name','send_address','send_address_longitude','send_address_latitude','pay_type',
        ];
        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'use_flag','value'=>'Y'],
            ['type'=>'=','name'=>'receiver_id','value'=>$user_info->total_user_id],
            ['type'=>'!=','name'=>'order_type','value'=>'lift'],
        ];
        $select2 = ['self_id','parame_name'];
        $where  = get_list_where($search);
        $data['info'] = TmsLittleOrder::with(['tmsCarType' => function($query) use($select2){
            $query->select($select2);
        }])->where($where);
        if ($order_status){
            if ($order_status == 1){
                $data['info'] = $data['info']->whereIn('order_status',[2,3]);
            }elseif($order_status == 2){
                $data['info'] = $data['info']->whereIn('order_status',[4,5]);
            }else{
                $data['info'] = $data['info']->where('order_status',6);
            }
        }
        $data['info'] = $data['info']->orderBy('create_time','desc')->select($select)->get();
//        dd($data['info']);


        $tms_control_type = array_column(config('tms.tms_control_type'),'name','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');

        foreach ($data['info'] as $k=>$v) {
            $v->total_money = number_format($v->total_money/100);
            $v->self_id_show       = substr($v->self_id,15);
            $v->send_time          = date('m-d H:i',strtotime($v->send_time));
            $temperture = json_decode($v->clod);
            foreach ($temperture as $kk => $vv){
                $temperture[$kk]    = $tms_control_type[$vv] ?? null;
            }
            if ($v->order_type == 'vehicle' || $v->order_type == 'lift'){
                if ($v->tmsCarType){
                    $v->car_type_show = $v->tmsCarType->parame_name;
                }
            }
            $v->temperture = $temperture;
            $v->order_type_show=$tms_order_type[$v->order_type]??null;
            $v->pay_status_color=$pay_status[$v->order_status-1]['pay_status_color']??null;
            $v->pay_status_text=$pay_status[$v->order_status-1]['pay_status_text']??null;

            if($v->order_type == 'vehicle' || $v->order_type == 'lift'){
                $v->picktime_show = '装车时间 '.$v->send_time;
            }else{
                $v->picktime_show = '提货时间 '.$v->send_time;
            }

            $v->temperture_show ='温度 '.$v->temperture[0];
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
                case 'carriage':
                    foreach ($button_info as $key => $value){
                        if ($value->id == 120 ){
                            $button1[] = $value;
                        }
                        if ($value->id == 121){
                            $button1[] = $value;
                        }
                        if ($value->id == 122){
                            $button2[] = $value;
                        }
//                        if ($value->id == 123){
//                            $button2[] = $value;
//                        }
                        if ($value->id == 123){
                            $button2[] = $value;
                            $button3[] = $value;
                            $button5[] = $value;
                        }
                        if ($value->id == 142){
                            $button4[] = $value;
                        }
                        if ($value->id == 229){
                            $button5[] = $value;
                            $button6[] = $value;
                        }
                        if ($v->order_status == 2 || $v->order_status == 3){
                            $v->button  = $button1;
                        }
                        if ($v->order_status == 4 ){
                            $v->button  = $button2;
                        }
                        if ($v->order_status == 5 && $v->receipt_flag == 'N'){
                            $v->button  = $button3;
                        }
                        if ($v->order_status == 5 && $v->receipt_flag == 'N' && $v->pay_type == 'offline' && $v->pay_status == 'N'){
                            $v->button = $button5;
                        }
                        if ($v->order_status == 5 && $v->receipt_flag == 'Y' && $v->pay_type == 'offline' && $v->pay_status == 'N'){
                            $v->button = $button6;
                        }
                        if ($v->order_status == 6  && $v->pay_type == 'offline' && $v->pay_status == 'N' && $v->receipt_flag == 'N'){
                            $v->button = $button6;
                        }
//                        if ($v->receipt_flag == 'Y'){
//                            $v->button  = $button4;
//                        }
                        if ($v->receipt_flag == 'Y' && $v->pay_type == 'online'){
                            $v->button  = [];
                        }

                    }
                    break;
                case 'customer':

                    break;
            }
        }
        $msg['code'] = 200;
        $msg['msg']  = '数据拉取成功！';
        $msg['data'] = $data;
        return $msg;
    }

    /**
     * 接单
     * */
    public function addFastTakeOrder(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $total_user_id = $user_info->total_user_id;
        $token_name    = $user_info->token_name;
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $dispatch_id       = $request->input('dispatch_id');

        /*** 虚拟数据
        $input['dispatch_id']           =$dispatch_id='dispatch_202101282034423534882996';
         **/
        $rules = [
            'dispatch_id'=>'required',
        ];
        $message = [
            'dispatch_id.required'=>'请选择接取的订单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];

            $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address', 'send_sheng_name','send_shi_name','send_qu_name',
                'send_address','good_info','good_number','good_weight','good_volume','total_money'];
            $wait_info=TmsLittleOrder::where($where)->select($select)->first();
            $order_where = [
                ['self_id','=',$wait_info->order_id]
            ];



            if($wait_info->on_line_flag == 'N' && $wait_info->receiver_id){
                $msg['code'] = 304;
                $msg['msg'] = '您选择的订单已被承接';
                return $msg;
            }
            if ($wait_info->total_user_id == $user_info->total_user_id){
                $msg['code'] = 305;
                $msg['msg'] = '不可以接自己的订单';
                return $msg;
            }

            $update['receiver_id']       =$user_info->total_user_id;
            $update['dispatch_flag']     ='Y';
            $update['on_line_flag']      ='N';
            $update['order_status']      = 3;
            $update['update_time']        =$now_time;
            $id = TmsLittleOrder::where($where)->update($update);

//            /** 保存应收及应付对象**/
//            if ($wait_info->pay_type == 'online'){
//                $money['shouk_total_user_id']           = $user_info->total_user_id;
//                $money['shouk_type']                    = 'USER';
//                $money['fk_group_code']                 = '1234';
//                $money['fk_type']                       = 'PLATFORM';
//                $money['ZIJ_total_user_id']             = $user_info->total_user_id;
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
//                $money['shouk_total_user_id']           = $user_info->total_user_id;
//                $money['shouk_type']                    = 'USER';
//                TmsOrderCost::where($money_where)->update($money);
//            }


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
     * 快捷订单接单详情
     * */
    public function takeOrderDetails(Request $request){
        $self_id=$request->input('self_id');
        $table_name='tms_little_order';
        $select=['self_id','order_type','order_status','group_code','group_name','use_flag','on_line_flag','total_money','good_info','pay_status',
            'good_number','good_weight','good_volume','gather_address_id','gather_contacts_id','gather_name','gather_tel','gather_sheng','gather_shi',
            'gather_qu','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','clod','create_time','receipt_flag','gather_address_longitude',
            'gather_address_latitude','send_address_id','send_contacts_id','send_name','send_tel', 'send_sheng','send_shi','send_time','gather_time',
            'send_qu','send_sheng_name','send_shi_name','send_qu_name','send_address','send_address_longitude','send_address_latitude','pay_type','info'
        ];
        $select1 = ['self_id','order_id','receipt','group_code','group_name','total_user_id'];
        $select2 = ['self_id','order_id','carriage_id'];
        $select3 = ['self_id'];
        $select4 = ['self_id','carriage_id','use_flag','delete_flag','group_code','group_name','car_id','car_number','contacts','tel','price'];
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
            }])->where('self_id',$self_id)->select($select)->first();

        if($info){
            $tms_order_type          =array_column(config('tms.tms_order_type'),'name','key');
            $tms_control_type        =array_column(config('tms.tms_control_type'),'name','key');
            $tms_pay_type            =array_column(config('tms.pay_type'),'name','key');

            /** 如果需要对数据进行处理，请自行在下面对 $info 进行处理工作*/
            $info->total_money=number_format($info->total_money/100,2);

            $info->good_info=json_decode($info->good_info,true);
            $info->clod=json_decode($info->clod,true);
            $info->info=json_decode($info->info,true);
            $info->order_type_show=$tms_order_type[$info->order_type]??null;
            $info->pay_type_show = $tms_pay_type[$info->pay_type]??null;
            $info_clod = $info->clod;
            $info->self_id_show  = substr($info->self_id,15);
            foreach ($info_clod as $key => $value){
                $info_clod[$key]=$tms_control_type[$value]??null;
            }
            $info->clod = $info_clod;

            $temperture = $info->clod;
            foreach ($temperture as $key => $value){
                $temperture[$key] = $value;
            }
            $car_list = [];
            if ($info->tmsFastDispatch){
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
            }
            $info->temperture = implode(',',$temperture);
            if ($info->tmsReceipt){
                $receipt_info = img_for($info->tmsReceipt->receipt,'more');
                $info->receipt = $receipt_info;
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
            $order_details1['name'] = '订单金额';
            $order_details1['value'] = '¥'.$info->total_money;
            $order_details1['color'] = '#FF7A1A';
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
            }else{
                $order_details3['name'] = '提货时间';
                $order_details3['value'] = $info->send_time;
                $order_details3['color'] = '#000000';
            }
            $order_details6['name'] = '订单备注';
            $order_details6['value'] = $info->remark;
            $order_details6['color'] = '#000000';

            $order_details9['name'] = '运输信息';
            $order_details9['value'] = $info->car_info;
            $order_details10['name'] = '回单信息';
            $order_details10['value'] = $info->receipt;

            $order_details[] = $order_details1;

            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl' || $info->order_type == 'lift'){
                if ($info->kilometre){
                    $order_details[] = $order_details2;
                }
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
            $data['info']=$info;
            $data['order_details'] = $order_details;
            $data['receipt_list'] = $receipt_list;
            $data['car_info'] = $car_info;
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
     * 快捷订单取消接单
     * */
    public function takeOrderCancel(Request $request){

            $user_info = $request->get('user_info');//接收中间件产生的参数
            $now_time      = date('Y-m-d H:i:s',time());
            $input         = $request->all();

            /** 接收数据*/
            $dispatch_id       = $request->input('dispatch_id');

            /*** 虚拟数据
            $input['dispatch_id']           =$dispatch_id='dispatch_202101282034423534882996';
             **/
            $rules = [
                'dispatch_id'=>'required',
            ];
            $message = [
                'dispatch_id.required'=>'请取消的订单',
            ];

            $validator = Validator::make($input,$rules,$message);
            if($validator->passes()) {
                $where=[
                    ['delete_flag','=','Y'],
                    ['self_id','=',$dispatch_id],
                ];
                $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                    'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address',
                    'send_sheng_name','send_shi_name','send_qu_name','send_address',
                    'good_info','good_number','good_weight','good_volume','total_money'];
                $wait_info=TmsLittleOrder::where($where)->select($select)->first();
                $order_where = [
                    ['self_id','=',$wait_info->order_id]
                ];
                $order_update['order_status'] = 2;
                $order = TmsLittleOrder::where($order_where)->update($order_update);

                if ($wait_info->order_status == 4){
                    $msg['code'] = 305;
                    $msg['msg'] = '此订单已调度不可以取消';
                    return $msg;
                }

                $update['receiver_id']       = null;
                $update['dispatch_flag']     ='N';
                $update['on_line_flag']      ='Y';
                $update['order_status']      = 2;
                $update['update_time']        =$now_time;
                $id = TmsLittleOrder::where($where)->update($update);

                /** 设置费用表里的收款对象为null **/
//                $money_where = [
//                    ['order_id','=',$wait_info->order_id],
//                    ['delete_flag','=','Y'],
//                    ['shouk_type','=','USER']
//                ];
//                if ($wait_info->pay_type == 'online'){
//                    $money['delete_flag'] = 'N';
//                }else{
//                    $money['shouk_total_user_id']           = null;
//                    $money['shouk_type']                    = null;
//                }
//
//                TmsOrderCost::where($money_where)->update($money);

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
         * 快捷订单调度
         * */
        public function addTakeFastOrder(Request $request){
            $user_info = $request->get('user_info');//接收中间件产生的参数
            $now_time      = date('Y-m-d H:i:s',time());
            $input         = $request->all();

            /** 接收数据*/
            $dispatch_id       = $request->input('dispatch_id');
            $car_id       = $request->input('car_id');
            $contacts       = $request->input('contacts');
            $tel            = $request->input('tel');

            /*** 虚拟数据
            $input['dispatch_id']           =$dispatch_id='dispatch_202101282034423534882996';
            $input['car_id']                =$car_id='car_202101281717140184141326';
            $input['contacts']              =$contacts='李白';
            $input['tel']                   =$tel='13256454879';
             **/
            $rules = [
                'dispatch_id'=>'required',
                'car_id'=>'required',

            ];
            $message = [
                'dispatch_id.required'=>'请选择调度订单',
                'car_id.required'=>'请选择车辆',
            ];

            $validator = Validator::make($input,$rules,$message);
            if($validator->passes()) {
                $where=[
                    ['delete_flag','=','Y'],
                    ['self_id','=',$dispatch_id],
                ];
                $car_where=[
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                    ['self_id','=',$car_id],
                ];
                $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                    'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address',
                    'send_sheng_name','send_shi_name','send_qu_name','send_address',
                    'good_info','good_number','good_weight','good_volume','total_money'];
                $wait_info=TmsLittleOrder::where($where)->select($select)->first();
                $car = TmsCar::where($car_where)->first();
                //调度订单修改订单状态
                $order_where = [
                    ['self_id','=',$wait_info->order_id]
                ];
                $order_update['order_status'] = 4;
                $order_update['update_time']  = $now_time;
                TmsLittleOrder::where($where)->update($order_update);

                $carriage_id = generate_id('carriage_');

                $list['self_id']            =generate_id('patch_');
                $list['order_id']        = $dispatch_id;
                $list['carriage_id']        = $carriage_id;
                $list['total_user_id']         = $user_info->total_user_id;
                $list['create_user_id']     = $user_info->total_user_id;
                $list['create_time']        =$list['update_time']=$now_time;

                $order_list['self_id']            =generate_id('driver_');
                $order_list['carriage_id']        = $carriage_id;
                $order_list['total_user_id']         = $user_info->total_user_id;
                $order_list['create_user_id']     = $user_info->total_user_id;
                $order_list['create_time']        =$order_list['update_time']=$now_time;
                $order_list['car_id']   = $car_id;

                $order_list['car_number']   =  $car->car_number;
                $order_list['contacts']   =  $contacts;
                $order_list['tel']   = $tel;
                $order_list['price'] = $wait_info->total_money;

                $data['self_id']            = $carriage_id;
                $data['create_user_id']     = $user_info->admin_id;
                $data['create_user_name']   = $user_info->name;
                $data['create_time']        = $data['update_time']=$now_time;
                $data['total_user_id']        = $user_info->total_user_id;
                $data['total_money']        = $wait_info->on_line_money;
                $data['carriage_flag']   =  'compose';
                $data['order_status']   =  2;
                DB::beginTransaction();
                try {
                    $id = TmsFastCarriage::insert($data);
                    TmsFastDispatch::insert($list);
                    TmsFastCarriageDriver::insert($order_list);
                    DB::commit();
                }catch(\Exception $e){
                    DB::rollBack();
                    $msg['code'] = 302;
                    $msg['msg'] = "操作失败";
                    return $msg;
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
         * 极速版取消调度
         * */
    public function fastCarriageCancel(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $dispatch_id       = $request->input('dispatch_id');
        /*** 虚拟数据
        $input['dispatch_id']           =$dispatch_id='dispatch_202101282034423534882996';
         **/
        $rules = [
            'dispatch_id'=>'required',
        ];
        $message = [
            'dispatch_id.required'=>'请选择调度订单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];

            $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address',
                'send_sheng_name','send_shi_name','send_qu_name','send_address',
                'good_info','good_number','good_weight','good_volume','total_money'];

            $wait_info = TmsLittleOrder::where($where)->select($select)->first();

            //修改当前运输单状态
            $dispatch_order['order_status']  = 3;
            $dispatch_order['dispatch_flag'] = 'Y';
            $dispatch_order['update_time']   = $now_time;
            $id = TmsLittleOrder::where($where)->update($dispatch_order);

            //删除所有关联运输数据
            $list['delete_flag']         = 'N';
            $list['update_time']         = $now_time;
            TmsFastCarriage::where('order_id',$wait_info->self_id)->update($list);

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
         * 订单送达
         * */
    public function fastOrderDone(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $dispatch_id       = $request->input('self_id');
        /*** 虚拟数据
        $input['self_id']           =$dispatch_id='patch_202105091028035791605429';
         **/
        $rules = [
            'self_id'=>'required',
        ];
        $message = [
            'self_id.required'=>'请选择调度订单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];

            $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','order_status',
                'send_sheng_name','send_shi_name','send_qu_name','send_address',
                'good_info','good_number','good_weight','good_volume','total_money'];

            $wait_info = TmsLittleOrder::where($where)->select($select)->first();

            //修改当前运输单状态
            $dispatch_order['order_status']  = 5;
            $dispatch_order['update_time']   = $now_time;
            $id = TmsLittleOrder::where($where)->update($dispatch_order);

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
     * 上传回单  /api/take/dispatchUploadReceipt
     * */
    public function dispatchUploadReceipt(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $order_id       = $request->input('order_id');
        $receipt       = $request->input('receipt');

        /*** 虚拟数据
        $input['order_id']           =$order_id='dispatch_202101282034423534882996';
         **/
        $rules = [
            'order_id'=>'required',
            'receipt'=>'required',
        ];
        $message = [
            'order_id.required'=>'请选择调度订单',
            'receipt.required'=>'请选择要上传回单',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$order_id],
            ];
            $select=['self_id','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','order_status',
                'gather_address', 'send_sheng_name','send_shi_name','send_qu_name','send_address','good_info','good_number','good_weight','good_volume','total_money'];
            $wait_info=TmsLittleOrder::where($where)->select($select)->first();
            if(!in_array($wait_info->order_status,[5,6])){
                $msg['code']=301;
                $msg['msg']='请确认订单已送达';
                return $msg;
            }
            $dispatch_update['receipt_flag'] = 'Y';
            $dispatch_update['update_time'] = $now_time;
            TmsLittleOrder::where($where)->update($dispatch_update);
            $data['self_id'] = generate_id('receipt_');
            $data['receipt'] = img_for($receipt,'in');
            $data['order_id'] = $order_id;
            $data['create_time'] = $data['update_time'] = $now_time;
            $data['total_user_id']  = $user_info->total_user_id;

            $id=TmsFastReceipt::insert($data);

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

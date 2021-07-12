<?php
namespace App\Http\Api\Tms;

use App\Http\Controllers\DetailsController as Details;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsCar;
use App\Models\Tms\TmsCarriage;
use App\Models\Tms\TmsCarriageDispatch;
use App\Models\Tms\TmsCarriageDriver;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderCost;
use App\Models\Tms\TmsOrderDispatch;
use App\Http\Controllers\Controller;
use App\Models\Tms\TmsOrderMoney;
use App\Models\Tms\TmsReceipt;
use App\Models\User\UserCapital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PhpParser\Node\Stmt\DeclareDeclare;

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
            'line_gather_sheng','line_gather_shi','line_gather_qu','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name' , 'line_gather_address',
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
            if($v->order_type == 'vehicle' || $v->order_type == 'lcl'){
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
            'line_gather_address_longitude','line_gather_address_latitude','line_send_address_id','line_send_contacts_id','line_send_name','line_send_tel', 'line_send_sheng','line_send_shi','remark','receipt_flag',
            'line_send_qu','line_send_sheng_name','line_send_shi_name','line_send_qu_name','line_send_address','line_send_address_longitude','line_send_address_latitude','clod','pick_flag','send_flag','pay_type'
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
            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl'){
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
//            $order_details2['name'] = '是否付款';
//            $order_details2['value'] = $info->pay_state;
//            $order_details2['color'] = '#FF7A1A';

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
//            $order_details[]= $order_details2;

            if ($info->order_type == 'vehicle' || $info->order_type == 'lcl'){
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
            'line_gather_sheng','line_gather_shi','line_gather_qu','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name' , 'line_gather_address','clod','create_time','receipt_flag',
            'line_gather_address_longitude','line_gather_address_latitude','line_send_address_id','line_send_contacts_id','line_send_name','line_send_tel', 'line_send_sheng','line_send_shi','send_time','gather_time',
            'line_send_qu','line_send_sheng_name','line_send_shi_name','line_send_qu_name','line_send_address','line_send_address_longitude','line_send_address_latitude','pick_flag','send_flag','car_type',
        ];
        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'use_flag','value'=>'Y'],
            ['type'=>'=','name'=>'receiver_id','value'=>$user_info->total_user_id],
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
            if ($v->order_type == 'vehicle'){
                if ($v->tmsCarType){
                    $v->car_type_show = $v->tmsCarType->parame_name;
                }
            }
            $v->temperture = $temperture;
            $v->order_type_show=$tms_order_type[$v->order_type]??null;
            $v->pay_status_color=$pay_status[$v->order_status-1]['pay_status_color']??null;
            $v->pay_status_text=$pay_status[$v->order_status-1]['pay_status_text']??null;

            if($v->order_type == 'vehicle'){
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
                        if ($value->id == 123){
                            $button2[] = $value;
                        }
                        if ($value->id == 123){
                            $button3[] = $value;
                        }
                        if ($value->id == 142){
                            $button4[] = $value;
                        }
                        if ($v->order_status == 2 || $v->order_status == 3){
                            $v->button  = $button1;
                        }
                        if ($v->order_status == 4 ){
                            $v->button  = $button2;
                        }
                        if ($v->order_status == 5){
                            $v->button  = $button3;
                        }
//                        if ($v->receipt_flag == 'Y'){
//                            $v->button  = $button4;
//                        }
                        if ($v->receipt_flag == 'Y'){
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
                'good_info','good_number','good_weight','good_volume','total_money','on_line_money'];
            $wait_info=TmsOrderDispatch::where($where)->select($select)->first();
            $order_where = [
                ['self_id','=',$wait_info->order_id]
            ];
            $order_update['order_status'] = 3;
            $order = TmsOrder::where($order_where)->update($order_update);

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
//            $money['self_id']           = generate_id('order_money_');
//            $money['shouk_group_code']           = $user_info->group_code;
//            $money['shouk_type']                 = 'COMPANY';
//            $money['fk_total_user_id']           = $user_info->total_user_id;
//            $money['fk_type']                    = 'USER';
//            $money['ZIJ_total_user_id']          = $user_info->total_user_id;
//
//
//            $money['order_id']                   = $dispatch_id;
//            $money['create_time']                = $now_time;
//            $money['update_time']                = $now_time;
//            $money['money']                      = $total_money;
//            $money['money_type']                 = 'freight';
//            $money['type']                       = 'in';


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
//            TmsOrderMoney::insert($money);
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
}

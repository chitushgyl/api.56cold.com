<?php
namespace App\Http\Admin\Tms;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsOrderMoney;
use App\Models\Tms\TmsSettle;
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

        /** 抓取可调度的订单**/
        $where=[
            ['delete_flag','=','Y'],
            ['dispatch_flag','=','Y'],
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
        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $group_code     =$request->input('group_code');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'all','name'=>'use_flag','value'=>$use_flag],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
        ];


        $where=get_list_where($search);

        $select=['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
        ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsOrderDispatch::where($where)->count(); //总的数据量
                $data['items']=TmsOrderDispatch::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=TmsOrderDispatch::where($where)->count(); //总的数据量
                $data['items']=TmsOrderDispatch::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsOrderDispatch::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsOrderDispatch::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }
        $button_info1=[];
        $button_info2=[];
        $button_info3 = [];
        foreach ($button_info as $k => $v){
//            dump($v);
            if($v->id==643){
                $button_info1[]=$v;
            }
            if($v->id==644){
                $button_info2[]=$v;
            }
            if($v->id==645){
                $button_info1[]=$v;
                $button_info2[]=$v;
                $button_info3[] = $v;
            }
        }
//        dd($button_info1,$button_info2,$button_info3);
        foreach ($data['items'] as $k=>$v) {
            $v->button_info=$button_info;
            $v->order_type_show=$tms_order_type[$v->order_type]??null;
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

            if($v->dispatch_flag =='N' && $v->on_line_flag =='Y'){
                $v->button_info=$button_info1;
            }elseif($v->dispatch_flag =='Y' && $v->on_line_flag =='N'){
                $v->button_info=$button_info2;
            }elseif($v->dispatch_flag =='N' && $v->on_line_flag =='N'){
                $v->button_info=$button_info3;
            }else{
                $v->button_info=$button_info2;
            }

            $v->total_money = $v->total_money/100;
            $v->on_line_money = $v->on_line_money/100;

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
        $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
        /** 接收数据*/
        $input              =$request->all();
        $group_code=$request->input('group_code');
//        $input['group_code'] = $group_code =  'group_202101071545547962521124';
        $rules=[
            'group_code'=>'required',
        ];
        $message=[
            'group_code.required'=>'请至少选择一条调度单',
        ];
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['dispatch_flag','=','Y'],
                ['receiver_id','=',$group_code],
            ];
            $select=['self_id','receiver_id','company_name','create_time','create_time','group_name',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address',
                'send_sheng_name','send_shi_name','send_qu_name','send_address',
                'good_info','good_number','good_weight','good_volume'];

            $data['info']=TmsOrderDispatch::where($where)->select($select)->get(); //总的数据量
            foreach ($data['info'] as $key => $value){
                    $value['good_info'] = json_decode($value['good_info'],true);
                    foreach ($value['good_info'] as $k =>$v){
                        if ($v['clod']){
                            $v['clod'] =$tms_control_type[$v['clod']];
                        }
                        $value['good_info_show'] .= $v['good_name'].',';
                    }
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
    /***    调度订单提交      /tms/dispatch/dispatchOrder
     */
    public function dispatchOrder(Request $request){
        $group_info             = $request->get('group_info');
        $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
        /** 接收数据*/
        $input              =$request->all();

        $group_code=$request->input('group_code');
//        $input['group_code'] = $group_code =  'dispatch_202101131433174868682366';
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
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address',
                'send_sheng_name','send_shi_name','send_qu_name','send_address',
                'good_info','good_number','good_weight','good_volume'];

            $data['info']=TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$group_code))->select($select)->get(); //总的数据量
            foreach ($data['info'] as $key => $value){
                $value['good_info'] = json_decode($value['good_info'],true);
                foreach ($value['good_info'] as $k =>$v){
                    if ($v['clod']){
                        $v['clod'] =$tms_control_type[$v['clod']];
                    }
                    $value['good_info_show'] .= $v['good_name'].',';
                }
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
        $input['dispatch_list']     =$dispatch_list=['dispatch_202101071320455798189989','dispatch_202101071320455798863169'];
        $input['company_id']        =$company_id='company_202012291153523141320375'; //
        $input['carriage_flag']        =$carriage_flag='driver';  // 自己oneself，个体司机driver，承运商carriers 组合compose'
        $input['car_info']         =$car_info = [['car_id'=>'','type'=>'lease','car_number'=>'沪V12784','contacts'=>'刘伯温','tel'=>'19819819819','price'=>500],
            ['car_id'=>'','type'=>'oneself','car_number'=>'沪V44561','contacts'=>'赵匡胤','tel'=>'16868686868','price'=>100]];
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
            $wait_info=TmsOrderDispatch::where($where)->whereIn('self_id',explode(',',$dispatch_list))->select($select)->get(); //总的数据量
//            dd($wait_info);
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
                    $list['group_code']         = $user_info->group_code;
                    $list['group_name']         = $user_info->group_name;
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
                    $order_list['group_code']         = $user_info->group_code;
                    $order_list['group_name']         = $user_info->group_name;
                    $order_list['create_user_id']     = $user_info->admin_id;
                    $order_list['create_user_name']   = $user_info->name;
                    $order_list['create_time']        =$order_list['update_time']=$now_time;
                    $order_list['car_id']   = $value['car_id'];
                    $car_list_info = $tms->get_car($value['car_id'],$value['car_number'],$value['type'],$group_info,$user_info,$now_time);
                    $order_list['car_id']   = $car_list_info->self_id;
                    if($company_id){
                        $order_list['company_id']   = $company_info->self_id;
                        $order_list['company_name']   = $company_info->company_name;
                    }
                    $order_list['car_number']   =  $car_list_info->car_number;
                    $order_list['contacts']   =  $value['contacts'];
                    $order_list['tel']   = $value['tel'];
                    $order_list['price'] = $value['price']*100;
                    $order_info[]=$order_list;
                    $car_list[] = $car_list_info->car_possess;

                    $money['self_id']           = generate_id('order_money_');
                    $money['driver_id']          = $order_list['self_id'];
                    $money['create_user_id']    = $user_info->admin_id;
                    $money['create_user_name']  = $user_info->name;
                    $money['create_time']       = $money['update_time'] = $now_time;
                    $money['group_code']        = $group_info->group_code;
                    $money['group_name']        = $group_info->group_name;
                    $money['money']             = $value['price']*100 ;
                    $money['type']              = 'out';
                    $money['money_type']        = 'freight';
                    $money['table_type'] = 'driver';
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
            $data['group_code']         = $user_info->group_code;
            $data['group_name']         = $user_info->group_name;
            $data['total_money']        = $total_price*100;
            if($company_id){
                $data['company_id']         = $company_info->self_id;
                $data['company_name']       = $company_info->company_name;

                $money['self_id']           = generate_id('order_money_');
                $money['carriage_id']       = $carriage_id;
                $money['create_user_id']    = $user_info->admin_id;
                $money['create_user_name']  = $user_info->name;
                $money['create_time']       = $money['update_time'] = $now_time;
                $money['group_code']        = $group_info->group_code;
                $money['group_name']        = $group_info->group_name;
                $money['company_id']        = $company_info->self_id;
                $money['company_name']      = $company_info->company_name;
                $money['money']             = $total_price*100;
                $money['money_type']        = 'freight';
                $money['type']              = 'out';
                $money['table_type']        = 'carriage';
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
            TmsOrderMoney::insert($order_money);
             if ($carriage_flag == 'oneself' || $carriage_flag == 'driver'){
                 TmsCarriageDriver::insert($order_info);
             }


            $data_update['dispatch_flag']      ='N';
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
        $self_id=$request->input('self_id');
        $table_name='tms_order_dispatch';
        $select=['self_id','order_id','company_id','company_name','create_time','use_flag','delete_flag','group_code','group_name','order_type','order_status','gather_name',
            'gather_tel','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_name','send_tel','send_sheng_name','send_shi_name','send_qu_name',
            'send_address','total_money','good_info','good_number','good_weight','good_volume','dispatch_flag','carriage_group_id','carriage_group_name','on_line_flag','on_line_money',
            'pick_flag','send_flag','clod'];
        $self_id='dispatch_202101151150314326956916';
        $info=$details->details($self_id,$table_name,$select);

        if($info){
            $tms_order_type        =array_column(config('tms.tms_order_type'),'name','key');
            $tms_control_type        =array_column(config('tms.tms_control_type'),'name','key');
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $info->total_money=$info->total_money/100;
            $info->on_line_money=$info->on_line_money/100;
            $info->good_info=json_decode($info->good_info,true);
            $info->clod=json_decode($info->clod,true);
            $info->order_type=$tms_order_type[$info->order_type];
            foreach ($info->clod as $key => $value){
                $info->clod[$key]=$tms_control_type[$value];
            }
            foreach ($info->good_info as $k => $v){
                $info->good_info[$k]['clod']=$tms_control_type[$v['clod']];
            }
//            $info->morepickup_price=$info->morepickup_price/100;
//            dd($info);
            $data['info']=$info;
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


        $input              =$request->all();
        /** 接收数据*/
        $self_id			=$request->input('self_id');
        $price 				= $request->input('price');//上线价格
        $send_flag 			= $request->input('send_flag');
        $gather_flag 		= $request->input('gather_flag');
        $group_code      	=$request->input('group_code');
        $address 			= $request->input('address');//收发货地址

        /*** 虚拟数据

        $self_id=  $input['self_id'] = 'dispatch_202101081457313376204567';
        $price=  $input['price'] = 2000;//上线价格
        $send_flag = $input['send_flag'] = 'Y';
        $gather_flag = $input['gather_flag'] = 'N'; // Y 修改地址 N地址不变
        $group_code = $input['group_code'] = '1234';
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

//        dd($group_info);
        $validator=Validator::make($input,$rules,$message);
        $where_check=[
            ['delete_flag','=','Y'],
            ['self_id','=',$group_code],
        ];
        $group_info= SystemGroup::where($where_check)->select('self_id','group_code','group_name')->first();

        $select=['self_id','order_id','company_id','company_name','order_type','order_status','total_money','dispatch_flag','gather_address_id','gather_contacts_id','gather_tel','gather_sheng',
            'gather_shi','gather_qu','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','gather_address_longitude','gather_address_latitude',
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
        $data['on_line_money'] = $price*100;
        $data['on_line_flag'] = 'Y';
        $data['dispatch_flag'] = 'N';
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
            $gather_address = $tms->address('',$address['gather_qu'],$address['gather_address'],$group_info,$user_info,$now_time);
            $gather_conact = $tms->contacts('',$address['gather_name'],$address['gather_tel'],$group_info,$user_info,$now_time);
            $data['line_gather_address_id'] = $gather_address->self_id;
            $data['line_gather_contacts_id'] = $gather_conact->self_id;
            $data['line_gather_name'] = $gather_conact->contacts;
            $data['line_gather_tel'] = $gather_conact->tel;
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
            $data['line_send_contacts_id'] = $order_info->send_contacts_id;
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
            $send_address = $tms->address('',$address['send_qu'],$address['send_address'],$group_info,$user_info,$now_time);
            $send_contact = $tms->contacts('',$address['send_name'],$address['send_tel'],$group_info,$user_info,$now_time);
            $data['line_send_address_id'] = $send_address->self_id;
            $data['line_send_contacts_id'] = $send_contact->self_id;
            $data['line_send_name'] = $send_contact->contacts;
            $data['line_send_tel'] = $send_contact->tel;
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

    /*
     * 上线订单列表
     * */
    public function onlineOrder(){

    }

    public function unline(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_order_dispatch';
        $self_id=$request->input('self_id');
        $operationing->access_cause='下线';
        $operationing->table = $table_name;
        $operationing->table_id = $self_id;
        $operationing->now_time = $now_time;

//        $self_id='line_202101071834126555383754';
        $where=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
            ['self_id','=',$self_id]
        ];
        $select=['self_id','order_id','company_id','company_name','order_type','order_status','total_money','dispatch_flag', 'send_address_latitude', 'on_line_flag',];
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
//        dump($line->toArray());
            $data['on_line_flag'] = 'N';
            $data['dispatch_flag'] = 'Y';
            $data['update_time'] = $now_time;
            $id=TmsOrderDispatch::where($where)->update($data);

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
}
?>

<?php
namespace App\Http\Admin\Tms;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsOrder;
use App\Models\Tms\TmsOrderCost;
use App\Models\Tms\TmsOrderMoney;
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



class OnlineController extends CommonController{

    /***    上线订单头部      /tms/online/onlineList
     */
    public function  onlineList(Request $request){
        $group_info             = $request->get('group_info');
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        /** 抓取已上线的订单**/
        $where=[
            ['delete_flag','=','Y'],
            ['on_line_flag','=','Y']
        ];

        $data['total']=TmsOrderDispatch::where($where)->count(); //总的数据量
        /**
        switch ($group_info['group_id']){
        case 'all':
        $data['total']=TmsOrderDispatch::where($where)->count(); //总的数据量
        break;

        case 'one':
        $where[]=['receiver_id','=',null];
        $data['total']=TmsOrderDispatch::where($where)->count(); //总的数据量
        break;

        case 'more':
        //dd($where);
        $data['total']=TmsOrderDispatch::where($where)->whereIn('receiver_id',null)->count(); //总的数据量
        break;
        }**/

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        //dd($msg);
        return $msg;
    }

    /***    上线订单分页      /tms/online/onlinePage
     */
    public function onlinePage(Request $request){
        $pay_status     =config('tms.tms_order_status_type');
        $dispatch_status     =config('tms.tms_dispatch_type');
        $online_status     =config('tms.tms_online_type');
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数
        $buttonInfo    = $request->get('buttonInfo');//接收中间件产生的参数

        $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
        $carriage_flag    =array_column(config('tms.carriage_flag'),'name','key');
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $group_code     =$request->input('group_code');
        $start_city     = $request->input('start_city');
        $end_city       = $request->input('end_city');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'on_line_flag','value'=>'Y'],
            ['type'=>'=','name'=>'pay_type','value'=>'online'],
            ['type'=>'=','name'=>'order_status','value'=>2],
            ['type'=>'like','name'=>'line_gather_shi_name','value'=>$start_city],
            ['type'=>'like','name'=>'line_send_shi_name','value'=>$end_city],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
        ];
        $search1=[
            ['type'=>'=','name'=>'on_line_flag','value'=>'Y'],
            ['type'=>'=','name'=>'pay_type','value'=>'offline'],
            ['type'=>'=','name'=>'order_status','value'=>2],
            ['type'=>'like','name'=>'line_gather_shi_name','value'=>$start_city],
            ['type'=>'like','name'=>'line_send_shi_name','value'=>$end_city],
        ];

        $where=get_list_where($search);
        $where1=get_list_where($search1);
//        $where=[
//            ['on_line_flag','=','Y'],
//            ['pay_type','=','online'],
//            ['order_status','=',2]
//        ];
//        $where1 = [
//            ['on_line_flag','=','Y'],
//            ['pay_type','=','offline'],
//            ['order_status','=',2]
//        ];
        $select=['self_id','order_type','order_status','receiver_id','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
            ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','line_gather_address_id','line_gather_contacts_id','line_gather_name','line_gather_tel',
            'line_gather_sheng','line_gather_shi','line_gather_qu','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name' , 'line_gather_address','info','car_type','clod',
            'line_gather_address_longitude','line_gather_address_latitude','line_send_address_id','line_send_contacts_id','line_send_name','line_send_tel', 'line_send_sheng','line_send_shi',
            'line_send_qu','line_send_sheng_name','line_send_shi_name','line_send_qu_name','line_send_address','line_send_address_longitude','line_send_address_latitude','total_user_id','send_time','gather_time',
        ];
        $select2 = ['self_id','parame_name'];
        $data['total']=TmsOrderDispatch::where($where)->orWhere($where1)->whereNull('receiver_id')->count(); //总的数据量
        $data['items']=TmsOrderDispatch::with(['tmsCarType' => function($query) use($select2){
            $query->select($select2);
        }])
            ->where($where)->orWhere($where1)->whereNull('receiver_id')
            ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
            ->select($select)->get();
        $data['group_show']='Y';

        foreach ($button_info as $k => $v){
            if($v->id==652){
                $button_info1[]=$v;
            }
            if($v->id==653){
                $button_info1[]=$v;
                $button_info2[]=$v;
            }
        }

        foreach ($data['items'] as $k=>$v) {
            $v->button_info = $button_info;
            $v->order_type_show = $tms_order_type[$v->order_type] ?? null;
            $v->self_id_show = substr($v->self_id,15);
            $v->total_money = number_format($v->total_money / 100, 2);
            $v->on_line_money = number_format($v->on_line_money / 100, 2);
            $v->pay_status_text = $pay_status[$v->order_status - 1]['pay_status_text'] ?? null;
            if ($v->receiver_id) {
                $v->button_info = $button_info2;
            }
            if ($v->order_type == 'vehicle'){
                if ($v->tmsCarType){
                    $v->car_type_show = $v->tmsCarType->parame_name;
                }
            }
            $v->send_time        = date('m-d H:i',strtotime($v->send_time));
            $v->gather_time      = date('m-d H:i',strtotime($v->gather_time));
            $temperture = json_decode($v['clod'],true);
            foreach ($temperture as $kkk => $vvv){
                $temperture[$kkk] = $tms_control_type[$vvv];
            }
            $v->temperture = implode(',',$temperture);
            $button1 = [];
            $button2 = [];

            foreach ($buttonInfo as $kk => $vv) {
                if ($vv->id == 185) {
                    $button1[] = $vv;
                }
                if ($v->dispatch_flag =='N' && $v->on_line_flag =='Y' && $v->order_status == 2) {
                    $v->button = $button1;
                }
            }
        }
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /***    拉取订单数据    /tms/online/createDispatch
     */
    public function createDispatch(Request $request){
        $group_info             = $request->get('group_info');
        $tms_control_type           =array_column(config('tms.tms_control_type'),'name','key');
        $tms_order_inco_type         =array_column(config('tms.tms_order_inco_type'),'icon','key');
        /** 接收数据*/
        $input              =$request->all();
        $dispatch_id = $request->input('dispatch_id');
//        $input['dispatch_id'] = $dispatch_id =  'dispatch_202101151706244512773397';
        $rules=[
            'dispatch_id'=>'required',
        ];
        $message=[
            'dispatch_id.required'=>'请至少选择一条调度单',
        ];
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where=[
                ['delete_flag','=','Y'],
                ['self_id','=',$dispatch_id],
            ];
            $select=['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
                ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','line_gather_address_id','line_gather_contacts_id','line_gather_name','line_gather_tel',
                'line_gather_sheng','line_gather_shi','line_gather_qu','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name' , 'line_gather_address','info','send_time','gather_time','remark',
                'line_gather_address_longitude','line_gather_address_latitude','line_send_address_id','line_send_contacts_id','line_send_name','line_send_tel', 'line_send_sheng','line_send_shi',
                'line_send_qu','line_send_sheng_name','line_send_shi_name','line_send_qu_name','line_send_address','line_send_address_longitude','line_send_address_latitude','pick_flag','send_flag','clod'
            ];

            $info=TmsOrderDispatch::where($where)->select($select)->first(); //
//            dd($info->toArray());
            if ($info){
                $good_info=json_decode($info->good_info,true);
                $info->info = json_decode($info->info,true);
                $info->on_line_money       = number_format($info->on_line_money/100, 2);
                $info->total_money       = number_format($info->total_money/100, 2);
                $info->order_type_show=$tms_order_type[$info->order_type]??null;
                $info->type_inco = img_for($tms_order_inco_type[$info->order_type],'no_json')??null;
                $info->send_time         = date('m-d H:i',strtotime($info->send_time));
                foreach ($good_info as $k => $v){
                    $good_info[$k]['clod_show']=$tms_control_type[$v['clod']];
                }
                $info->good_info_show=$good_info;
            }
            $msg['code']=200;
            $msg['msg']="数据拉取成功";
            $msg['data']=$info;
//            dd($data['info']->toArray());
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

    /***    接单      /tms/online/addOrder
     */
    public function addOrder(Request $request,Tms $tms){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_order_dispatch';
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
            $select=['self_id','company_name','order_status','create_time','create_time','group_name','dispatch_flag','receiver_id','on_line_flag',
                'gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','order_id','group_code',
                'send_sheng_name','send_shi_name','send_qu_name','send_address',
                'good_info','good_number','good_weight','good_volume'];
            $wait_info=TmsOrderDispatch::where($where)->select($select)->first(); //总的数据量
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
            $order_where = [
                ['self_id','=',$wait_info->order_id]
            ];
            $order_update['order_status'] = 3;
            $order = TmsOrder::where($order_where)->update($order_update);

            $update['receiver_id']      =$user_info->group_code;
            $update['dispatch_flag']      ='Y';
            $update['on_line_flag']       ='N';
            $update['order_status']       = 3;
            $update['update_time']        =$now_time;
            $id = TmsOrderDispatch::where($where)->update($update);

            /*** 保存应收及应付对象**/
            if ($wait_info->pay_type == 'online'){
                $money['shouk_group_code']              = $user_info->group_code;
                $money['shouk_type']                    = 'GROUP_CODE';
                $money['fk_group_code']                 = '1234';
                $money['fk_type']                       = 'PLATFORM';
                $money['ZIJ_group_code']                = '1234';
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
                $money['shouk_group_code']              = $user_info->group_code;
                $money['shouk_type']                    = 'GROUP_CODE';
                TmsOrderCost::where($money_where)->update($money);
            }

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


    /***    上线订单详情     /tms/online/details
     */
    public function  details(Request $request,Details $details){
        $pay_status     =config('tms.tms_order_status_type');
        $dispatch_status     =config('tms.tms_dispatch_type');
        $online_status     =config('tms.tms_online_type');
        $self_id=$request->input('self_id');
        $table_name='tms_order_dispatch';
        $select=['self_id','order_type','order_status','company_name','dispatch_flag','group_code','group_name','use_flag','on_line_flag','gather_sheng_name','gather_shi_name','gather_qu_name','gather_address','send_sheng_name','send_shi_name'
            ,'send_qu_name','send_address','total_money','good_info','good_number','good_weight','good_volume','carriage_group_name','on_line_money','line_gather_address_id','line_gather_contacts_id','line_gather_name','line_gather_tel',
            'line_gather_sheng','line_gather_shi','line_gather_qu','line_gather_sheng_name','line_gather_shi_name','line_gather_qu_name' , 'line_gather_address','info','send_time','gather_time','remark',
            'line_gather_address_longitude','line_gather_address_latitude','line_send_address_id','line_send_contacts_id','line_send_name','line_send_tel', 'line_send_sheng','line_send_shi',
            'line_send_qu','line_send_sheng_name','line_send_shi_name','line_send_qu_name','line_send_address','line_send_address_longitude','line_send_address_latitude','clod','pick_flag','send_flag',
        ];
//        $self_id='patch_20210515155014145726906';
        $info=$details->details($self_id,$table_name,$select);

        if($info){
            $tms_order_type          =array_column(config('tms.tms_order_type'),'name','key');
            $tms_control_type        =array_column(config('tms.tms_control_type'),'name','key');
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $info->total_money=number_format($info->total_money/100,2);
            $info->on_line_money=number_format($info->on_line_money/100,2);
            $info->info=json_decode($info->info,true);
            $info->clod=json_decode($info->clod,true);
            $info->order_type=$tms_order_type[$info->order_type];
            $info->pay_status_text=$pay_status[$info->order_status-1]['pay_status_text']??null;
            foreach ($info->clod as $key => $value){
                $info->clod[$key]=$tms_control_type[$value];
            }
            $good_info=json_decode($info->good_info,true);
            foreach ($good_info as $k => $v){
                $good_info[$k]['clod_show']=$tms_control_type[$v['clod']];
            }
            $info->good_info_show=$good_info;
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

    /**
     *  取消接单 /tms/online/orderCancel
     * */
    public function orderCancel(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_order_dispatch';
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
        $select=['self_id','order_id','group_code','company_id','company_name','order_type','order_status','total_money','dispatch_flag', 'send_address_latitude', 'on_line_flag',];
        $order_info = TmsOrderDispatch::where($where)->select($select)->first();
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
        $id=TmsOrderDispatch::where($where)->update($data);

        $order_update['order_status'] = 2;
        $order_update['update_time'] = $now_time;
        TmsOrder::where('self_id',$order_info->order_id)->update($order_update);

        /** 修改订单费用中的应收对象 **/
        $money_where = [
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
            ['dispatch_id','=',$self_id],
            ['shouk_type','=','GROUP_CODE'],
            ['order_id','=',$order_info->order_id]

        ];
        $money_update['shouk_group_code'] = null;
        $money_update['shouk_type']       = null;
        $money_update['update_time']      = $now_time;
        TmsOrderCost::where($money_where)->update($money_update);

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

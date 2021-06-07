<?php
namespace App\Http\Api\Line;
use App\Http\Controllers\DetailsController as Details;
use App\Http\Controllers\StatusController as Status;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsLineList;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TmsController as Tms;
use App\Models\Tms\TmsLine;
use Illuminate\Support\Facades\Validator;

class LineController extends Controller{
    /**
     * 首页模板接口      /line/page
     */
    public function page(Request $request){
        $now_time           =date('Y-m-d H:i:s',time());
        $user_info          =$request->get('user_info');
        $listrows			=config('page.listrows')[0];//每次加载的数量
        $first				=$request->input('page')??1;
        $firstrow			=($first-1)*$listrows;
        $user_info->type		='user';
        $user_info->group_code	='1234';

        $select=['self_id','shift_number','type','price','use_flag','group_name',
            'pick_price','send_price','pick_type','send_type','trunking','control',
            'send_sheng_name','send_shi_name','send_qu_name','gather_sheng_name','gather_shi_name','gather_qu_name',
            'start_time','end_time','time0','time1','time2','time3','time4','time5','time6','depart_time'];
        $selectList=['line_id','yuan_self_id'];
		switch ($user_info->type){
			case 'TMS3PL':
                $where=[
                    ['delete_flag','=','Y'],
                    ['group_code','=',$user_info->group_code],
                ];
				break;
            case 'user':
                $where=[
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
//                    ['group_code','=',$user_info->group_code],
                    ['start_time','<',$now_time],
                    ['end_time','>',$now_time],
                ];
                break;
		}

        $data['info']=TmsLine::with(['tmsLineList' => function($query)use($selectList,$select) {
            $query->where('delete_flag','=','Y');
            $query->select($selectList);
            $query->orderBy('sort', 'asc');
            $query->with(['tmsLine' => function($query)use($select){
                $query->select($select);
            }]);
        }])->where($where)->offset($firstrow)->limit($listrows)->select($select)->get();

        foreach ($data['info'] as $k=>$v) {
            $v->price=number_format($v->price/100,2);
            $v->pick_price=number_format($v->pick_price/100,2);
            $v->send_price=number_format($v->send_price/100,2);
        }
//        dd($data['info']->toArray());
        $msg['code']=200;
        $msg['msg']='数据拉取成功！';
        $msg['data']=$data;
		//dd($msg);
        return $msg;
    }


    /**
     * 线路添加     /line/add
     */
    public function add(Request $request,Tms $tms){
//      区分用户身份普通用户没有添加线路，只能是3pl公司
        $user_info          =$request->get('user_info');
//        dd($user_info);
        $user_info->type		='TMS3PL';
        $user_info->group_code	='1234';

        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_line';
//        dd($operationing);

//        $operationing->access_cause     ='创建/修改线路';
//        $operationing->table            =$table_name;
//        $operationing->operation_type   ='create';
//        $operationing->now_time         =$now_time;
//        $operationing->type             ='add';

        $input              =$request->all();
        //dump($input);
        /** 接收数据*/
        $self_id                =$request->input('self_id');
        $type                   =$request->input('type');
        $shift_number           =$request->input('shift_number');
        $price                  =$request->input('price');
        $min_money              =$request->input('min_money');
        $group_code             =$request->input('group_code');
        $pick_price             =$request->input('pick_price');
        $send_price             =$request->input('send_price');
        $pick_type              =$request->input('pick_type');
        $send_type              =$request->input('send_type');
        $depart_time            =$request->input('depart_time');
        $all_weight             =$request->input('all_weight');
        $all_volume             =$request->input('all_volume');
        $trunking               =$request->input('trunking');
        $start_time             =$request->input('start_time');
        $end_time               =$request->input('end_time');
        $control                =$request->input('control');
        $time0                  =$request->input('time0');
        $time1                  =$request->input('time1');
        $time2                  =$request->input('time2');
        $time3                  =$request->input('time3');
        $time4                  =$request->input('time4');
        $time5                  =$request->input('time5');
        $time6                  =$request->input('time6');
        $combination            =$request->input('combination');

        /** 地址接收数据**/
        $gather_address_id      =$request->input('gather_address_id');
        $gather_contacts_id     =$request->input('gather_contacts_id');
        $send_address_id        =$request->input('send_address_id');
        $send_contacts_id       =$request->input('send_contacts_id');
        $gather_qu              =$request->input('gather_qu');
        $gather_address         =$request->input('gather_address');
        $gather_longitude       =$request->input('gather_longitude');
        $gather_dimensionality  =$request->input('gather_dimensionality');
        $gather_contacts_name   =$request->input('gather_contacts_name');
        $gather_contacts_tel    =$request->input('gather_contacts_tel');
        $send_qu                =$request->input('send_qu');
        $send_address           =$request->input('send_address');
        $send_longitude         =$request->input('send_longitude');
        $send_dimensionality    =$request->input('send_dimensionality');
        $send_contacts_name     =$request->input('send_contacts_name');
        $send_contacts_tel      =$request->input('send_contacts_tel');

        /*** 虚拟数据
        $input['self_id']           =$self_id=null;
        $input['type']             =$type='combination';           //alone   ,combination
        $input['shift_number']     =$shift_number='154578';
        $input['price']                =$price='1200';
        $input['min_money']              =$min_money='1100';
        $input['group_code']           =$group_code='1234';
        $input['pick_price']        =$pick_price='20';
        $input['send_price']         =$send_price='50';
        $input['pick_type']         =$pick_type='pick';
        $input['send_type']    =$send_type='send';
        $input['depart_time']        =$depart_time='12:00:00';
        $input['all_weight']        =$all_weight='154';

        $input['all_volume']    =$all_volume='30';
        $input['trunking']        =$trunking='3';
        $input['start_time']        =$start_time='2018-11-30 00:00:00';
        $input['end_time']        =$end_time='2099-12-31 00:00:00';
        $input['control']        =$control='freeze';
        $input['time0']        =$time0='Y';
        $input['time1']        =$time1='Y';
        $input['time2']        =$time2='Y';
        $input['time3']        =$time3='Y';
        $input['time4']        =$time4='Y';
        $input['time5']        =$time5='Y';
        $input['time6']        =$time6='Y';
        // $input['gather_address_id']         =$gather_address_id='address_202012291000353391824836';
        $input['gather_contacts_id']        =$gather_contacts_id='contacts_202012290959556624971375';
        $input['send_address_id']           =$send_address_id='address_202012291117129095489512';
        $input['send_contacts_id']          =$send_contacts_id='contacts_202012291001340113861963';
        $input['combination']               =$combination=['line_202101041056118913256218','line_202101041055337562586879'];
        $input['gather_qu']               =$gather_qu='43';
        $input['gather_address']           =$gather_address='小浪底';
        $input['gather_particular']        =$gather_particular='1002333';
        $input['gather_longitude']         =$gather_longitude='123';
        $input['gather_dimensionality']    =$gather_dimensionality='456';

         **/

        $rules=[
            'type'=>'required',
            'price'=>'required',

        ];
        $message=[
            'type.required'=>'类型必须填写',
            'price.required'=>'价格必须填写',
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
            $shiftnumber = TmsLine::where('shift_number',$shift_number)->count();


//            dump($shiftnumber);
            if ($shiftnumber>0){
                $msg['code'] = 308;
                $msg['msg'] = '班次号不能重复,请重新填写';
                return $msg;
            }
            switch ($type){
                case 'alone':

                    $gather_address=$tms->address($gather_address_id,$gather_qu,$gather_address,$gather_longitude,$gather_dimensionality,$group_info);
                    if(empty($gather_address)){
                        $msg['code'] = 303;
                        $msg['msg'] = '收货地址不存在';
                        return $msg;
                    }

                    $send_address = $tms->address($send_address_id,$send_qu,$send_address,$send_longitude,$send_dimensionality,$group_info);
                    if(empty($send_address)){
                        $msg['code'] = 304;
                        $msg['msg'] = '发货地址不存在';
                        return $msg;
                    }


                    $gather_contacts = $tms->contacts($gather_contacts_id,$gather_contacts_name,$gather_contacts_tel,$group_info);
                    if(empty($gather_contacts)){
                        $msg['code'] = 305;
                        $msg['msg'] = '收货联系人不存在';
                        return $msg;
                    }

                    $send_contacts = $tms->contacts($send_contacts_id,$send_contacts_name,$send_contacts_tel,$group_info);
                    if(empty($send_contacts)){
                        $msg['code'] = 306;
                        $msg['msg'] = '发货联系人不存在';
                        return $msg;
                    }

                    $data['type']                       =$type;
                    $data['shift_number']               =$shift_number;
                    $data['price']                      =$price*100;
                    $data['min_money']                  =$min_money*100;
                    $data['pick_price']                 =$pick_price*100;
                    $data['send_price']                 =$send_price*100;
                    $data['pick_type']                  =$pick_type;
                    $data['send_type']                  =$send_type;
                    $data['depart_time']                =$depart_time;
                    $data['all_weight']                 =$all_weight;
                    $data['all_volume']                 =$all_volume;
                    $data['trunking']                   =$trunking;
                    $data['start_time']                 =$start_time;
                    $data['end_time']                   =$end_time;
                    $data['control']                    =$control;
                    $data['time0']                      =$time0;
                    $data['time1']                      =$time1;
                    $data['time2']                      =$time2;
                    $data['time3']                      =$time3;
                    $data['time4']                      =$time4;
                    $data['time5']                      =$time5;
                    $data['time6']                      =$time6;
                    $data['gather_address_id']          = $gather_address->self_id;
                    $data['gather_contacts_id']         = $gather_contacts->self_id;
                    $data['gather_name']                = $gather_contacts->contacts;
                    $data['gather_tel']                 = $gather_contacts->tel;
                    $data['gather_sheng']               = $gather_address->sheng;
                    $data['gather_sheng_name']          = $gather_address->sheng_name;
                    $data['gather_shi']                 = $gather_address->shi;
                    $data['gather_shi_name']            = $gather_address->shi_name;
                    $data['gather_qu']                  = $gather_address->qu;
                    $data['gather_qu_name']             = $gather_address->qu_name;
                    $data['gather_address']             = $gather_address->address;
                    $data['gather_address_longitude']   = $gather_address->longitude;
                    $data['gather_address_latitude']    = $gather_address->dimensionality;
                    $data['send_address_id']            = $send_address->self_id;
                    $data['send_contacts_id']           = $send_contacts->self_id;
                    $data['send_name']                  = $send_contacts->contacts;
                    $data['send_tel']                   = $send_contacts->tel;
                    $data['send_sheng']                 = $send_address->sheng;
                    $data['send_sheng_name']            = $send_address->sheng_name;
                    $data['send_shi']                   = $send_address->shi;
                    $data['send_shi_name']              = $send_address->shi_name;
                    $data['send_qu']                    = $send_address->qu;
                    $data['send_qu_name']               = $send_address->qu_name;
                    $data['send_address']               = $send_address->address;
                    $data['send_address_longitude']     = $send_address->longitude;
                    $data['send_address_latitude']      = $send_address->dimensionality;

//                    dump($list);

                    $wheres['self_id'] = $self_id;
                    $old_info=TmsLine::where($wheres)->first();

                    if($old_info){

                        $data['update_time']=$now_time;
                        $id=TmsLine::where($wheres)->update($data);


                        $operationing->access_cause='修改线路';
                        $operationing->operation_type='update';


                    }else{
                        $self_id=generate_id('line_');
                        $data['self_id']=$self_id;
                        $data['create_user_id']=$user_info->admin_id;
                        $data['create_user_name']=$user_info->name;
                        $data['create_time']=$data['update_time']=$now_time;
                        $data['group_code']=$group_code;
                        $data['group_name']=$group_info->group_name;
                        //dd($data);
                        $id=TmsLine::insert($data);
//                        $operationing->access_cause='新建线路';
//                        $operationing->operation_type='create';

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


                    break;

                case 'combination':
                    $data['type']                       =$type;
                    $data['shift_number']               =$shift_number;
                    $data['price']                      =$price*100;
                    $data['min_money']                  =$min_money*100;
                    $data['pick_price']                 =$pick_price*100;
                    $data['send_price']                 =$send_price*100;
                    $data['pick_type']                  =$pick_type;
                    $data['send_type']                  =$send_type;
                    $data['depart_time']                =$depart_time;
                    $data['all_weight']                 =$all_weight;
                    $data['all_volume']                 =$all_volume;
                    $data['trunking']                   =$trunking;
                    $data['start_time']                 =$start_time;
                    $data['end_time']                   =$end_time;
                    $data['control']                    =$control;
                    $data['time0']                      =$time0;
                    $data['time1']                      =$time1;
                    $data['time2']                      =$time2;
                    $data['time3']                      =$time3;
                    $data['time4']                      =$time4;
                    $data['time5']                      =$time5;
                    $data['time6']                      =$time6;


                    $list=[];
                    foreach ($combination as $k => $v){
                        $where_line=[
                            ['delete_flag','=','Y'],
                            ['self_id','=',$v],
                        ];
                        $select=['self_id','send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_sheng_name','send_shi','send_shi_name','send_qu','send_qu_name',
                            'send_address','send_address_longitude','send_address_latitude','gather_address_id','gather_contacts_id','gather_name','gather_tel','gather_sheng','gather_sheng_name',
                            'gather_shi','gather_shi_name','gather_qu','gather_qu_name','gather_address','gather_address_longitude','gather_address_latitude'];
                        $line_info=TmsLine::where($where_line)->select($select)->first();
                        if($k == 0){
                            $data['send_address_id']            = $line_info->send_address_id;
                            $data['send_contacts_id']           = $line_info->send_contacts_id;
                            $data['send_name']                  = $line_info->contacts;
                            $data['send_tel']                   = $line_info->send_name;
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

                        if($k == count($combination)-1){
                            $data['gather_address_id']          = $line_info->gather_address_id;
                            $data['gather_contacts_id']         = $line_info->gather_contacts_id;
                            $data['gather_name']                = $line_info->gather_name;
                            $data['gather_tel']                 = $line_info->gather_tel;
                            $data['gather_sheng']               = $line_info->gather_sheng;
                            $data['gather_sheng_name']          = $line_info->gather_sheng_name;
                            $data['gather_shi']                 = $line_info->gather_shi;
                            $data['gather_shi_name']            = $line_info->gather_shi_name;
                            $data['gather_qu']                  = $line_info->gather_qu;
                            $data['gather_qu_name']             = $line_info->gather_qu_name;
                            $data['gather_address']             = $line_info->gather_address;
                            $data['gather_address_longitude']   = $line_info->gather_address_longitude;
                            $data['gather_address_latitude']    = $line_info->gather_address_latitude;
                        }

                        $abc= $line_info->toArray();
                        $abc['sort']=$k+1;
                        $abc['yuan_self_id']=$abc['self_id'];
                        $list[]=$abc;
                    }

                    $wheres['self_id'] = $self_id;
                    $old_info=TmsLine::where($wheres)->first();

                    if($old_info){

                        $where_line_delete=[
                            ['delete_flag','=','Y'],
                            ['line_id','=',$self_id],
                        ];

                        $line_delete['update_time']=$now_time;
                        $line_delete['delete_flag']='N';

                        TmsLineList::where($where_line_delete)->update($line_delete);

                        $data['update_time']=$now_time;
                        $id=TmsLine::where($wheres)->update($data);

                        foreach ($list as $k => $v){
                            $line_list[$k]['self_id']=generate_id('line_list_');
                            $line_list[$k]['line_id']=$self_id;
                            $line_list[$k]['create_user_id']=$user_info->admin_id;
                            $line_list[$k]['create_user_name']=$user_info->name;
                            $line_list[$k]['create_time']=$line_list[$k]['update_time']=$now_time;
                            $line_list[$k]['group_code']=$group_code;
                            $line_list[$k]['group_name']=$group_info->group_name;
                            $line_list[$k]['sort']=$v['sort'];
                            $line_list[$k]['yuan_self_id']=$v['yuan_self_id'];
                        }

                        TmsLineList::insert($list);

//                        $operationing->access_cause='修改组合线路';
//                        $operationing->operation_type='update';


                    }else{
                        $self_id=generate_id('line_');
                        $data['self_id']=$self_id;
                        $data['create_user_id']=$user_info->admin_id;
                        $data['create_user_name']=$user_info->name;
                        $data['create_time']=$data['update_time']=$now_time;
                        $data['group_code']=$group_code;
                        $data['group_name']=$group_info->group_name;

                        foreach ($list as $k => $v){
                            $line_list[$k]['self_id']=generate_id('line_list_');
                            $line_list[$k]['line_id']=$self_id;
                            $line_list[$k]['create_user_id']=$user_info->admin_id;
                            $line_list[$k]['create_user_name']=$user_info->name;
                            $line_list[$k]['create_time']=$line_list[$k]['update_time']=$now_time;
                            $line_list[$k]['group_code']=$group_code;
                            $line_list[$k]['group_name']=$group_info->group_name;
                            $line_list[$k]['sort']=$v['sort'];
                            $line_list[$k]['yuan_self_id']=$v['yuan_self_id'];
                        }

                        $id=TmsLine::insert($data);
                        TmsLineList::insert($list);
//                        $operationing->access_cause='新建组合线路';
//                        $operationing->operation_type='create';

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


                    break;
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
     * 线路详情     /line/details
     */
    public function details(Request $request,Details $details){
        $self_id=$request->input('self_id');
        $table_name='tms_line';
        $select=['self_id','shift_number','type','price','min_money','pick_price','send_price','pick_type','send_type','depart_time','all_weight','all_volume','weight_price'
            ,'trunking','start_time','end_time','control','send_name','send_tel','send_sheng_name','send_shi_name','send_qu_name','send_address','gather_shi_name',
            'gather_qu_name','gather_sheng_name','gather_address'];
//        $self_id='line_202101041055337562586879';
        $where['self_id'] = $self_id;
        $where['delete_flag'] = 'Y';
        $selectList = ['line_id','self_id','yuan_self_id'];
        $selectList2 = ['self_id','use_flag','delete_flag'];
        $info = TmsLine::with(['tmsLineList'=>function($query)use($selectList,$selectList2){
            $query->where('delete_flag','=','Y');
            $query->select($selectList);
            $query->with(['tmsLine'=>function($query)use($selectList2){
                $query->select($selectList2);
            }]);
        }])->where($where)->select($select)->first();
//        dd($info);
        if($info){

            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $info->price=$info->price/100;
            $info->min_money=$info->min_money/100;
            $info->pick_price=$info->pick_price/100;
            $info->send_price=$info->send_price/100;

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

    /***    线路禁用/启用      /tms/line/lineUseFlag
     */
    public function use_flag(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $table_name='tms_line';
        $self_id=$request->input('self_id');
//        $self_id='line_202101041055337562586879';
//        $self_id='line_202101031051573138452569';
//        $use_flag = true;
        $where['self_id']=$self_id;
        $select=['self_id','use_flag','delete_flag', 'update_time', 'group_code', 'group_name','type'];
        $selectList=['line_id','self_id','yuan_self_id'];
        $selectList2=['self_id','use_flag','delete_flag'];
        $line = TmsLine::with(['tmsLineList' => function($query)use($selectList,$selectList2){
            $query->where('delete_flag','=','Y');
            $query->select($selectList);
            $query->with(['tmsLine' => function($query)use($selectList2){
                $query->select($selectList2);
            }]);
        }])->where($where)->select($select)->first();


        if($line){
            switch ($line->type){
                case 'combination':
                    $cando='Y';
                    if($line->use_flag == 'Y'){
                        $data['use_flag']='N';
                        $data['update_time']=$now_time;
                    }else{
                        /** 如果是启用这个线路，请注意，必须保证他关联的数据全部是能启用的姿态*/
                        foreach ($line->tmsLineList as $k => $v){
                            if($v->tmsLine->use_flag == 'N' || $v->tmsLine->delete_flag == 'N'){
                                $cando='N';
                            }
                        }

                        if($cando=='N'){
                            $msg['code']=301;
                            $msg['msg']="组合线路中包含不运行的线路";
                            return $msg;
                        }

                        $data['use_flag']='Y';
                        $data['update_time']=$now_time;

                    }
                    $id=TmsLine::where($where)->update($data);

                    break;
                case 'alone':
                    if($line->use_flag == 'N'){
                        $data['use_flag']='Y';
                        $data['update_time']=$now_time;
                        $id=TmsLine::where($where)->update($data);

                        $line_info = TmsLineList::where('yuan_self_id',$self_id)->pluck('line_id')->toArray();


                        $line = TmsLine::with(['tmsLineList' => function($query)use($selectList,$selectList2){
                            $query->where('delete_flag','=','Y');
                            $query->select($selectList);
                            $query->with(['tmsLine' => function($query)use($selectList2){
                                $query->select($selectList2);
                            }]);
                        }])->whereIn('self_id',$line_info)->select($select)->get();

                        $data['use_flag']='Y';
                        $data['update_time']=$now_time;
                        foreach ($line as $k => $v){
                            $cando='Y';
                            foreach ($v->tmsLineList as $kk => $vv){
//                                dump($vv->tmsLine->use_flag);
                                if($vv->tmsLine->use_flag == 'N' || $vv->tmsLine->delete_flag == 'N'){
                                    $cando='N';
                                }
                            }
                            if($cando=='Y'){
                                $where2222['self_id']=$v->self_id;
                                TmsLine::where($where2222)->update($data);
                            }
                        }
                    }else{
                        $line_info = TmsLineList::where('yuan_self_id',$self_id)->pluck('line_id')->toArray();

                        $where_line=[
                            ['delete_flag','=','Y'],
                            ['use_flag','=','Y'],
                        ];
                        $data['use_flag'] = 'N';
                        $data['update_time'] = $now_time;
                        $id=TmsLine::where($where)->update($data);
                        TmsLine::where($where_line)->whereIn('self_id',$line_info)->update($data);

                    }
                    break;
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
            $msg['code']=300;
            $msg['msg']="查询不到数据";
            return $msg;

        }
    }


    /***    线路删除     /tms/line/lineDelFlag
     */
    public function delete_flag(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $table_name='tms_line';
        $self_id=$request->input('self_id');
//        $self_id='line_202101041055337562586879';
        $where['self_id']=$self_id;
        $where['delete_flag']='Y';
        $select=['self_id','use_flag','delete_flag', 'update_time', 'group_code', 'group_name','type'];
        $selectList=['line_id','self_id','yuan_self_id'];
        $selectList2=['self_id','use_flag','delete_flag'];
        $line = TmsLine::with(['tmsLineList' => function($query)use($selectList,$selectList2){
            $query->where('delete_flag','=','Y');
            $query->select($selectList);
            $query->with(['tmsLine' => function($query)use($selectList2){
                $query->select($selectList2);
            }]);
        }])->where($where)->select($select)->first();

//        dump($line->toArray());

        if($line){
            switch ($line->type){
                case 'combination':
                    $data['delete_flag']='N';
                    $data['update_time']=$now_time;
                    $id=TmsLine::where($where)->update($data);
                    break;
                case 'alone':
                    $line_info = TmsLineList::where('yuan_self_id',$self_id)->pluck('line_id')->toArray();

                    $where_line=[
                        ['delete_flag','=','Y'],
                        ['use_flag','=','Y'],
                    ];
                    $edit['delete_flag'] = 'N';
                    $edit['update_time'] = $now_time;
                    $id=TmsLine::where($where)->update($edit);
                    TmsLine::where($where_line)->whereIn('self_id',$line_info)->update($edit);
                    break;
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
            $msg['code']=300;
            $msg['msg']="查询不到数据";
            return $msg;

        }
    }


}
?>

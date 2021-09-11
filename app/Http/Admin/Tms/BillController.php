<?php
namespace App\Http\Admin\Tms;

use App\Http\Controllers\CommonController;
use App\Http\Controllers\DetailsController as Details;
use App\Models\Tms\TmsBill;
use App\Models\Tms\TmsCommonBill;
use App\Models\Tms\TmsOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\StatusController as Status;

class BillController extends CommonController {
    /**
     * 开票订单列表 /tms/bill/order_list
     * */
    public function order_list(Request $request){
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
        $tax_flag          =$request->input('tax_flag');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;
        $order_status = 3;
        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'all','name'=>'use_flag','value'=>$use_flag],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
            ['type'=>'=','name'=>'company_id','value'=>$company_id],
            ['type'=>'=','name'=>'type','value'=>$type],
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'order_status','value'=>6],
            ['type'=>'=','name'=>'tax_flag','value'=>$tax_flag]
        ];

        $where=get_list_where($search);

        $select=['self_id','group_name','company_name','create_user_name','create_time','use_flag','order_type','order_status','pay_type','pay_state',
            'gather_address_id','gather_contacts_id','gather_name','gather_tel','gather_sheng','gather_shi','gather_qu','gather_qu_name','gather_address',
            'send_address_id','send_contacts_id','send_name','send_tel','send_sheng','send_shi','send_qu','send_address','total_money','total_user_id',
            'good_name','good_number','good_weight','good_volume','gather_shi_name','send_shi_name','send_qu_name','car_type','clod','send_time','gather_time','tax_flag'];
        $select2 = ['self_id','parame_name'];
        $select3 = ['self_id','total_user_id','tel'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsOrder::where($where)->count(); //总的数据量
                $data['items']=TmsOrder::with(['TmsCarType' => function($query) use($select2){
                    $query->select($select2);
                }])
                    ->with(['userReg' => function($query) use($select3){
                        $query->select($select3);
                    }])
                    ->where($where)
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
                    ->where($where)
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
                    ->where($where)
                    ->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }

//        dd($data['items']->toArray());
//        dd($tms_order_status_type);

        foreach ($data['items'] as $k=>$v) {
            $v->total_money = number_format($v->total_money/100, 2);
            $v->total_money_show = $v->total_money;
            $v->order_status_show=$tms_order_status_type[$v->order_status]??null;
            $v->order_type_show=$tms_order_type[$v->order_type]??null;
            $v->type_inco = img_for($tms_order_inco_type[$v->order_type],'no_json')??null;
            $v->button_info=$button_info;
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
     * 开票列表头部 /tms/bill/billList
     * */
    public function billList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }


    /**
     * 开票列表 /tms/bill/billPage
     * */
    public function billPage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数
        $tax_type = array_column(config('tms.tax_type'),'name','key');
        $bill_type = array_column(config('tms.bill_type'),'name','key');
        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $group_code     =$request->input('group_code');
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
        ];

        $where=get_list_where($search);

        $select=['self_id','order_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel','name','tel','remark','tax_price',
            'total_user_id','group_name','group_code','delete_flag','create_time','bill_type','bill_flag','repeat_flag'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsBill::where($where)->count(); //总的数据量
                $data['items']=TmsBill::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=TmsBill::where($where)->count(); //总的数据量
                $data['items']=TmsBill::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsBill::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsBill::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }
        // dd($data['items']->toArray());

        foreach ($data['items'] as $k=>$v) {
            $v->button_info=$button_info;
            $v->tax_type_show =  $tax_type[$v->type] ?? null;
            $v->bill_type_show =  $bill_type[$v->bill_type] ?? null;
            if ($v->bill_flag == 'Y'){
                $v->bill_flag_show = '已开票';
            }else{
                $v->bill_flag_show = '未开票';
            }

            $button1 = [];
            foreach ($button_info as $key => $value){
                if ($value->id == 231 ){
                    $button1[] = $value;
                }
                if ($v->repeat_flag == 'N'){
                    $v->button = $button1;
                }else{
                    $v->button = [];
                }
            }

        }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        //dd($msg);
        return $msg;
    }

    /**
     * 新建开票      /tms/bill/createBill
     */
    public function createBill(Request $request){
        /** 接收数据*/
        $self_id = $request->input('self_id');
//        $self_id = 'car_20210313180835367958101';
        $tax_type = array_column(config('tms.tax_type'),'name','key');
        $bill_type = array_column(config('tms.bill_type'),'name','key');

        $where = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select = ['self_id','order_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel','name','tel','remark','tax_price',
            'total_user_id','group_name','group_code','delete_flag','create_time','bill_type'];
        $data['info'] = TmsBill::where($where)->select($select)->first();
        if ($data['info']){
            $data['info']->tax_type_show =  $tax_type[$data['info']->type] ?? null;
            $data['info']->bill_type_show =  $bill_type[$data['info']->bill_type] ?? null;
        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }

    /**
     * 添加开票  /tms/bill/addBill
     * */
    public function addBill(Request $request){
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_group';

        $operationing->access_cause     ='创建/修改司机';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;

        $user_info = $request->get('user_info');//接收中间件产生的参数
        $input              =$request->all();
        /** 接收数据*/
        $self_id               = $request->input('self_id');
        $order_id              = $request->input('order_id'); //订单ID
        $type                  = $request->input('type'); //发票类型：普票normal  增值税专票special
        $bill_type             = $request->input('bill_type'); //发票抬头类型
        $company_title         = $request->input('company_title');
        $company_tax_number    = $request->input('company_tax_number');
        $bank_name             = $request->input('bank_name');
        $bank_num              = $request->input('bank_num');
        $company_address       = $request->input('company_address');
        $company_tel           = $request->input('company_tel');
        $name                  = $request->input('name');
        $tel                   = $request->input('tel');
        $contact_address       = $request->input('contact_address');
        $remark                = $request->input('remark');
        $tax_price             = $request->input('tax_price');

        /*** 虚拟数据
        $input['self_id']            = $self_id             ='group_202006040950004008768595';
        $input['order_id']           = $order_id            ='1234';
        $input['type']               = $type                ='pull';
        $input['bill_type']          = $bill_type           ='152';
        $input['company_title']      = $company_title       ='pull';
        $input['company_tax_number'] = $company_tax_number  ='客户';
        $input['bank_name']          = $bank_name           ='客户';
        $input['bank_num']           = $bank_num            ='客户';
        $input['company_address']    = $company_address     ='客户';
        $input['company_tel']        = $company_tel         ='客户';
        $input['name']               = $name                ='客户';
        $input['tel']                = $tel                 ='客户';
        $input['contact_address']    = $contact_address     ='客户';
        $input['tax_price']          = $tax_price           ='客户';
        $input['remark']             = $remark              ='客户';
         ***/
//        dd($input);
        if ($type == 'company'){
            $rules = [
                'company_title'=>'required',
                'company_tax_number'=>'required',
                'bank_name'=>'required',
                'bank_num'=>'required',
                'company_address'=>'required',
                'company_tel'=>'required',
                'bill_type'=>'required',
            ];
            $message = [
                'company_title.required'=>'请填写公司抬头',
                'company_tax_number.required'=>'请填写税号',
                'bank_name.required'=>'请填写开户行名称',
                'bank_num.required'=>'请填写开户行账号',
                'company_address.required'=>'请填写企业注册地址',
                'company_tel.required'=>'请填写企业联系电话',
                'bill_type.required'=>'请选择发票类型',
            ];
        }else{
            $rules = [
                'company_title'=>'required',
            ];
            $message = [
                'company_title.required'=>'请填写公司抬头',
            ];
        }
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()){
            $data['order_id']            = $order_id;
            $data['type']                = $type;
            $data['bill_type']           = $bill_type;
            $data['company_title']       = $company_title;
            $data['company_tax_number']  = $company_tax_number;
            $data['bank_name']           = $bank_name;
            $data['bank_num']            = $bank_num;
            $data['company_address']     = $company_address;
            $data['company_tel']         = $company_tel;
            $data['name']                = $name;
            $data['tel']                 = $tel;
            $data['contact_address']     = $contact_address;
            $data['remark']              = $remark;
            $data['tax_price']           = $tax_price*100;

            $wheres['self_id'] = $self_id;
            $old_info = TmsBill::where($wheres)->first();

            if($old_info){
                $data['update_time'] = $now_time;
                $data['repeat_flag'] = 'Y';
                $id = TmsBill::where($wheres)->update($data);

                $operationing->access_cause='修改开票信息';
                $operationing->operation_type='update';
            }else{
                $data['self_id']          = generate_id('bill_');
                $data['group_code']    = $user_info->group_code;
                $data['create_time']      = $data['update_time'] = $now_time;
                $id = TmsBill::insert($data);
                $update['tax_flag'] ='Y';
                $order_info = TmsOrder::whereIn('self_id',json_decode($order_id,true))->update($update);
                $operationing->access_cause='新增开票';
                $operationing->operation_type='create';

            }
            $operationing->table_id=$old_info?$self_id:$data['self_id'];
            $operationing->old_info=$old_info;
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
     * 删除开票记录 /tms/bill/billDelFlag
     * */
    public function billDelFlag(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_bill';
        $medol_name='TmsBill';
        $self_id=$request->input('self_id');
        $flag='delFlag';
//        $self_id='car_202012242220439016797353';

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

    /**
     * 开票详情 /tms/bill/details
     * */
    public function details(Request $request, Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'tms_bill';
        $select = ['self_id','order_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel','name','tel','remark','tax_price',
            'total_user_id','group_name','group_code','delete_flag','create_time','bill_type','contact_address'];
        $select1 = ['self_id','send_shi_name','gather_shi_name','total_money'];
        // $self_id = 'car_202101111749191839630920';
        $info = $details->details($self_id,$table_name,$select);
        if($info) {
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $tax_type     = array_column(config('tms.tax_type'),'name','key');
            $bill_type = array_column(config('tms.bill_type'),'name','key');
            $info->tax_price = number_format($info->tax_price/100,2);
            $info->tax_type_show = $tax_type[$info->type]??null;
            $info->bill_type_show = $bill_type[$info->bill_type]??null;
            $data['info'] = $info;
            $msg['code']  = 200;
            $msg['msg']   = "数据拉取成功";
            $msg['data']  = $data;
            return $msg;
        }else{
            $msg['code'] = 300;
            $msg['msg']  = "没有查询到数据";
            return $msg;
        }
    }

    /**
     * 开票关联订单 /api/bill/ordeList
     * */
    public function orderList(Request $request){
        $self_id    = $request->input('order_id');
        $table_name = 'tms_bill';
        $order_id = json_decode($self_id,true);
        $select = ['self_id','send_shi_name','gather_shi_name','total_money','create_time','update_time','send_sheng_name','send_qu_name',
            'gather_sheng_name','gather_qu_name','gather_time'];
        // $self_id = 'car_202101111749191839630920';
        $info = TmsOrder::whereIn('self_id',$order_id)->select($select)->get();
//        $info = $details->details($self_id,$table_name,$select);
        if($info) {
            /** 如果需要对数据进行处理，请自行在下面对 $info 进行处理工作*/
            foreach ($info as $k =>$v){
                $v->total_money = number_format($v->total_money/100,2);
                $v->order_id_show = substr($v->self_id,15);
//                $v->on_line_money = number_format($v->on_line_money/100,2);
            }

            $data['info'] = $info;
            $msg['code']  = 200;
            $msg['msg']   = "数据拉取成功";
            $msg['data']  = $data;
            return $msg;
        }else{
            $msg['code'] = 300;
            $msg['msg']  = "没有查询到数据";
            return $msg;
        }
    }

    /**
     * 常用开票抬头  /tms/bill/commonBillList
     * */
    public function commonBillList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        $abc='车辆类型';

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 常用开票抬头列表 /tms/bill/commonBillPage
     * */
    public function commonBillPage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数
        $tax_type = array_column(config('tms.tax_type'),'name','key');

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

        $select=['self_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel',
            'total_user_id','group_code','delete_flag','create_time','default_flag','special_use','use_flag'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsCommonBill::where($where)->count(); //总的数据量
                $data['items']=TmsCommonBill::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=TmsCommonBill::where($where)->count(); //总的数据量
                $data['items']=TmsCommonBill::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsCommonBill::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsCommonBill::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }


        foreach ($data['items'] as $k=>$v) {
            $v->button_info=$button_info;
            $v->type_show = $tax_type[$v->type]??null;
        }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 添加常用抬头 /tms/bill/createCommonBill
     * */
    public function createCommonBill(Request $request){
        /** 接收数据*/
        $self_id=$request->input('self_id');
//        $self_id = 'car_20210313180835367958101';
        $tax_type = array_column(config('tms.tax_type'),'name','key');
        $where=[
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select=['self_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel',
            'total_user_id','group_code','delete_flag','create_time','default_flag','license','use_flag','delete_flag'];

        $data['info']=TmsCommonBill::where($where)->select($select)->first();

        if ($data['info']){
            $data['info']->type_show = $tax_type[$data['info']->type] ??null;
            $data['info']->license = img_for($data['info']->license,'one');
        }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
//        dd($msg);
        return $msg;
    }

    /**
     * 添加/编辑常用开票抬头 /tms/bill/addCommonBill
     * */
    public function addCommonBill(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_common_bill';
        $operationing->access_cause     ='创建/修改发票抬头';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';
        $input              =$request->all();

        /** 接收数据*/
        $self_id               = $request->input('self_id');
        $type                  = $request->input('type');
        $company_title         = $request->input('company_title');
        $company_tax_number    = $request->input('company_tax_number');
        $bank_name             = $request->input('bank_name');
        $bank_num              = $request->input('bank_num');
        $company_address       = $request->input('company_address');
        $company_tel           = $request->input('company_tel');
        $special_use           = $request->input('special_use');
        $license               = $request->input('license');
        $default_flag          = $request->input('default_flag');

        /*** 虚拟数据

         ***/
        if ($type == 'company'){
            $rules = [
                'company_title'=>'required',
                'company_tax_number'=>'required',
                'bank_name'=>'required',
                'bank_num'=>'required',
                'company_address'=>'required',
                'company_tel'=>'required',
            ];
            $message = [
                'company_title.required'=>'请填写公司抬头',
                'company_tax_number.required'=>'请填写税号',
                'bank_name.required'=>'请填写开户行名称',
                'bank_num.required'=>'请填写开户行账号',
                'company_address.required'=>'请填写企业注册地址',
                'company_tel.required'=>'请填写企业联系电话',
            ];
        }else{
            $rules = [
                'company_title'=>'required',
            ];
            $message = [
                'company_title.required'=>'请填写公司抬头',
            ];
        }

        if ($special_use == 'Y'){
            $rules = [
                'license'=>'required',
            ];
            $message = [
                'license.required'=>'请上传营业执照',
            ];
        }

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $data['type']                = $type;  //抬头类型：company,personal
            $data['company_title']       = $company_title;
            $data['company_tax_number']  = $company_tax_number;
            $data['bank_name']           = $bank_name;
            $data['bank_num']            = $bank_num;
            $data['company_address']     = $company_address;
            $data['company_tel']         = $company_tel;
            $data['special_use']         = $special_use;
            $data['license']             = img_for($license,'in');
            $data['default_flag']        = $default_flag;

            $wheres['self_id'] = $self_id;
            $old_info=TmsCommonBill::where($wheres)->first();
            if ($default_flag == 'Y'){
                $update['default_flag'] = 'N';
                TmsCommonBill::where('default_flag','Y')->where('group_code',$user_info->group_code)->update($update);
            }
            if($old_info){
                $data['update_time']=$now_time;
                $id=TmsCommonBill::where($wheres)->update($data);
                $operationing->access_cause='修改地址';
                $operationing->operation_type='update';

            }else{
                $data['self_id']          = generate_id('bill_');
                $data['group_code']       = $user_info->group_code;
                $data['create_time']      = $data['update_time'] = $now_time;
                $id = TmsCommonBill::insert($data);
                $operationing->access_cause='新建地址';
                $operationing->operation_type='create';

            }

            $operationing->table_id=$old_info?$self_id:$data['self_id'];
            $operationing->old_info=$old_info;
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
     * 启用/禁用常用发票抬头 /tms/bill/useCommonBill
     * */
    public function useCommonBill(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_common_bill';
        $self_id=$request->input('self_id');
        $use_flag=$request->input('use_flag');
        $flag='use_flag';
//        $self_id='address_202103011352018133677963';
        $old_info = TmsCommonBill::where('self_id',$self_id)->select('group_code','use_flag','delete_flag','update_time')->first();
        $update['use_flag'] = $use_flag;
        $update['update_time'] = $now_time;
        $id = TmsCommonBill::where('self_id',$self_id)->update($update);

        $operationing->access_cause='启用/禁用';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$old_info;
        $operationing->new_info=(object)$update;
        $operationing->operation_type=$flag;
        if($id){
            $msg['code']=200;
            $msg['msg']='操作成功！';
            $msg['data']=(object)$update;
        }else{
            $msg['code']=300;
            $msg['msg']='操作失败！';
        }

        return $msg;
    }

    /**
     * 删除常用发票抬头 /tms/bill/delCommonBill
     * */
    public function delCommonBill(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_common_bill';
        $self_id=$request->input('self_id');
        $flag='delete_flag';
//        $self_id='address_202103011352018133677963';
        $old_info = TmsCommonBill::where('self_id',$self_id)->select('group_code','use_flag','delete_flag','update_time')->first();
        $update['delete_flag'] = 'N';
        $update['update_time'] = $now_time;
        $id = TmsCommonBill::where('self_id',$self_id)->update($update);
        $operationing->access_cause='删除';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$old_info;
        $operationing->new_info=(object)$update;
        $operationing->operation_type=$flag;
        if($id){
            $msg['code']=200;
            $msg['msg']='删除成功！';
            $msg['data']=(object)$update;
        }else{
            $msg['code']=300;
            $msg['msg']='删除失败！';
        }

        return $msg;
    }

    /**
     * 常用发票详情 /tms/bill/billDetails
     * */
    public function billDetails(Request $request, Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'tms_common_bill';
        $select     = ['self_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel',
            'total_user_id','group_code','delete_flag','create_time','default_flag','special_use','license'
        ];
        // $self_id='address_202101111755143321983342';
        $info = $details->details($self_id,$table_name,$select);

        if($info) {
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $data['info'] = $info;
            $msg['code']  = 200;
            $msg['msg']   = "数据拉取成功";
            $msg['data']  = $data;
            return $msg;
        }else{
            $msg['code'] = 300;
            $msg['msg']  = "没有查询到数据";
            return $msg;
        }
    }

    /**
     * 发票抬头专票认证头部 /tms/bill/billTitleList
     * */
    public function billTitleList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 发票抬头专票认证 /tms/bill/billTitlePage
     * */
    public function billTitlePage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数
        $tax_type = array_column(config('tms.tax_type'),'name','key');

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
            ['type'=>'!=','name'=>'special_use','value'=>'N'],
        ];
        $where=get_list_where($search);

        $select=['self_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel',
            'total_user_id','group_code','delete_flag','create_time','default_flag','special_use','use_flag'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsCommonBill::where($where)->count(); //总的数据量
                $data['items']=TmsCommonBill::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=TmsCommonBill::where($where)->count(); //总的数据量
                $data['items']=TmsCommonBill::where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsCommonBill::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsCommonBill::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }

        $button_info1=[];
        $button_info2=[];
        foreach ($button_info as $key => $value){
//            dump($v);
            if($value->id==738){
                $button_info2[]=$value;
            }
            if($value->id==737){
                $button_info2[]=$value;
            }
            if($value->id==739){
                $button_info1[]=$value;
                $button_info2[]=$value;
            }
        }
        foreach ($data['items'] as $k=>$v) {
            $v->button_info=$button_info;
            $v->type_show = $tax_type[$v->type]??null;
            if ($v->special_use == 'W'){
                $v->button_info=$button_info2;
            }elseif($v->special_use == 'Y'){
                $v->button_info=$button_info1;
            }else{
                $v->button_info=$button_info1;
            }
        }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 认证通过 /tms/bill/billSuccess
     * */
    public function billSuccess(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_common_bill';
        $self_id=$request->input('self_id');
        $special_use=$request->input('special_use');
        $flag='use_flag';
//        $self_id='address_202103011352018133677963';
        $old_info = TmsCommonBill::where('self_id',$self_id)->select('group_code','use_flag','delete_flag','update_time')->first();
        $update['special_use'] = $special_use;
        $update['update_time'] = $now_time;
        $id = TmsCommonBill::where('self_id',$self_id)->update($update);

        $operationing->access_cause='启用/禁用';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$old_info;
        $operationing->new_info=(object)$update;
        $operationing->operation_type=$flag;
        if($id){
            $msg['code']=200;
            $msg['msg']='操作成功！';
            $msg['data']=(object)$update;
        }else{
            $msg['code']=300;
            $msg['msg']='操作失败！';
        }

        return $msg;
    }






































































































































































































































}

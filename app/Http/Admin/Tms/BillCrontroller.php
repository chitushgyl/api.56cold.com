<?php

namespace App\Http\Admin\Tms;

use App\Models\Tms\TmsBill;
use App\Models\Tms\TmsCommonBill;
use http\Env\Request;
use Illuminate\Support\Facades\Validator;

class BillCrontroller extends CommonController{


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
            'total_user_id','group_name','group_code','delete_flag','create_time'];
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
            $v->car_possess_show=$tms_car_possess_type[$v->car_possess]??null;
            $v->tms_control_type_show=$tms_control_type[$v->control]??null;
        }


        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        //dd($msg);
        return $msg;
    }

    /**
     * 添加开票  /tms/bill/addBill
     * */
    public function addBill(Request $request){

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
            'total_user_id','group_code','delete_flag','create_time','default_flag'];
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
            'total_user_id','group_code','delete_flag','create_time','default_flag'];

        $data['info']=TmsCommonBill::where($where)->select($select)->first();

        if ($data['info']){
            $data['info']->type_show = $tax_type[$data['info']->type] ??null;
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
            $data['license']             = $license;
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
        $flag='use_flag';
//        $self_id='address_202103011352018133677963';
        $old_info = TmsCommonBill::where('self_id',$self_id)->select('group_code','use_flag','delete_flag','update_time')->first();
        $update['use_flag'] = 'N';
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





































































































































































































































}

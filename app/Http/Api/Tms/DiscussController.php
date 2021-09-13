<?php
namespace App\Http\Api\Tms;

use App\Http\Controllers\CommonController;
use App\Models\Tms\TmsDiscuss;
use Illuminate\Http\Request;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;

class DiscussController extends CommonController{
    /**
     * 评论列表
     * */
    public function discussPage(Request $request){
        /** 接收中间件参数**/
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $project_type       =$request->get('project_type');
        $button_info =     $request->get('buttonInfo');
        $tax_type = array_column(config('tms.tax_type'),'name','key');
        $bill_type = array_column(config('tms.bill_type'),'name','key');
//        $total_user_id = $user_info->total_user_id;
        /**接收数据*/
        $num           = $request->input('num')??10;
        $page          = $request->input('page')??1;
        $listrows      = $num;
        $firstrow      = ($page-1)*$listrows;
        $search = [];

        $search = [
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'total_user_id','value'=>$user_info->total_user_id],
        ];

        $where=get_list_where($search);

        $select=['self_id','order_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel','name','tel','remark','tax_price',
            'total_user_id','group_name','group_code','delete_flag','create_time','bill_type','bill_flag','repeat_flag'];
        $data['info'] = TmsBill::where($where)
            ->offset($firstrow)
            ->limit($listrows)
            ->orderBy('create_time', 'desc')
            ->select($select)
            ->get();
        $data['total'] = TmsBill::where($where)->count();
        foreach ($data['info'] as $key => $value){
            $value->tax_type_show =  $tax_type[$value->type] ?? null;
            $value->bill_type_show =  $bill_type[$value->bill_type] ?? null;
            if ($value->bill_flag == 'Y'){
                $value->bill_flag_show = '已开票';
            }else{
                $value->bill_flag_show = '未开票';
            }
            $button1 = [];
            foreach ($button_info as $k => $v){
                if ($v->id == 231 ){
                    $button1[] = $v;
                }
                if ($value->repeat_flag == 'N'){
                    $value->button = $button1;
                }else{
                    $value->button = [];
                }
            }
        }
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 添加编辑获取评论数据
     * */
    public function createDiscuss(Request $request){
        /** 接收数据*/
        $self_id = $request->input('self_id');
//        $self_id = 'car_20210313180835367958101';
        $tax_type = array_column(config('tms.tax_type'),'name','key');
        $bill_type = array_column(config('tms.bill_type'),'name','key');

        $where = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select = ['self_id'];
        $data['info'] = TmsDiscuss::where($where)->select($select)->first();
        if ($data['info']){

        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }

    /**
     * 添加评论
     * */
    public function addDiscuss(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $total_user_id = $user_info->total_user_id;
//        $token_name    = $user_info->token_name;
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

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
        //      $input['self_id']           =$self_id='good_202007011336328472133661';

         **/
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


        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
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

            }else{
                $data['self_id']          = generate_id('car_');
                $data['total_user_id']    = $total_user_id;
//                $data['create_user_id']   = $total_user_id;
//                $data['create_user_name'] = $token_name;
                $data['create_time']      = $data['update_time'] = $now_time;
                $id = TmsBill::insert($data);
                $update['tax_flag'] ='Y';
                $order_info = TmsOrder::whereIn('self_id',json_decode($order_id,true))->update($update);
            }

            if($id){
                $msg['code'] = 200;
                $msg['msg']  = "操作成功";
                return $msg;
            }else{
                $msg['code'] = 302;
                $msg['msg']  = "操作失败";
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
     *删除评论
     * */
    public function delFlag(Request $request,Status $status){
        $now_time     = date('Y-m-d H:i:s',time());
        $operationing =  $request->get('operationing');//接收中间件产生的参数
        $table_name   = 'tms_discuss';
        $medol_name   = 'TmsDiscuss';
        $self_id = $request->input('self_id');
        $flag    = 'delFlag';
        // $self_id = 'car_202101111723422044395481';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);
        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];
        return $msg;
    }





























}

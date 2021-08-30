<?php
namespace App\Http\Api\Tms;
use App\Models\Tms\TmsBill;
use App\Models\Tms\TmsCommonBill;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;



class BillController extends Controller{
      /**
       * 开票列表（历史记录） /api/bill/billPage
       * */
    public function billPage(Request $request){
        /** 接收中间件参数**/
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $project_type       =$request->get('project_type');
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
            'total_user_id','group_name','group_code','delete_flag','create_time'];
        $data['info'] = TmsBill::where($where)
            ->offset($firstrow)
            ->limit($listrows)
            ->orderBy('create_time', 'desc')
            ->select($select)
            ->get();
        $data['total'] = TmsBill::where($where)->count();
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 新建开票      /api/bill/createBill
     */
    public function createBill(Request $request){
        /** 接收数据*/
        $self_id = $request->input('self_id');
//        $self_id = 'car_20210313180835367958101';
        $tax_type = array_column(config('tms.tax_type'),'name','key');

        $where = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select = ['self_id','order_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel','name','tel','remark','tax_price',
            'total_user_id','group_name','group_code','delete_flag','create_time'];
        $data['info'] = TmsBill::where($where)->select($select)->first();
        if ($data['info']){
            $data['info']->tax_type_show =  $tax_type[$data['info']->type] ?? null;
        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }

    /**
     * 添加 开票  /api/bill/billAdd
     * */
    public function billAdd(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $total_user_id = $user_info->total_user_id;
//        $token_name    = $user_info->token_name;
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $self_id               = $request->input('self_id');
        $order_id              = $request->input('order_id'); //订单ID
        $type                  = $request->input('type'); //发票抬头类型
        $company_title         = $request->input('company_title');
        $company_tax_number    = $request->input('company_tax_number');
        $bank_name             = $request->input('bank_name');
        $bank_num              = $request->input('bank_num');
        $company_address       = $request->input('company_address');
        $company_tel           = $request->input('company_tel');
        $name                  = $request->input('name');
        $tel                   = $request->input('tel');
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


        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $data['order_id']            = $order_id;
            $data['type']                = $type;
            $data['company_title']       = $company_title;
            $data['company_tax_number']  = $company_tax_number;
            $data['bank_name']           = $bank_name;
            $data['bank_num']            = $bank_num;
            $data['company_address']     = $company_address;
            $data['company_tel']         = $company_tel;
            $data['name']                = $name;
            $data['tel']                 = $tel;
            $data['remark']              = $remark;
            $data['tax_price']           = $tax_price;

            $wheres['self_id'] = $self_id;
            $old_info = TmsBill::where($wheres)->first();

            if($old_info){
                $data['update_time'] = $now_time;
                $id = TmsBill::where($wheres)->update($data);

            }else{
                $data['self_id']          = generate_id('car_');
                $data['total_user_id']    = $total_user_id;
//                $data['create_user_id']   = $total_user_id;
//                $data['create_user_name'] = $token_name;
                $data['create_time']      = $data['update_time'] = $now_time;
                $id = TmsBill::insert($data);
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
     * 删除开票记录  /api/bill/delFlag
     * */
    public function delFlag(Request $request,Status $status){
        $now_time     = date('Y-m-d H:i:s',time());
        $operationing =  $request->get('operationing');//接收中间件产生的参数
        $table_name   = 'tms_bill';
        $medol_name   = 'TmsBill';
        $self_id = $request->input('self_id');
        $flag    = 'delFlag';
        // $self_id = 'car_202101111723422044395481';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);
        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];
        return $msg;
    }

    /**
     * 开票详情 /api/bill/details
     * */
    public function details(Request $request){

    }


    /**
     * 常用开票抬头列表  api/bill/commonBillList
     * */
    public function commonBillList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        $abc='开票';
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 常用开票抬头列表  api/bill/commonBillPage
     * */
    public function commonBillPage(Request $request){
        /** 接收中间件参数**/
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $project_type       =$request->get('project_type');
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
        $select=['self_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel',
            'total_user_id','group_code','delete_flag','create_time','default_flag'];
        $data['info'] = TmsCommonBill::where($where)
            ->offset($firstrow)
            ->limit($listrows)
            ->orderBy('create_time', 'desc')
            ->select($select)
            ->get();
        $data['total'] = TmsCommonBill::where($where)->count();
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 添加编辑获取数据 /api/bill/createCommonBill
     * */
    public function createCommonBill(Request $request){
        /** 接收数据*/
        $self_id = $request->input('self_id');
//        $self_id = 'car_20210313180835367958101';
        $tax_type = array_column(config('tms.tax_type'),'name','key');

        $where = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select = ['self_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel',
            'total_user_id','group_code','delete_flag','create_time','default_flag'];
        $data['info'] = TmsCommonBill::where($where)->select($select)->first();
        if ($data['info']){
            $data['info']->tax_type_show =  $tax_type[$data['info']->type] ?? null;
        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }

    /**
     * 添加/编辑 /api/bill/addCommonBill
     * */
     public function addCommonBill(Request $request){
         $user_info = $request->get('user_info');//接收中间件产生的参数
         $total_user_id = $user_info->total_user_id;
         $now_time      = date('Y-m-d H:i:s',time());
         $input         = $request->all();

         /** 接收数据*/
         $self_id               = $request->input('self_id');
         $type                  = $request->input('type'); //抬头类型
         $company_title         = $request->input('company_title');
         $company_tax_number    = $request->input('company_tax_number');
         $bank_name             = $request->input('bank_name');
         $bank_num              = $request->input('bank_num');
         $company_address       = $request->input('company_address');
         $company_tel           = $request->input('company_tel');
         $special_use           = $request->input('special_use');//认证专票资质
         $license               = $request->input('license'); //营业执照
         $default_flag          = $request->input('default_flag'); //是否默认


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


         $validator = Validator::make($input,$rules,$message);
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
             $old_info = TmsCommonBill::where($wheres)->first();
             if ($default_flag == 'Y'){
                 $update['default_flag'] = 'N';
                 TmsCommonBill::where('default_flag','Y')->where('total_user_id',$total_user_id)->update($update);
             }
             if($old_info){
                 $data['update_time'] = $now_time;
                 $id = TmsCommonBill::where($wheres)->update($data);

             }else{
                 $data['self_id']          = generate_id('bill_');
                 $data['total_user_id']    = $total_user_id;
                 $data['create_time']      = $data['update_time'] = $now_time;
                 $id = TmsCommonBill::insert($data);
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
      * 禁用常用发票抬头 /api/bill/useCommonBill
      * */
    public function useCommonBill(Request $request,Status $status){
        $now_time    = date('Y-m-d H:i:s',time());
        $table_name  = 'tms_common_bill';
        $medol_name  = 'TmsCommonBill';
        $self_id     = $request->input('self_id');
        $flag        = 'useFlag';
        // $self_id  = 'address_202101111755143321983342';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);
        $msg['code'] = $status_info['code'];
        $msg['msg']  = $status_info['msg'];
        $msg['data'] = $status_info['new_info'];
        return $msg;
    }

     /**
      * 删除常用发票抬头  /api/bill/delCommonBill
      * */
    public function delCommonBill(Request $request,Status $status){
        $now_time    = date('Y-m-d H:i:s',time());
        $table_name  = 'tms_common_bill';
        $medol_name  = 'TmsCommonBill';
        $self_id     = $request->input('self_id');
        $flag        = 'delFlag';
        // $self_id  = 'address_202101111755143321983342';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);
        $msg['code'] = $status_info['code'];
        $msg['msg']  = $status_info['msg'];
        $msg['data'] = $status_info['new_info'];
        return $msg;
    }

    /**
     * 常用发票详情 /api/bill/billDetails
     * */
    public function billDetails(Request $request,Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'tms_common_bill';
        $select     = ['self_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel',
            'total_user_id','group_code','delete_flag','create_time','default_flag'
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




}
?>

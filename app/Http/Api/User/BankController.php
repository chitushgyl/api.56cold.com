<?php
namespace App\Http\Api\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\User\UserBank;
use App\Http\Controllers\StatusController as Status;

class BankController extends Controller{
    /**
     * 用户银行卡      /user/bank_page
     */
    public function bank_page(Request $request){
        $user_info		=$request->get('user_info');
		$where=[
			['total_user_id','=',$user_info->total_user_id],
			['delete_flag','=','Y'],
		];
		
		$select=['self_id as bank_id','bank_name','card_holder','card_number','create_time','delete_flag'];
		
		$bank_info=UserBank::where($where)->orderBy('create_time','desc')->select($select)->get();;
	
		$msg['code']=200;
		$msg['msg']='数据拉取成功！';
		$msg['data']=$bank_info;

		
        return $msg;
    }

 	/**
     * 我的银行卡创建      /user/creat_bank
     */
    public function creat_bank(Request $request){
        $user_info		=$request->get('user_info');
		$bank_id		=$request->input('self_id');
		$bank_where=[
            ['total_user_id','=',$user_info->total_user_id],
			['self_id','=',$bank_id],
			['delete_flag','=','Y'],
		];
	    $select=['self_id as bank_id','bank_name','card_holder','card_number','create_time','delete_flag'];	
		$bank_info=UserBank::where($bank_where)->select($select)->first();

        $msg['code']=200;
        $msg['msg']='数据拉取成功';
        $msg['data']=$bank_info;

        return $msg;

    }

    /**
     * 我的银行卡进入数据库      /user/add_bank
     */
    public function add_bank(Request $request){
        $user_info		=$request->get('user_info');
        $now_time       =date('Y-m-d H:i:s',time());
		$input			=$request->all();
		//dd($input);
        /** 接收数据*/
		$self_id				=$request->input('self_id');
        $bank_name         		=$request->input('bank_name');
		$card_holder         	=$request->input('card_holder');
		$card_number        	=$request->input('card_number');

        /*** 虚拟数据**/
        //$input['self_id']=$self_id='group_202007132106489285668739';
        $input['bank_name']=$bank_name='支付宝';
        $input['card_holder']=$card_holder='1545787';
        $input['card_number']=$card_number='163.com';


		$rules=[
			'bank_name'=>'required',
			'card_holder'=>'required',
			'card_number'=>'required',
		];
		$message=[
			'bank_name.required'=>'请填写银行名称',
			'card_holder.required'=>'请填写持卡人名称',
			'card_number.required'=>'请填写银行卡号',
		];
        /*** 虚拟数据**/
        //$input['bank_name'];
		$validator=Validator::make($input,$rules,$message);
		if($validator->passes()){
			//做一个流水记录1
			$data['total_user_id']  =$user_info->total_user_id;

			$data['bank_name']   	=$bank_name;
			$data['card_holder']  	=$card_holder;
			$data['card_number']    =$card_number;
			
			if($self_id){ //是修改
				$where2['self_id']		=$self_id;
				$data['update_time']	=$now_time;
				//dd($data);
				$id=UserBank::where($where2)->update($data);

			}else{  //是新增1
				$data['self_id']        =generate_id('bank_');
				$data['create_time']	=$data['update_time']=$now_time;
				//dd($data);
				$id=UserBank::insert($data);
			}
			
			if($id){
				$msg['code']=200;
				$msg['msg']="操作成功";
				$msg['data']=$data;
				return $msg;
			}else{
				$msg['code']=301;
				$msg['msg']="操作失败";
				return $msg;
			}

		}else{
			$erro=$validator->errors()->all();
			$msg['code']=300;
			$msg['msg']=$erro[0];
            return $msg;
		}

    }

   
    /**
     * 银行卡的删除操作     /user/del_bank
     */
    public function del_bank(Request $request,Status $status){

    	//dd(1211);
        $user_info		=$request->get('user_info');
        $now_time		=date('Y-m-d H:i:s',time());
        $table_name='user_bank';
        $medol_name='ShopCoupon';
        $flag='delFlag';
        $self_id		=$request->input('bank_id');

        $self_id		='bank_20210119101529275985711';


        $status_info=$status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);

        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];

        return $msg;


    }


}
?>

<?php
namespace App\Http\Api\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Group\SystemGroupApply;
use App\Models\Group\SystemGroup;
use App\Models\Group\SystemGroupApplyDetails;

class ApplyController extends Controller{
    /**
     * 门店审核加载数据      /user/apply_page
     */
    public function apply_page(Request $request){
        $user_info		=$request->get('user_info');				//接收中间件产生的参数

        $listrows		=config('page.listrows')[1];//每次加载的数量
        $first			=$request->input('page')??1;
        $firstrow		=($first-1)*$listrows;

		$apply_where=[
			['total_user_id','=',$user_info->total_user_id],
			['delete_flag','=','Y'],
		];
        $select=['self_id as apply_id','group_name','group_code','use_flag','create_time'];

        //dd($apply_where);
		$apply_info=SystemGroupApply::with(['systemGroup' => function($query) {
            $query->select('group_code','front_name');
        }])->with(['systemGroupFather' => function($query) {
            $query->select('group_code','front_name');
        }])
		->where($apply_where)->offset($firstrow)->limit($listrows)->orderBy('create_time','desc')
			->select($select)
			->get();

		//DD($apply_info -> toArray());
		$urld=config('page.platform.front_name');

		foreach ($apply_info as $k => $v){
			if($v->use_flag == 'Y'){

				$v->hosturl			=$v->systemGroup->front_name??$urld;
				$v->father_hosturl	=$v->systemGroupFather->front_name??$urld;
				if($v->hosturl == $urld){
					$v->hosturl ='http://'.$v->father_hosturl;
				}else{
					$v->hosturl ='http://'.$v->hosturl;
				}
			}
		}


		$msg['code']=200;
		$msg['msg']='数据拉取成功！';
		$msg['data']=$apply_info;
        return $msg;
    }

	/**
     * 商户申请的创建商户    /user/create_apply
     */
    public function create_apply(Request $request){
		$business_type=config('page.business_type');

		$msg['code']=200;
		$msg['msg']='数据拉取成功！';
		$msg['data']=$business_type;

		return $msg;

    }

    /**
     * 地址的添加和修改入库操作     /user/add_apply
     */
    public function add_apply(Request $request){
        $user_info		=$request->get('user_info');
        $now_time		=date('Y-m-d H:i:s',time());

        $input=$request->all();

        /** 接收数据*/
        $apply_id		=$request->input('apply_id');
        $group_name		=$request->input('group_name');
        $name			=$request->input('name');
        $leader_phone	=$request->input('leader_phone');
        $business_type	=$request->input('business_type');
        $default_login	=$request->input('default_login');


        /** 虚拟数据*/
        $group_name		=$input['group_name']		='xxXXxxx公司1111112121';
        $name			=$input['name']				='找图个1111';
        $leader_phone	=$input['leader_phone']		='12345678910';
        $business_type	=$input['business_type']	='SHOP';
        $default_login	=$input['default_login']	='1545@163.com';


		$rules=[
			'group_name'=>'required',
			'name'=>'required',
			'leader_phone'=>'required',
            'default_login'=>'required',
		];
		$message=[
			'group_name.required'=>'请填写申请的公司名称',
			'name.required'=>'请填写联系人',
			'leader_phone.required'=>'请填写联系人联系方式',
            'default_login.required'=>'请填写后台登录账号',
		];



		$validator=Validator::make($input,$rules,$message);

		 if($validator->passes()){

			if($apply_id){
				//说明是再次申请
				$apply_where=[
					['self_id','!=',$apply_id],
					['group_name','=',$group_name],
					['delete_flag','=','Y'],
				];
			}else{
				//说明是新申请
				$apply_where=[
					['group_name','=',$group_name],
					['delete_flag','=','Y'],
				];
			}

			//dd($apply_where);

			 $group_where=[
				 ['group_name','=',$group_name],
				 ['delete_flag','=','Y'],
			 ];
			 $system_group=SystemGroup::where($group_where)->first();

			 $system_group_apply=SystemGroupApply::where($apply_where)->first();

             if($system_group || $system_group_apply){
                 $msg['code']=301;
                 $msg['msg']='商户名称已存在';
                 return $msg;
             }

			 if($apply_id){
				 //说明是再次申请
				$apply_wheressss['self_id']=$apply_id;

				 $data['group_name']		=$group_name;
				 $data['update_time']		=$now_time;
				 $data['use_flag']			='W';

				 $id=SystemGroupApply::where($apply_wheressss)->update($data);

				 $data['self_id']=$apply_id;

				 $data_details['self_id']		=generate_id('apply_');
				 $data_details['apply_id']		=$apply_id;
				 $data_details['total_user_id']	=$user_info->total_user_id;
				 $data_details['group_code']	=SystemGroupApply::where($apply_wheressss)->value('group_code');
				 $data_details['group_name']	=$data['group_name'];
				 $data_details['name']			=$name;
				 $data_details['leader_phone']	=$leader_phone;
				 $data_details['business_type']	=$business_type;
				 $data_details['default_login']	=$default_login;
				 $data_details['use_flag']		='W';
				 $data_details['create_time']	=$data_details['update_time']	=$now_time;
				 SystemGroupApplyDetails::insert($data_details);


			 }else{
				 //说明是新申请
				 $data['self_id']=$data['group_code']	=generate_id('group_');
				 $data['group_name']					=$group_name;
				 $data['create_time']					=$data['update_time']=$now_time;
				 $data['use_flag']						='W';
				 $data['total_user_id']					=$user_info->total_user_id;
				 $id=SystemGroupApply::insert($data);


				 $data_details['self_id']				=generate_id('apply_');
				 $data_details['apply_id']				=$data['self_id'];
				 $data_details['total_user_id']			=$user_info->total_user_id;
				 $data_details['group_code']			=$data['group_code'];
				 $data_details['group_name']			=$data['group_name'];
				 $data_details['name']					=$name;
				 $data_details['leader_phone']			=$leader_phone;
				 $data_details['business_type']			=$business_type;
				 $data_details['default_login']			=$default_login;
				 $data_details['use_flag']				='W';
				 $data_details['create_time']			=$data_details['update_time']=$now_time;
				 SystemGroupApplyDetails::insert($data_details);
			 }

				if($id){
					$msg['code']=200;
					$msg['msg']='添加成功！';
					$msg['data']=(object)$data;
                    return $msg;
				}else{
					$msg['code']=303;
					$msg['msg']='添加失败！';
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
     * 商户申请的详情    /user/apply_detail
     */
    public function apply_detail(Request $request){
        $apply_id		=$request->input('apply_id');
//        $apply_id='group_202101190956526391943702';

		$details_where=[
			['apply_id','=',$apply_id],
			['delete_flag','=','Y'],
		];
        $select=['group_name','business_type','default_login','create_time','name','leader_phone','auditor_cause','use_flag'];
		$details_info=SystemGroupApplyDetails::where($details_where)->orderBy('create_time','desc')
			->select($select)
			->get();
		if($details_info){
			$msg['code']=200;
			$msg['msg']='数据拉取成功！';
			$msg['data']=$details_info;
            return $msg;
		}else{
			$msg['code']=300;
			$msg['msg']='没有数据！';
            return $msg;
		}

    }



}
?>

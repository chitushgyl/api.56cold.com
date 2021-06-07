<?php
namespace App\Http\Api\Tms;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Tools\Import;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\FileController as File;
use App\Http\Controllers\DetailsController as Details;
use App\Models\Tms\TmsGroup;
use App\Models\Group\SystemGroup;
use App\Models\Tms\TmsContacts;


class ContactsController extends Controller{

    /**
    ** 联系人列表      /api/contacts/contactsPage
    */
    public function contactsPage(Request $request){
        /** 接收中间件参数**/
        $project_type       =$request->get('project_type');
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        /**接收数据*/
        $num            = $request->input('num')??10;
        $page           = $request->input('page')??1;

        $listrows       = $num;
        $firstrow       = ($page-1)*$listrows;
        $search = [];
        switch ($project_type){
            case 'user':
                $search = [
                    ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'total_user_id','value'=>$user_info->total_user_id],
                ];

                break;
            case 'company':
                $search = [
                    ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'company_id','value'=>$user_info->userIdentity->company_id],
                ];
                break;
        }

        $where  = get_list_where($search);
        $select = ['self_id','create_user_name','contacts','tel','use_flag'];
        $data   = TmsContacts::where($where)
            ->offset($firstrow)
            ->orderBy('create_time', 'desc')
            ->limit($listrows)
            ->select($select)
            ->get();

        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }

    /*
    **联系人创建      /api/contacts/createContacts
    */
    public function createContacts(Request $request){
        /** 接收数据*/
        $self_id = $request->input('self_id');
        // $self_id = 'contacts_202101111753552307743620';
        $where   = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id]
        ];
        $data['info'] = TmsContacts::where($where)->first();
        $msg['code']  = 200;
        $msg['msg']   = "数据拉取成功";
        $msg['data']  = $data;
        return $msg;
    }

    /*
    **    联系人添加进入数据库      /api/contacts/addContacts
    */
    public function addContacts(Request $request){
        $project_type       =$request->get('project_type');
        $now_time       = date('Y-m-d H:i:s',time());
        $table_name     = 'tms_contacts';
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $total_user_id = $user_info->total_user_id;
//        $token_name    = $user_info->token_name;
        $input         = $request->all();
        /** 接收数据*/
        $self_id       = $request->input('self_id');
        $contacts      = $request->input('contacts');//联系人姓名
        $tel           = $request->input('tel');//联系人电话
//        DUMP($project_type);
       // Dd($user_info->toArray());
        /*** 虚拟数据
        $input['self_id']  = $self_id='';
        $input['contacts'] = $contacts='pull222222';
        $input['tel']      = $tel='123456748';
         ***/
        $rules = [
            'contacts'=>'required',
            'tel'=>'required',
        ];
        $message = [
            'contacts.required'=>'联系人名称不能为空',
            'tel.required'     =>'联系人电话不能为空',
        ];
        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {

            $data['contacts']  = $contacts;
            $data['tel']       = $tel;

            $wheres['self_id'] = $self_id;
            $old_info = TmsContacts::where($wheres)->first();
            switch ($project_type){
                case 'user':

                    break;
                case 'company':
                    $data['company_id']     = $user_info->userIdentity->company_id;
                    $data['company_name']     =$user_info->userIdentity->company_id;
                    $data['group_code']     = $user_info->userIdentity->group_code;
                    $data['group_name']     =$user_info->userIdentity->group_name;
                    break;
            }
            if($old_info) {
                $data['update_time'] = $now_time;
                $id = TmsContacts::where($wheres)->update($data);
            } else {
                $data['self_id']            = generate_id('contacts_');		//联系人ID
                $data['total_user_id']     = $total_user_id;
//                $data['create_user_id']     = $total_user_id;
//                $data['create_user_name']   = $token_name;
                $data['create_time']        = $data['update_time'] = $now_time;
                $id = TmsContacts::insert($data);
            }

            if($id) {
                $msg['code'] = 200;
                $msg['msg']  = "操作成功";
                $msg['data'] = $data;
                return $msg;
            } else {
                $msg['code'] = 302;
                $msg['msg']  = "操作失败";
                return $msg;
            }
        } else {
            //前端用户验证没有通过
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg']  = null;
            foreach ($erro as $k => $v) {
                $kk = $k+1;
                $msg['msg'] .= $kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }

    /***    联系人启用禁用      /api/contacts/contactsUseFlag
     */
    public function contactsUseFlag(Request $request,Status $status){
        $now_time   = date('Y-m-d H:i:s',time());
        $table_name = 'tms_contacts';
        $medol_name = 'TmsContacts';
        $self_id    = $request->input('self_id');
        $flag       = 'useFlag';
        // $self_id='contacts_202101131109315158207613';

        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);

        $msg['code'] = $status_info['code'];
        $msg['msg']  = $status_info['msg'];
        $msg['data'] = $status_info['new_info'];
        return $msg;
    }

    /*
    **    联系人删除      /api/contacts/contactsDelFlag
    */
    public function contactsDelFlag(Request $request,Status $status){
        $now_time   = date('Y-m-d H:i:s',time());
        $table_name = 'tms_contacts';
        $medol_name ='TmsContacts';
        $self_id    = $request->input('self_id');
        $flag = 'delFlag';
        // $self_id='contacts_202101131109315158207613';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);

        $msg['code'] = $status_info['code'];
        $msg['msg']  = $status_info['msg'];
        $msg['data'] = $status_info['new_info'];
        return $msg;
    }

    /***    联系人详情     /api/contacts/details
     */
    public function  details(Request $request,Details $details){
        $self_id = $request->input('self_id');
        $table_name = 'tms_contacts';
        $select = ['self_id','group_name','use_flag','create_user_name','create_time',
            'company_name','contacts','tel'];
        // $self_id = 'contacts_202101131109315158207613';
        $info = $details->details($self_id,$table_name,$select);

        if($info){
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

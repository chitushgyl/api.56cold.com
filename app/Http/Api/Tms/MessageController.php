<?php
namespace App\Http\Api\Tms;


use App\Http\Controllers\Controller;
use App\Models\Tms\TmsMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\StatusController as Status;

class MessageController extends Controller {

    /**
     * 滚动消息列表 /tms/message/messagePage
     * */
    public function messagePage(Request $request){
        /** 接收中间件参数**/
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $project_type       =$request->get('project_type');
        $total_user_id = $user_info->total_user_id;
        $tms_order_type           =array_column(config('tms.tms_order_type'),'name','key');
        /**接收数据*/
        $num      = $request->input('num')??10;
        $page     = $request->input('page')??1;
        $type     = $request->input('type');
        $listrows = $num;
        $firstrow = ($page-1)*$listrows;

        $search = [
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'type','value'=>$type],
        ];

        $where = get_list_where($search);
        $select = ['self_id','content','use_flag','delete_flag','create_time','update_time','type','sort','group_code','group_name'];
        $data['info'] = TmsMessage::where($where)
            ->offset($firstrow)
            ->limit($listrows)
            ->orderBy('sort', 'desc')
            ->select($select)
            ->get();

        foreach ($data['info'] as $k=>$v) {
            $v->type_show = $tms_order_type[$v->type]?? null;
        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;

        return $msg;
    }






}

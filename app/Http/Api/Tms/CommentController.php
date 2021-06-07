<?php
namespace App\Http\Api\Tms;
use App\Models\SysAddress;
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
use App\Models\Tms\TmsComment;
use App\Models\Tms\TmsOrder;

class CommentController extends Controller{

    /*
    **    评论添加进入数据库      /api/comment/addComment
    */
    public function addContacts(Request $request){
        $now_time      = date('Y-m-d H:i:s',time());
        $table_name    = 'tms_comment';
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $total_user_id = $user_info->total_user_id;
        $token_name    = $user_info->token_name;
        $input         = $request->all();
        /** 接收数据*/
        $order_id      = $request->input('order_id');//订单self_id
        $anonymous     = $request->input('anonymous') ?? 1;//是否匿名，1：是，2：否
        $score         = $request->input('score');//评分
        $contact       = $request->input('contact');//评价
        /*** 虚拟数据***/
        $order_id      = 'order_202101131734531443640277';
        $anonymous     = 1;
        $score         = 3;
        $contact       = '评价内容';

        $rules = [
            'order_id'=>'required',
            'score'=>'required',
            'contact'=>'required',
        ];
        $message = [
            'order_id.required'=>'数据错误、请重试',
            'score.required'=>'评分不能为空',
            'contact.required'=>'评价不能为空',
        ];
        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {

            $data['order_id']  = $order_id;
            $data['score']     = $score;
            $data['contact']   = $contact;

            $wheres['self_id'] = $order_id;
            $order_info = TmsOrder::where($wheres)->first();
            $order_type = !empty($order_info) ? $order_info->order_type : '';
            $group_code = !empty($order_info) ? $order_info->group_code : '';
            $group_name = !empty($order_info) ? $order_info->group_name : '';
            $create_user_id   = !empty($order_info) ? $order_info->create_user_id : '';
            $create_user_name = !empty($order_info) ? $order_info->create_user_name : '';

            if($old_info) {
                $data['update_time'] = $now_time;
                $id = TmsContacts::where($wheres)->update($data);
            } else {
                $data['self_id']            = generate_id('contacts_');		//联系人ID
                $data['create_user_id']     = $total_user_id;
                $data['create_user_name']   = $token_name;
                $data['create_time']        = $data['update_time'] = $now_time;
                $id = TmsContacts::insert($data);
            }

            if($id) {
                $msg['code'] = 200;
                $msg['msg']  = "操作成功";
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
    /*
       **    评论添加进入数据库      /api/comment/getWord
       */

    public function getWord(){
        $select= ['name','id'];
        $where1 = [
            ['xx','=','N'],
        ];
//        $where1 = [
//            ['id','=','520'],
//        ];
            $address = SysAddress::where($where1)->select($select)->limit(1000)->get();
            //dd($address->toArray());
            foreach ($address as $key =>$value){
                $where = [
                   ['id','=',$value['id']],

                ];
                $word = get_word($value['name']);

                //获取单个汉字拼音首字母。注意:此处不要纠结。汉字拼音是没有以U和V开头的
                dump($word);
                $data['first_word'] = $word;
                $data['xx'] = 'Y';
                SysAddress::where($where)->update($data);
            }

    }
}
?>

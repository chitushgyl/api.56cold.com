<?php
namespace App\Http\Api\Tms;

use App\Http\Controllers\CommonController;
use App\Models\Tms\TmsDiscuss;
use App\Models\Tms\TmsOrder;
use Illuminate\Http\Request;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;
use Illuminate\Support\Facades\Validator;

class DiscussController extends CommonController{
    /**
     * 评论列表
     * */
    public function discussPage(Request $request){
        /** 接收中间件参数**/
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $project_type       =$request->get('project_type');
        $button_info =     $request->get('buttonInfo');
        $tms_order_type        = array_column(config('tms.tms_order_type'),'name','key');
//        $total_user_id = $user_info->total_user_id;
        /**接收数据*/
        $num           = $request->input('num')??10;
        $page          = $request->input('page')??1;
        $type          = $request->input('type');
        $line_id       = $request->input('line_id');
        $total_user_id       = $request->input('total_user_id');
        $listrows      = $num;
        $firstrow      = ($page-1)*$listrows;
        $search = [];

        $search = [
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'carriage_id','value'=>$total_user_id],
            ['type'=>'=','name'=>'type','value'=>$type],
            ['type'=>'=','name'=>'line_id','value'=>$line_id],

        ];

        $where=get_list_where($search);

        $select=['self_id','order_id','type','content','line_id','anonymous','score','follow_discuss','follow_flag','images','delete_flag','create_time','on_time',
            'total_user_id','group_name','group_code','neat','fast','condition','temperture','car_smell','carriage_id'];
        $select1 = ['self_id','gather_sheng_name','gather_shi_name','gather_qu_name','send_sheng_name','send_shi_name','send_qu_name'];
        $select2 = ['self_id','shift_number','gather_sheng_name','gather_shi_name','gather_qu_name','send_sheng_name','send_shi_name','send_qu_name'];
        $data['info'] = TmsDiscuss::with(['TmsOrder' => function($query) use($select1){
            $query->select($select1);
        }])
            ->with(['TmsLine' => function($query) use($select2){
                $query->select($select2);
            }])
            ->where($where)
            ->offset($firstrow)
            ->limit($listrows)
            ->orderBy('create_time', 'desc')
            ->select($select)
            ->get();
        $data['total'] = TmsDiscuss::where($where)->count();
        foreach ($data['info'] as $k => $v){
            $v->order_type_show   = $tms_order_type[$v->order_type] ?? null;
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
        $select = ['self_id','order_id','type','content','line_id','anonymous','score','follow_discuss','follow_flag','images','delete_flag','create_time','on_time',
            'total_user_id','group_name','group_code','neat','fast','condition','temperture','car_smell'];
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
        $type                  = $request->input('type'); //评论类型：整车vehicle  线路line
        $content               = $request->input('content'); //评论内容
        $line_id               = $request->input('line_id');//线路ID
        $anonymous             = $request->input('anonymous');// 是否匿名 是'Y' 否 'N'
        $score                 = $request->input('score');//评分/星级
        $follow_discuss        = $request->input('follow_discuss');//追评内容
        $follow_flag           = $request->input('follow_flag');//是否追评
        $images                = $request->input('images');//图片
        $total_user_id         = $request->input('total_user_id');
        $on_time               = $request->input('on_time');//时效准时
        $neat                  = $request->input('neat');//车内整洁
        $fast                  = $request->input('fast');//快速高效
        $condition             = $request->input('condition');//货品完好
        $temperture            = $request->input('temperture');//温度达标
        $car_smell             = $request->input('car_smell');//车内无异味
        $carriage_id           = $request->input('carriage_id');//承运人ID

        /*** 虚拟数据
         $input['self_id']           =$self_id       ='';
         $input['order_id']          =$order_id      ='order_';
         $input['type']              =$type          ='vehicle';
         $input['content']           =$content       ='一点不专业';
         $input['line_id']           =$line_id       ='';
         $input['anonymous']         =$anonymous     ='Y';
         $input['score']             =$score         ='2';
         $input['follow_discuss']    =$follow_discuss='';
         $input['follow_flag']       =$follow_flag   ='N';
         $input['images']            =$images        ='';
         $input['total_user_id']     =$total_user_id ='user_';
         $input['on_time']           =$on_time       ='N';
         $input['neat']              =$neat          ='N';
         $input['fast']              =$fast          ='N';
         $input['condition']         =$condition     ='N';
         $input['temperture']        =$temperture    ='N';
         $input['car_smell']         =$car_smell     ='N';
         $input['carriage_id']       =$carriage_id   ='N';

         **/
         $rules = [
//             'company_title'=>'required',
         ];
         $message = [
//             'company_title.required'=>'请填写公司抬头',
         ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $data['order_id']            = $order_id;
            $data['type']                = $type;
            $data['content']             = $content;
            $data['line_id']             = $line_id;
            $data['anonymous']           = $anonymous;
            $data['score']               = $score;
            $data['follow_discuss']      = $follow_discuss;
            $data['follow_flag']         = $follow_flag;
            $data['images']              = $images;
            $data['total_user_id']       = $user_info->total_user_id;
            $data['on_time']             = $on_time;
            $data['neat']                = $neat;
            $data['fast']                = $fast;
            $data['condition']           = $condition;
            $data['temperture']          = $temperture;
            $data['car_smell']           = $car_smell;
            $data['carriage_id']         = $carriage_id;

            $wheres['self_id'] = $self_id;
            $old_info = TmsDiscuss::where($wheres)->first();

            if($old_info){
                $data['update_time'] = $now_time;
                if ($follow_flag == 'Y'){
                    $update['update_time'] = $now_time;
                    $update['follow_discuss'] = 'Y';
                    $order_info = TmsOrder::where('self_id',$order_id)->update($update);
                }

                $id = TmsDiscuss::where($wheres)->update($data);

            }else{
                $data['self_id']          = generate_id('view_');
                $data['total_user_id']    = $total_user_id;
                $data['create_time']      = $data['update_time'] = $now_time;
                $id = TmsDiscuss::insert($data);
                $update['discuss_flag'] ='Y';
                $order_info = TmsOrder::where('self_id',$order_id)->update($update);
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
     *删除评论  /api/discuss/delFlag
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

    /**
     * 评论详情 /api/discuss/discussDetails
     * */
    public function discussDetails(Request $request,Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'tms_discuss';
        $select     = ['self_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel',
            'total_user_id','group_code','delete_flag','create_time','default_flag','special_use','license'
        ];
        // $self_id='address_202101111755143321983342';
//        $info = $details->details($self_id,$table_name,$select);
        $select=['self_id','order_id','type','content','line_id','anonymous','score','follow_discuss','follow_flag','images','delete_flag','create_time','on_time',
            'total_user_id','group_name','group_code','neat','fast','condition','temperture','car_smell','carriage_id'];
        $select1 = ['self_id','gather_sheng_name','gather_shi_name','gather_qu_name','send_sheng_name','send_shi_name','send_qu_name'];
        $select2 = ['self_id','shift_number','gather_sheng_name','gather_shi_name','gather_qu_name','send_sheng_name','send_shi_name','send_qu_name'];
        $info = TmsDiscuss::with(['TmsOrder' => function($query) use($select1){
            $query->select($select1);
        }])
            ->with(['TmsLine' => function($query) use($select2){
                $query->select($select2);
            }])
            ->where('self_id',$self_id)
            ->select($select)
            ->first();
        if($info) {
            /** 如果需要对数据进行处理，请自行在下面对 $info 进行处理工作*/
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

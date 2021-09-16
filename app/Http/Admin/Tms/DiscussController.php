<?php
namespace App\Http\Admin\Tms;

use App\Http\Controllers\CommonController;
use App\Models\Tms\TmsDiscuss;
use App\Models\Tms\TmsOrder;
use Illuminate\Http\Request;
use App\Http\Controllers\StatusController as Status;
use Illuminate\Support\Facades\Validator;

class DiscussController extends CommonController{
    /**
     * 评论列表头部 /tms/discuss/discussList
     * */
    public function DiscussList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     * 评论列表 /tms/discuss/discussPage
     * */
    public function DiscussPage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数
        $user_info      = $request->get('user_info');//接收中间件产生的参数

        /**接收数据*/
        $num            =$request->input('num')??10;
        $page           =$request->input('page')??1;
        $use_flag       =$request->input('use_flag');
        $group_code     =$request->input('group_code');
        $type          = $request->input('type');
        $line_id       = $request->input('line_id');
        $score         = $request->input('score'); //H 好评  M 中评 L 差评
        $images        = $request->input('images');//筛选有图片的评论 有 Y
        $follow_discuss= $request->input('follow_discuss');//筛选有追评的评论 有 Y
        $listrows       =$num;
        $firstrow       =($page-1)*$listrows;

        $search=[
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'carriage_id','value'=>$group_code],
            ['type'=>'=','name'=>'type','value'=>$type],
            ['type'=>'=','name'=>'line_id','value'=>$line_id],
        ];

        $where=get_list_where($search);

        $select=['self_id','order_id','type','content','line_id','anonymous','score','follow_discuss','follow_flag','images','delete_flag','create_time','on_time',
            'total_user_id','group_name','group_code','neat','fast','condition','temperture','car_smell','carriage_id'];
        $select1 = ['self_id','gather_sheng_name','gather_shi_name','gather_qu_name','send_sheng_name','send_shi_name','send_qu_name'];
        $select2 = ['self_id','shift_number','gather_sheng_name','gather_shi_name','gather_qu_name','send_sheng_name','send_shi_name','send_qu_name'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsDiscuss::where($where)->count(); //总的数据量
                $data['items']=TmsDiscuss::with(['TmsOrder' => function($query) use($select1){
                    $query->select($select1);
                }])
                    ->with(['TmsLine' => function($query) use($select2){
                        $query->select($select2);
                    }]);
                if ($score == 'H'){
                    $data['info'] = $data['info']->where('score','>=',3);
                }
                if ($images == 'Y'){
                    $data['info'] = $data['info']->where('images','!=',null);
                }
                if ($follow_discuss == 'Y'){
                    $data['info'] = $data['info']->where('follow_discuss','=','Y');
                }
                $data['items'] = $data['items']
                    ->where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;

            case 'one':
                if ($type == 'line'){

                }else{
                    $where[]=['group_code','=',$group_info['group_code']];
                }
                $data['total']=TmsDiscuss::where($where)->count(); //总的数据量
                $data['items']=TmsDiscuss::with(['TmsOrder' => function($query) use($select1){
                    $query->select($select1);
                }])
                    ->with(['TmsLine' => function($query) use($select2){
                        $query->select($select2);
                    }]);
                if ($score == 'H'){
                    $data['info'] = $data['info']->where('score','>=',3);
                }
                if ($images == 'Y'){
                    $data['info'] = $data['info']->where('images','!=',null);
                }
                if ($follow_discuss == 'Y'){
                    $data['info'] = $data['info']->where('follow_discuss','=','Y');
                }
                $data['items'] = $data['items']
                    ->where($where)
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsDiscuss::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['items']=TmsDiscuss::with(['TmsOrder' => function($query) use($select1){
                    $query->select($select1);
                }])
                    ->with(['TmsLine' => function($query) use($select2){
                        $query->select($select2);
                    }]);
                if ($score == 'H'){
                    $data['info'] = $data['info']->where('score','>=',3);
                }
                if ($images == 'Y'){
                    $data['info'] = $data['info']->where('images','!=',null);
                }
                if ($follow_discuss == 'Y'){
                    $data['info'] = $data['info']->where('follow_discuss','=','Y');
                }
                $data['items'] = $data['items']
                    ->where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)->limit($listrows)->orderBy('create_time', 'desc')
                    ->select($select)->get();
                $data['group_show']='Y';
                break;
        }
        // dd($data['items']->toArray());
        $data['score'] = round(TmsDiscuss::where($where)->avg('score'),1);
        $data['neat'] = TmsDiscuss::where($where)->where('neat','Y')->count();
        $data['fast'] = TmsDiscuss::where($where)->where('fast','Y')->count();
        $data['condition'] = TmsDiscuss::where($where)->where('condition','Y')->count();
        $data['temperture'] = TmsDiscuss::where($where)->where('temperture','Y')->count();
        $data['car_smell'] = TmsDiscuss::where($where)->where('car_smell','Y')->count();
        $data['on_time'] = TmsDiscuss::where($where)->where('on_time','Y')->count();
        foreach ($data['items'] as $k=>$v) {
            $v->images   = img_for($v->images,'more');

        }

        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        //dd($msg);
        return $msg;
    }

    /**
     * 添加编辑获取数据 /tms/discuss/createDiscuss
     * */
    public function createDiscuss(Request $request){
        /** 接收数据*/
        $self_id = $request->input('self_id');
//        $self_id = 'car_20210313180835367958101';

        $where = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select=['self_id','order_id','type','content','line_id','anonymous','score','follow_discuss','follow_flag','images','delete_flag','create_time','on_time',
            'total_user_id','group_name','group_code','neat','fast','condition','temperture','car_smell','carriage_id'];
        $data['info'] = TmsBill::where($where)->select($select)->first();
        if ($data['info']){

        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }

    /**
     * 添加/编辑  /tms/discuss/addDiscuss
     * */
    public function addDiscuss(Request $request){
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='tms_group';

        $operationing->access_cause     ='创建/修改评价';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;

        $user_info = $request->get('user_info');//接收中间件产生的参数
        $input              =$request->all();
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
        $group_code            = $request->input('group_code');
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
         ***/
//        dd($input);

        $rules = [
//            'company_title'=>'required',
        ];
        $message = [
//            'company_title.required'=>'请填写公司抬头',
        ];

        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()){
            $data['order_id']            = $order_id;
            $data['type']                = $type;
            $data['content']             = $content;
            $data['line_id']             = $line_id;
            $data['anonymous']           = $anonymous;
            $data['score']               = $score;
            $data['follow_discuss']      = $follow_discuss;
            $data['follow_flag']         = $follow_flag;
            $data['images']              = img_for($images,'in');
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
                $data['repeat_flag'] = 'Y';
                $id = TmsDiscuss::where($wheres)->update($data);
                if ($follow_flag == 'Y'){
                    $update['update_time'] = $now_time;
                    $update['follow_discuss'] = 'Y';
                    $order_info = TmsOrder::where('self_id',$order_id)->update($update);
                }
                $operationing->access_cause='修改评价信息';
                $operationing->operation_type='update';
            }else{
                $data['self_id']          = generate_id('view_');
                $data['group_code']       = $group_code;
                $data['create_time']      = $data['update_time'] = $now_time;
                $id = TmsDiscuss::insert($data);
                $update['discuss_flag'] ='Y';
                $order_info = TmsOrder::where('self_id',$order_id)->update($update);
                $operationing->access_cause='新增评价';
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
     * 删除评论  /tms/discuss/delFlag
     * */
    public function delFlag(Request $request,Status $status){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name='tms_discuss';
        $medol_name='TmsDiscuss';
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
     * 常用发票详情 /tms/discuss/discussDetails
     * */
    public function discussDetails(Request $request, Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'tms_discuss';
        $select     = ['self_id','type','company_title','company_tax_number','bank_name','bank_num','company_address','company_tel',
            'total_user_id','group_code','delete_flag','create_time','default_flag','special_use','license'
        ];
        // $self_id='address_202101111755143321983342';
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
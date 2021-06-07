<?php
namespace App\Http\Api\Home;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Shop\HomeConfig;
use App\Models\SysFoot;

class HomeController extends Controller{
    /**
     * 首页模板接口      /home/index
     * 前端传递必须参数：group_code：门店的标识符，默认为1234
     *
     * 回调结果：200  数据拉取成功1
     *
     *回调数据：  首页数据信息1
     */
    public function index(Request $request){
        $project_type         =$request->get('project_type');
        $user_info          =$request->get('user_info');
        $group_info         =$request->get('group_info');
        $now_time           =date('Y-m-d H:i:s',time());
        $group_code         =$group_info->group_code??config('page.platform.group_code');
        $index_where=[
            ['group_code','=',$group_code],
            ['use_flag','=','Y'],
            ['delete_flag','=','Y'],
            ['start_time','<',$now_time],
            ['end_time','>',$now_time],
        ];
        $select=['type','self_id','ground_flag','ground_info'];
        $selectList=['self_id','config_id','jump_type','data_value','url','name'];
        $home_config=HomeConfig::with(['homeConfigData' => function($query)use($selectList) {
            $query->where('delete_flag','=','Y');
            $query->select($selectList);
            $query->orderBy('sort', 'asc');
        }])->where($index_where)
            ->orderBy('sort','asc')
            ->select($select)
            ->get();


        if($home_config->count()  > 0){
            foreach ($home_config as $k => $v){
                if ($v->ground_flag =="img") {
                    $v->ground_info=img_for($v->ground_info,'one');
                }
                foreach ($v->homeConfigData as $kk => $vv){
                    $vv->url=img_for($vv->url,'more');
                }
            }
        }else{
            $home_config_info[0]['type']='home_menu';
            $home_config_info[0]['self_id']='home_menu';
            $home_config_info[0]['ground_flag']='no';
            $home_config_info[0]['ground_info']=null;
            $home_config_info[0]['home_config_data'][0]['self_id']='home_menu';
            $home_config_info[0]['home_config_data'][0]['config_id']='home_menu';
            $home_config_info[0]['home_config_data'][0]['name']='home_menu';
            $home_config_info[0]['home_config_data'][0]=(object)$home_config_info[0]['home_config_data'][0];

            $home_config[0]=(object)$home_config_info[0];
        }

        $pv['user_id']=$user_info?$user_info->total_user_id:null;
        $pv['browse_path']=$request->path();
        $pv['level']=null;
        $pv['table_id']=null;
        $pv['ip']=$request->getClientIp();
        $pv['place']='CT_H5';
//      pvuv($pv);
        //dd($groupCodes);
        //dd($group_info)
        $msg['code']=200;
        $msg['msg']='数据拉取成功！';
        $msg['data']=$home_config;
        //dd($msg);
        return $msg;
    }






}
?>

<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 2020/7/29
 * Time: 15:42
 */
namespace App\Http\Api\School;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\School\SchoolPath;
use App\Models\School\SchoolBasics;
use App\Models\School\SchoolHardware;
use App\Http\Controllers\RedisController as RedisServer;
use App\Http\Api\School\MeansController as MeansServer;
class FacilityController extends Controller{
    private $prefixCar='car_';

    /**
     * 发车
     * pathUrl =>  /school/govern
     * @param Request $request
     * @param RedisServer $redisServer
     * @param MeansController $meansServer
     * @return mixed
     */
    public function govern(Request $request,RedisServer $redisServer,MeansServer $meansServer){
        /** 接收数据*/
        $mac_address		=$request->input('mac_address');
        $input				=$request->all();

        //虚拟数据
        //$input['mac_address']       =$mac_address   ='22120010341';

        $rules = [
            'mac_address' => 'required',
        ];
        $message = [
            'mac_address.required' => '设备id不能为空',
        ];
        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()){
            $datass         =date_time();
            /** 从redis中拉取数据，如果有，则判断是不是2，如果是2，则还是他，如果没有则拉取第一条数据进行发车 **/
            $carriage_id		=$redisServer->get($mac_address,'mac_path');
            //如果没有则是发车
            //如果有则需要判断目前是线路是什么状态
            if($carriage_id){
                $carr		=$redisServer->get($carriage_id,'carriage');
                $carriageInfo=json_decode($carr);
                if($carriageInfo){
                    //dd($carriageInfo);
                    //判断当前的车是什么状态
                    //如果是1则是发当前的车辆 如果3则是发的下一趟车 如果是2不做处理
                    switch ($carriageInfo->carriage_status){
                        case '3':
                            $where_path=[
                                ['site_type','=',$datass['status']],
                                ['default_car_id','=',$carriageInfo->default_car_id],
                                ['delete_flag','=','Y'],
                                ['sort','>',$carriageInfo->sort],
                                ['use_flag','=','Y']
                            ];
                            $schoolPath=SchoolPath::where($where_path)->orderBy('sort','asc')->value('self_id');
                            if($schoolPath){
                                /**  如果你的第一个车有的话，还要去数据库看看，如果有，则别让他操作任何东西了！！！！！！！**/
                                $carriage_id2   =$this->prefixCar.$schoolPath.$datass['dateStatus'];
                                /***   这里要做修改，修改成从缓存中拿数据，如果拿到的数据，是2或者3，则不发车了，没必要从数据库中拿数据，
                                 * 注意：！！！！！！！！   缓存在发车过程中不可以清0
                                 **
                                 */
                                //如果有这个车，则执行发车逻辑即可
                                $path_info=$meansServer->getPathInfo($schoolPath);
                              //  dd($path_info->toArray());
                                //如果有数据则执行
                                if($path_info->count()>0 &&  $path_info->schoolPathway->count()>0){
                                    $dispatchCarStatus=1;         //初始化为1未发车
                                    //获取未发车的数据
                                    $carriageInfo2=$meansServer->carriageInfo($path_info,$carriage_id2,$dispatchCarStatus,$datass,$mac_address);
                                    /*** 因为这里是mac是空的，所以可以直接执行发车逻辑**/
                                    //如果再里面就发车
                                    $meansServer->saveInfo($carriageInfo2,$redisServer,$mac_address);
                                }
                            }
                            break;
                        case '1':
                            //直接发车
                            $meansServer->saveInfo($carriageInfo,$redisServer,$mac_address);
                            break;
                    }
                }
            }else{
                //如果是空的，则说明是初始化的状态，则需要根据UP，DOWM去拿第一条线路
                //第一步，我们需要把 设备号查询出来关联的那个车辆			school_hardware
                $where=[
                    ['mac_address','=',$mac_address],
                    ['delete_flag','=','Y']
                ];
                $mac_info=SchoolHardware::where($where)->select('mac_address','car_id','use_group_code as group_code')->first();
                if($mac_info){
                    $fache_flag='Y';
                    $where_basics=[
                        ['group_code','=',$mac_info->group_code],
                        ['delete_flag','=','Y']
                    ];
                    $basics=SchoolBasics::where($where_basics)->select('skip_flag','auto_depart_flag','group_code')->first();
                    if($basics){
                        $fache_flag		=$basics->fache_flag??$fache_flag;
                    }

                    //第三步，去拿取这个时间段的车辆
                    if($fache_flag == 'Y'){
                        //去查询第一个线路数据
                        $where_path=[
                            ['site_type','=',$datass['status']],
                            ['default_car_id','=',$mac_info->car_id],
                            ['delete_flag','=','Y'],
                            ['use_flag','=','Y']
                        ];
                        $schoolPath=SchoolPath::where($where_path)->orderBy('sort','asc')->value('self_id');

                        if($schoolPath){
                            /**  如果你的第一个车有的话，还要去数据库看看，如果有，则别让他操作任何东西了！！！！！！！**/
                            $carriage_id2   =$this->prefixCar.$schoolPath.$datass['dateStatus'];
                            /***   这里要做修改，修改成从缓存中拿数据，如果拿到的数据，是2或者3，则不发车了，没必要从数据库中拿数据，
                             * 注意：！！！！！！！！   缓存在发车过程中不可以清0
                             **
                             */
                            //如果有这个车，则执行发车逻辑即可
                            $path_info=$meansServer->getPathInfo($schoolPath);
                            //如果有数据则执行
                            if($path_info->count()>0 &&  $path_info->schoolPathway->count()>0){
                                $dispatchCarStatus=1;         //初始化为1未发车
                                //获取未发车的数据
                                $carriageInfo2=$meansServer->carriageInfo($path_info,$carriage_id2,$dispatchCarStatus,$datass,$mac_address);
                                /*** 因为这里是mac是空的，所以可以直接执行发车逻辑**/
                                //如果再里面就发车
                                $meansServer->saveInfo($carriageInfo2,$redisServer,$mac_address);
                            }
                        }
                    }
                }
            }
        }
    }
}

<?php
namespace Home\Controller;
use Common\Controller\CommonController;
class SafeController extends HomeController {
 	public function _initialize(){
 		parent::_initialize();
 	}
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	public function index(){
        $u_info = M('Member')->where("member_id = {$_SESSION['USER_KEY_ID']}")->find();
        //大盘数据
        $pan = ['total'=>0,'send'=>0];
        $baodan = M('Baodan');
        if($u_info['user_id']){
        	$total = 0;
        	$send = 0;
        	$list = $baodan->field('integral,remain_days')->where('user_id='.$u_info['user_id'])->select();
        	if($list){
        		foreach ($list as $v) {
        			$total += $v['integral'];
        			$send += $v['integral']*(200-$v['remain_days'])*0.005;
        		}
        		$pan = ['total'=>$total,'send'=>$send];
        	}
        }
        //alps币
        $alps = M('currency_user')->where(['member_id'=>$_SESSION['USER_KEY_ID'],'currency_id'=>30])->find();

        //外汇出币统计
        //$dollar = M('alps_log')->where(['member_id'=>$_SESSION['USER_KEY_ID'],'platform'=>'waihui','status'=>1,'type'=>1])->sum('money');


        $this->assign('alps_info',$alps);
        $this->assign('pan',$pan);
        $this->assign('u_info',$u_info);
		$this->assign('empty','暂无数据');
        $this->display();
     }
	 public function mobilebind(){

		$this->assign('empty','暂无数据');
        $this->display();
     }
     function generate_code(){
        exit;//生成大盘编号
        $alps_code = M('Member')->where("member_id = {$_SESSION['USER_KEY_ID']}")->getField('alps_code');
        if($alps_code) $this->error('已存在编号');
        do{
            $alps_code = 'AL'.rand(100000,999999);
            $member_id = M('Member')->where(['alps_code'=>$alps_code])->getField('member_id');

        }while($member_id);
        M('Member')->where("member_id = {$_SESSION['USER_KEY_ID']}")->save(['alps_code'=>$alps_code]);
        $this->success('生成成功！');
     }

}
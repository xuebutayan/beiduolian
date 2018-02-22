<?php
namespace Common\Behavior;
class updateBehavior extends \Think\Behavior{
	public function run(&$param){
		//更新日增积分行为
		$update = F('daily_update');
		if($update){
			$now = time();
			$tomorrow = strtotime(date('Y-m-d',$update))+86400;
			if($now>$update && $now>$tomorrow){
				M('Member')->where(' member_id>0 ')->save(['daily_inc'=>0,'bao'=>0]);
				F('daily_update',$now);
				//积分任务
				F('mission',0);
			}
		}else{
			F('daily_update',time());
		}

	}
}
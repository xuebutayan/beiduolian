<?php
namespace Home\Controller;
use Common\Controller\CommonController;
class Mt4Controller extends HomeController {
 	public function _initialize(){
 		parent::_initialize();
 	}
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	public function index(){
		$mt4 = M('member')->where(['member_id'=>$_SESSION['USER_KEY_ID']])->getField('mt4');
		$this->assign('mt4',$mt4);
		$this->display();
	}
	function setMt4(){
		$post = I('post.');
		if(empty($post['mt4'])) $this->ajaxReturn(['status'=>0,'info'=>'帐号不能为空！']);
		$re = M('member')->where(['member_id'=>$_SESSION['USER_KEY_ID']])->setField('mt4',$post['mt4']);
		if($re) $this->ajaxReturn(['status'=>1,'info'=>'提交成功！']);
		else  $this->ajaxReturn(['status'=>0,'info'=>'提交失败！']);
	}
}
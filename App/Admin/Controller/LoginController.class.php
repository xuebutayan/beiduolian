<?php
namespace Admin\Controller;
use Common\Controller\CommonController;
class LoginController extends CommonController {
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	public function login(){
        $this->display();
    }
    //登录验证
    public function checkLogin(){
    	$username=trim(I('post.username'));
    	$pwd=trim(I('post.pwd'));
        //检查验证码
        $verify = new \Think\Verify();
        $code = trim(I('post.vcode'));
        if(!$verify->check($code)){
            $this->error('验证码错误！');
        }
     	if(empty($username)||empty($pwd)){
     		$this->error('请填写完整信息');
     	}
     	$admin=M('Admin')->where("username='$username'")->find();
     	if($admin['password']!=md5($pwd)){
     		$this->error('登录密码不正确');
     	}
        //添加登录日志
        $data = ['admin_id'=>$admin['admin_id'],'login_ip'=>get_client_ip(0,1),'logtime'=>time()];
        M('admin_log')->add($data);
    	$_SESSION['admin_userid']=$admin['admin_id'];
    	$this->redirect('Index/index');
    }

    //登出
    public function loginout(){
    	$_SESSION['admin_userid']=null;
    	$this->redirect('Login/login');
    }
    function code(){
        $Verify = new \Think\Verify(['fontttf'=>'1.ttf','length'=>3]);
        $Verify->entry();
    }

}
<?php
namespace Common\Model;
use Think\Model;

class BorrowModel extends Model{
	//array(验证字段1,验证规则,错误提示,[验证条件,附加规则,验证时间]),
	protected $_validate = array(
		['money','require','贷款金额不能为空！',self::MUST_VALIDATE],
		['money','ckMoney','贷款金额必须为100的倍数！',self::MUST_VALIDATE,'callback'],
		//交易密码
		['password','require','交易密码不能为空！',self::EXISTS_VALIDATE]
	);

	protected function ckMoney($money){
		if(($money%100)!==0) return false;
		else return true;
	}

}
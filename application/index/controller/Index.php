<?php
namespace app\index\controller;

class Index
{
    public function index()
    {
        echo '<style type="text/css">*{ padding: 0; margin: 0; } .think_default_text{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>HELLO!</h1><p>Welcome to Taiyuweishi!<br/><span style="font-size:30px">taiyuweishiali.cn</span></p>';
    }
	
	public function mp(){
		return 'mp';
	}
}

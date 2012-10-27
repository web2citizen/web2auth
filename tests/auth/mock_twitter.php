<?php

// web2authのtwitter_callbackメソッドテスト用のモック
// プロパティを規定値で返すだけ
class Twitter
{
	public $id = '100';
	public $screen_name = 'twitter_user';
	public $name = 'ツイッターユーザー';
	public $description = 'ツイッターユーザーのテストです';
	public $profile_image_url = 'http://k.yimg.jp/images/top/sp/logo.gif';
	
	public static function get($url)
	{
		return new Twitter();
	}
}

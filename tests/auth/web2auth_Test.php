<?php
/**
 * web2auth test
 *
 * @group App
 */

// twitter関連のテスト用にモックを読み込み
require_once 'mock_twitter.php';

class Test_Web2Auth extends DbTestCase
{
	protected $tables = array(
					// テーブル名 => YAMLファイル名
					'users' => 'authuser',
				);

	/**
	 * 未ログイン状態での権限テスト
	 * 
	 * @dataProvider role_check_no_login_data_provider
	 */
	public function test_role_check_no_login($input, $expected)
	{
		$test = Auth::instance()->role_check($input);
		$this->assertEquals($expected, $test);
	}
	
	public function role_check_no_login_data_provider()
	{
		return array(
				array(null, false),
				array(1, false),
				array(2, false),
				array(3, true),		// group=0の時はログインしていなくてもOK
			);
	}
	
	/**
	 * ログイン状態でのユーザへの権限テスト
	 * 
	 * @dataProvider role_check_data_provider
	 */
	public function test_role_check($input, $expected)
	{
		// セッションにログイン情報を格納
		\Session::set('login_id', $input['login_id']);
		\Session::set('login_hash', $input['login_hash']);
		$test = Auth::instance()->role_check($input['check_id']);
		$this->assertEquals($expected, $test);
	}
	public function role_check_data_provider()
	{
		return array(
			array(array(
				'login_id'   => null,	//ログインユーザ
				'check_id'   => null,	//更新対象ユーザ
				'login_hash' => null,	//ログインハッシュ
				), false),				//期待結果
			array(array(
				'login_id'   => 1,
				'check_id'   => null,
				'login_hash' => null,
				), false),
			array(array(
				'login_id'   => 1,
				'check_id'   => 1,
				'login_hash' => '0a3229bd2b421c7da598981044aa972fc8e55b1d',
				), true),
			array(array(	//ハッシュ異常チェック
				'login_id'   => 1,
				'check_id'   => 1,
				'login_hash' => 'hash_error',
				), false),
			array(array(
				'login_id'   => 1,
				'check_id'   => 2,
				'login_hash' => '0a3229bd2b421c7da598981044aa972fc8e55b1d',
				), false),
			array(array(	//group=0のユーザなので更新できる
				'login_id'   => 1,
				'check_id'   => 3,
				'login_hash' => '0a3229bd2b421c7da598981044aa972fc8e55b1d',
				), true),
		);
	}
	
	/**
	 * メールアドレス、パスワードの整合性テスト
	 * 
	 * @dataProvider login_user_data_provider
	 */
	public function test_validate_user($input, $expected)
	{
		$test = Auth::instance()->validate_user($input['email'], $input['password']);
		if($expected['id'])
		{
			$expected = \DB::select_array(\Config::get('web2auth.table_columns', array('*')))
					->where('id', '=', $expected['id'])
					->from(\Config::get('web2auth.table_name'))
					->execute(\Config::get('web2auth.db_connection'))->current();
		}
		else
		{
			$expected = false;
		}
		$this->assertEquals($expected, $test);
	}
	
	/**
	 * ログイン、湯0座情報取得周りのdata_provider
	 * 他のテストメソッドでも使いまわす
	 */
	public function login_user_data_provider()
	{
		return array(
			array(
				array(	//input
					'email'    => null,
					'password' => null,
				),
				array(	//expected
					'login'      => false,	//ログイン期待
					'login_hash' => 10,		//ハッシュ作成エラーコード
					'id'         => false,
					'group'      => false,
					'email'      => false,
					'username'   => false
				)
			),
			array(	//パスワード未入力
				array(
					'email'    => 'a@a.com',
					'password' => null,
				),
				array(
					'login'      => false,
					'login_hash' => 10,		//ハッシュ作成エラーコード
					'id'         => false,
					'group'      => false,
					'email'      => false,
					'username'   => false
				)
			),
			array(	//メールアドレス未入力
				array(
					'email'    => null,
					'password' => 'testtest',
				),
				array(
					'login'      => false,
					'login_hash' => 10,		//ハッシュ作成エラーコード
					'id'         => false,
					'group'      => false,
					'email'      => false,
					'username'   => false
				)
			),
			array(	//パスワード誤り
				array(
					'email'    => 'a@a.com',
					'password' => 'test',
				),
				array(
					'login'      => false,
					'login_hash' => 10,		//ハッシュ作成エラーコード
					'id'         => false,
					'group'      => false,
					'email'      => false,
					'username'   => false
				)
			),
			array(	//存在しないユーザ
				array(
					'email'    => 'z@z.com',
					'password' => 'testtest',
				),
				array(
					'login'      => false,
					'login_hash' => 10,		//ハッシュ作成エラーコード
					'id'         => false,
					'group'      => false,
					'email'      => false,
					'username'   => false
				)
			),
			array(	//一派ニューザでログイン
				array(
					'email'    => 'a@a.com',
					'password' => 'testtest',
				),
				array(
					'login'      => true,
					'login_hash' => '',
					'id'         => 1,
					'group'      => 1,
					'email'      => 'a@a.com',
					'username'   => 'テストユーザ１'
				)
			),
			array(	//グループ0ユーザでログイン
				array(
					'email'    => 'c@c.com',
					'password' => 'testtest',
				),
				array(
					'login'      => true,
					'login_hash' => '',
					'id'         => 3,
					'group'      => 0,
					'email'      => 'c@c.com',
					'username'   => 'グループ0ユーザ'
				)
			),
		);
	}
	
	/**
	 * ツイッターでのログインテスト
	 */
	public function test_callback_twitter()
	{
		// ツイッターからの情報で新規ユーザ作成
		$test = Auth::instance()->twitter_callback();
		$expected = true;
		$this->assertEquals($expected, $test);
		
		// 作成済みのユーザでのログイン
		$test = Auth::instance()->twitter_callback();
		$expected = true;
		$this->assertEquals($expected, $test);
	}
	
	/**
	 * ツイッターによるユーザ情報アップデートテスト
	 */
	public function test_update_twitter_user()
	{
		// ユーザ情報をtwitterで更新する
		$auth = Auth::instance();
		$auth->login('a@a.com', 'testtest');
		$test = $auth-> update_twitter_user(1);
		$expected = true;
		$this->assertEquals($expected, $test);
		
		// 既に登録済みのtwitterで別のユーザは更新できない
		$auth->logout();
		$auth->login('b@b.com', 'testtest');
		$test = $auth-> update_twitter_user(2);
		$expected = false;
		$this->assertEquals($expected, $test);
		
		// 登録済みでも自分は更新できる
		$auth->logout();
		$auth->login('a@a.com', 'testtest');
		$auth->update_user(array('username' => 'テストのため一時変更'), 1);
		$test = $auth-> update_twitter_user(1);
		$expected = true;
		$this->assertEquals($expected, $test);
	}
	
	
	/**
	 * ログインテスト
	 * 
	 * @dataProvider login_user_data_provider
	 */
	public function test_login($input, $expected)
	{
		$test = Auth::instance()->login($input['email'], $input['password']);
		$expected = $expected['login'];
		$this->assertEquals($expected, $test);
	}
	
	/**
	 * 強制ログインテスト
	 * 
	 * @dataProvider force_login_data_provider
	 */
	public function test_force_login($input, $expected)
	{
		$test = Auth::instance()->force_login($input);
		$this->assertEquals($expected, $test);
	}
	public function force_login_data_provider()
	{
		return array(
				array(null, false),
				array(1, true),
				array(4, false),	//存在しないユーザでは強制ログインもできない
			);
	}
	
	/**
	 * ログアウトテスト
	 */
	public function test_logout()
	{
		// セッションにログイン情報を格納
		\Session::set('login_id', 1);
		\Session::set('login_hash', '0a3229bd2b421c7da598981044aa972fc8e55b1d');
		$test = Auth::instance()->logout();
		$expected = true;
		
		// ログインアウト後にセッションが消えていることを確認
		if(\Session::get('login_id', null) !== null)
				$this->fail('login_idが残っています。');
		if(\Session::get('login_hash', null) !== null)
				$this->fail('login_hashが残っています。');
		if(\Session::get('login_name', null) !== null)
				$this->fail('login_nameが残っています。');
		
		$this->assertEquals($expected, $test);
	}
	
	/**
	 * ユーザ作成テスト
	 * 
	 * @dataProvider create_user_data_provider
	 */
	public function test_create_user($input, $expected)
	{
		try
		{
			$test = Auth::instance()->create_user($input['email'], $input['password']);
			$this->assertEquals($expected['id'], $test);
			return;
		}
		catch(Web2UserUpdateException $e)
		{
			if($e->getCode() === $expected['error']) return;
		}
		$this->fail('ユーザ作成時に失敗');
	}
	public function create_user_data_provider()
	{
		return array(
			array(array(
					'email'    => null,
					'password' => null,
				), array(
					'id'    => null,	//成功時のユーザID
					'error' => 1,		//エラーコード
				)),
			array(array(
					'email'    => 'x@x.com',
					'password' => null,
				), array(
					'id'    => null,
					'error' => 1,
				)),
			array(array(
					'email'    => null,
					'password' => 'testtest',
				), array(
					'id'    => null,
					'error' => 1,
				)),
			array(array(
					'email'    => 'a@a.com',
					'password' => 'testtest',
				), array(
					'id'    => null,
					'error' => 2,
				)),
			array(array(
					'email'    => 'x@x.com',
					'password' => 'testtest',
				), array(
					'id'    => 4,
					'error' => null,
				)),
		);
	}
	
	/**
	 * ユーザ情報更新テスト
	 * 
	 * @dataProvider update_user_data_provider
	 */
	public function test_update_user($input, $expected)
	{
		$auth = Auth::instance();
		$auth->login($input['email'], $input['password']);
		try
		{
			$test = $auth->update_user($input['values'], $input['id']);
			$this->assertEquals($expected, $test);
			return;
		}
		catch(Web2UserUpdateException $e)
		{
			if($e->getCode() === $expected) return;
		}
		catch(Web2UserWrongPassword $e)
		{
			if($e->getCode() === $expected) return;
		}
		$this->fail('ユーザ情報アップデート時に失敗');
	}
	public function update_user_data_provider()
	{
		return array(
			array(array(
					'email'    => null,
					'password' => null,
					'id'       => 1,
					'values'   => array(),	//更新対象ユーザへのログイン情報
				), 3),	//エラーコードまたは可否
			array(array(	//権限のないユーザを更新
					'email'    => 'a@a.com',
					'password' => 'testtest',
					'id'       => 2,
					'values'   => array(),
				), 3),
			array(array(	//存在しないユーザを更新
					'email'    => 'a@a.com',
					'password' => 'testtest',
					'id'       => 5,
					'values'   => array()
				), 3),
			array(array(	//パスワードが間違っている
					'email'    => 'a@a.com',
					'password' => 'testtest',
					'id'       => 1,
					'values'   => array(
						'password'     => 'aaaaaaaa',
						'old_password' => 'aaaaaaaa',
					),
				), 5),
			array(array(	//変更後のパスワードがない
					'email'    => 'a@a.com',
					'password' => 'testtest',
					'id'       => 1,
					'values'   => array(
						'password'     => '',
						'old_password' => 'testtest',
					),
				), 6),
			array(array(	//メールアドレスの形式がおかしい
					'email'    => 'a@a.com',
					'password' => 'testtest',
					'id'       => 1,
					'values'   => array(
						'password'     => 'aaaaaaaa',
						'old_password' => 'testtest',
						'email'        => 'aaaaaaaaaaaaaaaaaa',
					),
				), 7),
			array(array(	//メールアドレスが別ユーザで登録済み
					'email'    => 'a@a.com',
					'password' => 'testtest',
					'id'       => 1,
					'values'   => array(
						'password'     => 'aaaaaaaa',
						'old_password' => 'testtest',
						'email'        => 'b@b.com',
						'group'        => '',
						'twitter_id'   => '',
						'twitter_name' => '',
						'username'     => '',
						'description'  => '',
						'image'        => '',
					),
				), 2),
			array(array(
					'email'    => 'a@a.com',
					'password' => 'testtest',
					'id'       => 1,
					'values'   => array(
						'password'     => 'aaaaaaaa',
						'old_password' => 'testtest',
						'email'        => 'a2@a.com',
						'group'        => '2',
						'twitter_id'   => '101',
						'twitter_name' => 'twtter_user',
						'username'     => '更新後テストユーザ',
						'description'  => '更新されたテストユーザです',
						'image'        => 'http://i.yimg.jp/images/rikunabi/commerce/images/bnr181.png',
					),
				), true),
			);
	}

	/**
	 * パスワード更新テスト
	 * 
	 * @dataProvider change_password_data_provider
	 */
	public function test_change_password($input, $expected)
	{
		$auth = Auth::instance();
		$auth->login($input['email'], $input['password']);
		try
		{
			$test = $auth->change_password(
											$input['old_password'],
											$input['new_password'],
											$input['id']
										);
			$this->assertEquals($expected, $test);
			return;
		}
		catch(Web2UserUpdateException $e)
		{
			if($e->getCode() === $expected) return;
		}
		catch(Web2UserWrongPassword $e)
		{
			if($e->getCode() === $expected) return;
		}
		$this->fail('パスワード更新時エラー');
	}
	public function change_password_data_provider()
	{
		return array(
			array(array(
					'email'   => null,
					'password'   => null,
					'old_password'   => null,
					'new_password'   => null,
					'id'   => 1,	//更新対象ユーザID
				), 3),	//エラーコード
			array(array(	//権限のないユーザを更新
					'email'   => 'a@a.com',
					'password'   => 'testtest',
					'old_password'   => 'testtest',
					'new_password'   => 'aaaaaaaa',
					'id'   => 2,
				), 3),
			array(array(	//パスワード誤り
					'email'   => 'a@a.com',
					'password'   => 'testtest',
					'old_password'   => 'aaaaaaaa',
					'new_password'   => 'aaaaaaaa',
					'id'   => 1,
				), 5),
			array(array(	//新しいパスワードを設定しない
					'email'   => 'a@a.com',
					'password'   => 'testtest',
					'old_password'   => 'testtest',
					'new_password'   => '',
					'id'   => 1,
				), 6),
			array(array(
					'email'   => 'a@a.com',
					'password'   => 'testtest',
					'old_password'   => 'testtest',
					'new_password'   => 'aaaaaaaa',
					'id'   => 1,
				), true),
			array(array(	//group=0のユーザを更新
					'email'   => 'a@a.com',
					'password'   => 'testtest',
					'old_password'   => 'testtest',
					'new_password'   => 'aaaaaaaa',
					'id'   => 3,
				), true),
			);
	}
	
	/**
	 * パスワードリセットテスト
	 * 
	 * @dataProvider reset_password_data_provider
	 */
	public function test_reset_password($input, $expected)
	{
		try
		{
			$test = Auth::instance()->reset_password($input);
			//とりあえずリセット後のパスワードの長さのみ確認
			$this->assertEquals(strlen($expected), strlen($test));
			return;
		}
		catch(Web2UserUpdateException $e)
		{
			if($e->getCode() === $expected) return;
		}
		$this->fail('パスワードリセット時エラー');
	}
	public function reset_password_data_provider()
	{
		return array(
				array(null, 8),
				array(1, 'aaaaaaaa'),
				array(4, 8),	//存在しないユーザのパスワードはリセットできない
			);
	}
	
	/**
	 * ユーザ削除テスト
	 * 
	 * @dataProvider delete_user_data_provider
	 */
	public function test_delete_user($input, $expected)
	{
		\Session::set('login_id', $input['login_id']);
		\Session::set('login_hash', $input['login_hash']);
		$test = Auth::instance()->delete_user($input['delete_id']);
		$this->assertEquals($expected, $test);
	}
	public function delete_user_data_provider()
	{
		return array(
			array(array(
				'login_id'   => null,
				'delete_id'   => 1,
				'login_hash' => null,
				), false),
			array(array(
				'login_id'   => 1,
				'delete_id'   => 1,
				'login_hash' => '0a3229bd2b421c7da598981044aa972fc8e55b1d',
				), true),
			array(array(	//権限のないユーザは削除できない
				'login_id'   => 1,
				'delete_id'   => 2,
				'login_hash' => '0a3229bd2b421c7da598981044aa972fc8e55b1d',
				), false),
			array(array(	//group=0ならログインしていなくても削除できる
				'login_id'   => null,
				'delete_id'   => 3,
				'login_hash' => null,
				), true),
			array(array(
				'login_id'   => 1,
				'delete_id'   => 3,
				'login_hash' => '0a3229bd2b421c7da598981044aa972fc8e55b1d',
				), true),
			);
	}
	
	/**
	 * ログインハッシュ作成テスト
	 * 
	 * @dataProvider login_user_data_provider
	 */
	public function test_create_login_hash($input, $expected)
	{
		$auth = Auth::instance();
		$auth->login($input['email'], $input['password']);
		$expected = $expected['login_hash'];
		try
		{
			$test = $auth->create_login_hash();
			//とりあえずログインハッシュがカラでないことを確認
			$this->assertNotEquals($expected, $test);
			return;
		}
		catch(Web2UserUpdateException $e)
		{
			if($e->getCode() === $expected) return;
		}
		$this->fail('ログインハッシュ作成時エラー');
	}
	
	/**
	 * ユーザID取得テスト
	 * 
	 * @dataProvider login_user_data_provider
	 */
	public function test_get_user_id($input, $expected)
	{
		$auth = Auth::instance();
		$auth->login($input['email'], $input['password']);
		$test = $auth->get_user_id();
		$expected = $expected['id'];
		$this->assertEquals($expected, $test);
	}
	
	/**
	 * グループコード取得テスト
	 * 
	 * @dataProvider login_user_data_provider
	 */
	public function test_get_groups($input, $expected)
	{
		$auth = Auth::instance();
		$auth->login($input['email'], $input['password']);
		$test = $auth->get_groups();
		$expected = $expected['group'];
		$this->assertEquals($expected, $test);
	}
	
	/**
	 * メールアドレス取得テスト
	 * 
	 * @dataProvider login_user_data_provider
	 */
	public function test_get_email($input, $expected)
	{
		$auth = Auth::instance();
		$auth->login($input['email'], $input['password']);
		$test = $auth->get_email();
		$expected = $expected['email'];
		$this->assertEquals($expected, $test);
	}
	
	/**
	 * ユーザ名取得テスト
	 * 
	 * @dataProvider login_user_data_provider
	 */
	public function test_get_screen_name($input, $expected)
	{
		$auth = Auth::instance();
		$auth->login($input['email'], $input['password']);
		$test = $auth->get_screen_name();
		$expected = $expected['username'];
		$this->assertEquals($expected, $test);
	}
}

<?php
/**
 * web2auth
 * 
 * メールアドレスとパスワード、またはツイッターによる認証を行う
 *（ツイッター認証のためには別途パッケージ
 * (https://github.com/dhorrigan/fuel-twitter)が必要）
 * 
 * @author     Shiina, Yuji
 * @license    MIT License
 * @copyright  2012 Shiina, Yuji
 * @link       http://github.com/web2citizen/web2auth
 */

class Web2UserUpdateException extends \FuelException {}

class Web2UserWrongPassword extends \FuelException {}

//class Auth_Login_web2auth extends Auth\Auth_Login_Driver
class Auth_Login_Web2Auth extends Auth\Auth_Login_Driver
{
	/**
	 * 設定ファイルの読み込み
	 */
	public static function _init()
	{
		\Config::load('web2auth', true, true, true);
	}
	
	/**
	 * ログイン状態チェック
	 *
	 * @return  bool   ログイン中:true 未ログイン（不正ログイン）:false
	 */
	protected function perform_check()
	{
		$login_id    = \Session::get('login_id');
		$login_hash  = \Session::get('login_hash');

		// ログイン状況、IDとハッシュの一致をチェック
		if ( ! empty($login_id) and ! empty($login_hash)) {
			$this->user = \DB::select_array(\Config::get('web2auth.table_columns', array('*')))
				 ->where('id', '=', $login_id)
				 ->from(\Config::get('web2auth.table_name'))
				 ->execute(\Config::get('web2auth.db_connection'))->current();

			if ($this->user and $this->user['login_hash'] === $login_hash) return true;
		}
		// ログインの確認ができないときはログイン情報を削除する
		\Session::delete('login_id');
		\Session::delete('login_hash');
		\Session::delete('login_name');
		return false;
	}

	/**
	 * 権限チェック
	 *
	 * @param   int   変更対象ユーザID
	 * @return  bool  権限あり:true 権限なし:false
	 */
	public function role_check($affect_user_id = null)
	{
		if(empty($affect_user_id)) return false;

		$affect_user = \DB::select_array(\Config::get('web2auth.table_columns', array('*')))
					->where('id', '=', $affect_user_id)
					->from(\Config::get('web2auth.table_name'))
					->execute(\Config::get('web2auth.db_connection'))->current();

		// 現状、group0は誰でも操作可能とする。
		if($affect_user['group'] === '0') return true;
		// ログイン状態をチェックする
		if( ! $this->perform_check()) return false;
		// 権限のない時、エラーを返す（現状は自分にのみ権限がある）
		if($this->user['id'] !== $affect_user['id']) return false;

		return true;
	}

	/**
	 * ログイン時のメールアドレス、パスワードチェック
	 *
	 * @param   string   メールアドレス
	 * @param   string   パスワード
	 * @return  bool     ログイン可:true ログイン不可:false
	 */
	public function validate_user($email = '', $password = '')
	{
		$email = trim($email) ?:
			trim(\Input::post(\Config::get('web2auth.username_post_key', 'email')));
		$password = trim($password) ?:
			trim(\Input::post(\Config::get('web2auth.password_post_key', 'password')));

		if (empty($email) or empty($password)) return false;

		$hash_password = $this->hash_password($password);
		$this->user = \DB::select_array(\Config::get('web2auth.table_columns', array('*')))
			->where_open()
			->where('email', '=', $email)
			->and_where('password', '=', $hash_password)
			->where_close()
			->from(\Config::get('web2auth.table_name'))
			->execute(\Config::get('web2auth.db_connection'))->current();

		return $this->user ?: false;
	}

	/**
	 * ツイッターによるログイン処理時のコールバックで呼び出すメソッド
	 *
	 * @return  bool
	 */
	public function twitter_callback()
	{
		$twitter_user = \Twitter::get('account/verify_credentials');

		// 登録済みユーザかチェック
		if ( ! ($this->user = $this->get_user_by_twitter_id($twitter_user->id)))
		{
			// 登録済みでない場合新規登録する
			$this->create_twitter_user($twitter_user);
			$this->user = $this->get_user_by_twitter_id($twitter_user->id);
		}
		\Session::set('login_id', $this->user['id']);
		\Session::set('login_hash', $this->create_login_hash());
		\Session::set('login_name', $this->user['username']);
		\Session::instance()->rotate();
		return true;
	}

	/**
	 * twitter_idに該当するユーザを取得
	 *
	 * @param   twitter_id   取得するtwitter_id
	 * @return  array()      取得したユーザ情報
	 */
	protected function get_user_by_twitter_id($twitter_id = null)
	{
	if (empty($twitter_id)) return false;
	
	return \DB::select_array(\Config::get('web2auth.table_columns', array('*')))
			->where('twitter_id', '=', $twitter_id)
			->from(\Config::get('web2auth.table_name'))
			->execute(\Config::get('web2auth.db_connection'))->current();
	}

	/**
	 * twitterの情報を元にユーザーを作成
	 *
	 * @param   twitter_user   ツイッターユーザオブジェクト
	 * @return  bool           実行結果可否
	 */
	protected function create_twitter_user($twitter_user = null)
	{
		if (empty($twitter_user)) return false;
		
		$user = array(
				'group'           => 1,
				'twitter_id'      => $twitter_user->id,
				'twitter_name'    => $twitter_user->screen_name,
				'username'        => $twitter_user->name,
				'description'     => $twitter_user->description,
				'image'           => $twitter_user->profile_image_url,
				'created_at'      => \Date::forge()->get_timestamp(),
		);
		$result = \DB::insert(\Config::get('web2auth.table_name'))
			->set($user)
			->execute(\Config::get('web2auth.db_connection'));

		return ($result[1] > 0) ? $result[0] : false;
	}

	/**
	 * twitterの情報を元にユーザー情報を更新
	 *
	 * @param   int    更新対象ユーザID
	 * @return  bool   更新
	 */
	public function update_twitter_user($user_id = null)
	{
		if (empty($user_id)) return false;
		
		$twitter_user = \Twitter::get('account/verify_credentials');
		// 未登録のtwitter userの場合は登録させない
		$user = $this->get_user_by_twitter_id($twitter_user->id);
		
		if ($user and $user_id !== (int) $user['id']) return false;

		$values = array(
			'twitter_id'      => $twitter_user->id,
			'twitter_name'    => $twitter_user->screen_name,
			'username'        => $twitter_user->name,
			'description'     => $twitter_user->description,
			'image'           => $twitter_user->profile_image_url,
		);
		return $this->update_user($values, $user_id);
	}

	/**
	 * ユーザをログインさせる
	 *
	 * @param   string   メールアドレス
	 * @param   string   パスワード
	 * @return  bool     実行結果可否
	 */
	public function login($email = '', $password = '')
	{
		if ( ! ($this->user = $this->validate_user($email, $password)))
		{
			\Session::delete('login_id');
			\Session::delete('login_hash');
			return false;
		}
		\Session::set('login_id', $this->user['id']);
		\Session::set('login_hash', $this->create_login_hash());
		\Session::set('login_name', $this->user['username']);
		\Session::instance()->rotate();
		return true;
	}

	/**
	 * 強制ログイン
	 * 管理時のみの使用を想定
	 *
	 * @param   int    ログインユーザID
	 * @return  bool   実行結果可否
	 */
	public function force_login($user_id = null)
	{
		if (empty($user_id)) return false;

		$this->user = \DB::select_array(\Config::get('web2auth.table_columns', array('*')))
			->where('id', '=', $user_id)
			->from(\Config::get('web2auth.table_name'))
			->execute(\Config::get('web2auth.db_connection'))
			->current();

		if ($this->user === false) {
			\Session::delete('login_id');
			\Session::delete('login_hash');
			\Session::delete('login_name');
			return false;
		}
		\Session::set('login_id', $this->user['id']);
		\Session::set('login_hash', $this->create_login_hash());
		\Session::set('login_name', $this->user['username']);
		return true;
	}

	/**
	 * ユーザをログアウトさせる
	 *
	 * @return  bool
	 */
	public function logout()
	{
		\Session::delete('login_id');
		\Session::delete('login_hash');
		\Session::delete('login_name');
		return true;
	}

	/**
	 * 新規ユーザ作成
	 * 
	 *
	 * @param   string   メールアドレス
	 * @param   string   パスワード
	 * @param   int      group
	 * @return  bool     実行結果可否
	 * @throws Web2UserUpdateException パスワードまたはメールアドレスが未入力
	 *									登録済みのメールアドレスを入力
	 */
	public function create_user($email, $password, $group = 1)
	{
	$email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
		$password = trim($password);

		if ($group > 0)
		{
			if (empty($password) or empty($email))
				throw new \Web2UserUpdateException('パスワード、メールアドレスは必ず入力してください。', 1);

			$same_users = \DB::select_array(\Config::get('web2auth.table_columns', array('*')))
				->where('email', '=', $email)
				->from(\Config::get('web2auth.table_name'))
				->execute(\Config::get('web2auth.db_connection'));

			if ($same_users->count() > 0)
				throw new \Web2UserUpdateException('そのメールアドレスはすでに登録されています。', 2);
		}

		// デフォルトの登録内容
		$user = array(
				'password'        => $this->hash_password((string) $password),
				'email'           => $email,
				'username'        => \Config::get('web2auth.default_name'),
				'image'           => \Config::get('web2auth.default_image'),
				'group'           => (int) $group,
				'created_at'      => \Date::forge()->get_timestamp(),
		);
		$result = \DB::insert(\Config::get('web2auth.table_name'))
			->set($user)
			->execute(\Config::get('web2auth.db_connection'));

		return ($result[1] > 0) ? $result[0] : false;
	}

	/**
	 * ユーザ情報更新
	 *
	 * @param   Array   アップデートする項目
	 * @param   int     アップデート対象ユーザID
	 * @return  bool    実行結果可否
	 * @throws Web2UserUpdateException 権限なし
	 *									ユーザ未取得
	 * 									パスワード未入力
	 * 									メール形式不一致
	 * 									既に登録されているメールアドレスで更新
	 * @throws Web2UserWrongPassword   旧パスワード不一致
	 */
	public function update_user($values, $user_id = null)
	{
		if (!$this->role_check($user_id))
			throw new \Web2UserUpdateException('権限がありません。', 3);

		$current_values = \DB::select_array(\Config::get('web2auth.table_columns', array('*')))
			->where('id', '=', $user_id)
			->from(\Config::get('web2auth.table_name'))
			->execute(\Config::get('web2auth.db_connection'));

		if (empty($current_values))
			throw new \Web2UserUpdateException('ユーザが見つかりませんでした。', 4);

		$update = array();

		if (array_key_exists('password', $values))
		{
			if (trim($current_values->get('password') !== '') and
					$current_values->get('password') !== $this->hash_password(trim($values['old_password'])))
				throw new \Web2UserWrongPassword('古いパスワードが一致しません。', 5);

			$password = trim(strval($values['password']));
			if ($password === '')
				throw new \Web2UserUpdateException('パスワードは空欄にできません。', 6);

			$update['password'] = $this->hash_password($password);
			unset($values['password']);
		}
		if (array_key_exists('old_password', $values))
			unset($values['old_password']);

		if (array_key_exists('email', $values)) {
			$email = filter_var(trim($values['email']), FILTER_VALIDATE_EMAIL);
			if ( ! $email)
				throw new \Web2UserUpdateException('メールアドレスが正しくありません。', 7);

			// メールアドレスの存在チェック
			$same_users = \DB::select_array(\Config::get('web2auth.table_columns', array('*')))
				->where_open()
				->where('email', '=', $email)
				->and_where('id', '!=', $user_id)
				->where_close()
				->from(\Config::get('web2auth.table_name'))
				->execute(\Config::get('web2auth.db_connection'));

			if ($same_users->count() > 0)
				throw new \Web2UserUpdateException('そのメールアドレスはすでに登録されています。', 2);

			$update['email'] = $email;
			unset($values['email']);
		}
		if (array_key_exists('group', $values)) {
			if (is_numeric($values['group'])) {
				$update['group'] = (int) $values['group'];
				unset($values['group']);
			}
		}
		if (array_key_exists('twitter_id', $values)) {
			if (is_numeric($values['twitter_id'])) {
				$update['twitter_id'] = $values['twitter_id'];
				unset($values['twitter_id']);
			}
		}
		if (array_key_exists('twitter_name', $values)) {
			$update['twitter_name'] = $values['twitter_name'];
			unset($values['twitter_name']);
		}
		if (array_key_exists('username', $values)) {
			$update['username'] = $values['username'];
			unset($values['username']);
		}
		if (array_key_exists('description', $values)) {
			$update['description'] = $values['description'];
			unset($values['description']);
		}
		if (array_key_exists('image', $values)) {
			$update['image'] = $values['image'];
			unset($values['image']);
		}
		$update['updated_at'] = \Date::forge()->get_timestamp();

		$affected_rows = \DB::update(\Config::get('web2auth.table_name'))
			->set($update)
			->where('id', '=', $user_id)
			->execute(\Config::get('web2auth.db_connection'));

		// Refresh user
		if ($this->get_user_id() === $user_id)
		{
			$this->user = \DB::select_array(\Config::get('web2auth.table_columns', array('*')))
				->where('id', '=', $user_id)
				->from(\Config::get('web2auth.table_name'))
				->execute(\Config::get('web2auth.db_connection'))->current();
			// ユーザ名を再設定
			\Session::set('login_name', $this->user['username']);
		}
		return $affected_rows > 0;
	}

	/**
	 * パスワード変更
	 *
	 * @param   string   変更前パスワード
	 * @param   string   変更後パスワード
	 * @param   int      変更対象ユーザID
	 * @return  bool     実行結果可否
	 */
	public function change_password($old_password, $new_password, $user_id = null)
	{
		return (bool) $this
			->update_user(array(
					'old_password' => $old_password,
					'password' => $new_password
				),$user_id);
	}

	/**
	 * ユーザのパスワードをリセットして新しいパスワードに変更する
	 * ユーザがパスワードを忘れた時に使用し、通常は使用しない
	 *
	 * @param   int      更新対象ユーザID
	 * @return  string   新しいパスワード
	 * @throws Web2UserUpdateException リセット失敗
	 */
	public function reset_password($user_id = null)
	{
		$new_password = \Str::random('alnum', 8);
		$password_hash = $this->hash_password($new_password);

		$affected_rows = \DB::update(\Config::get('web2auth.table_name'))
			->set(array('password' => $password_hash))
			->where('id', '=', $user_id)
			->execute(\Config::get('web2auth.db_connection'));

		if (!$affected_rows)
			throw new \Web2UserUpdateException('パスワードのリセットに失敗しました。', 8);
		return $new_password;
	}

	/**
	 * ユーザ削除
	 *
	 * @param   int    削除対象ユーザID
	 * @return  bool   実行結果可否
	 */
	public function delete_user($user_id = null)
	{
		if ( ! $this->role_check($user_id)) return false;

		$affected_rows = \DB::delete(\Config::get('web2auth.table_name'))
			->where('id', '=', $user_id)
			->execute(\Config::get('web2auth.db_connection'));

		return $affected_rows > 0;
	}

	/**
	 * コンフィグの login_hash_salt を使用してログインハッシュ作成
	 *
	 * @return  string   ログインハッシュ
	 * @throws Web2UserUpdateException 未ログインのためハッシュ作成できず
	 */
	public function create_login_hash()
	{
		if (empty($this->user))
			throw new \Web2UserUpdateException('ログインしていないためログインハッシュが作成できませんでした。', 10);

		$last_login = \Date::forge()->get_timestamp();
		$login_hash = sha1(\Config::get('web2auth.login_hash_salt').$this->user['id'].$last_login);

		\DB::update(\Config::get('web2auth.table_name'))
			->set(array('last_login' => $last_login, 'login_hash' => $login_hash))
			->where('id', '=', $this->user['id'])
			->execute(\Config::get('web2auth.db_connection'));

		$this->user['login_hash'] = $login_hash;

		return $login_hash;
	}

	/**
	 * ユーザIDを取得する
	 *
	 * @return  int   ユーザID
	 */
	public function get_user_id()
	{
		if (empty($this->user)) return false;
		return (int) $this->user['id'];
	}

	/**
	 * 所属グループコードを取得する
	 *
	 * @return  int   グループコード
	 */
	public function get_groups()
	{
		if (empty($this->user)) return false;
		return (int) $this->user['group'];
	}

	/**
	 * メールアドレスを取得する
	 *
	 * @return  string   メールアドレス
	 */
	public function get_email()
	{
		if (empty($this->user)) return false;
		return $this->user['email'];
	}

	/**
	 * ユーザ名を取得する
	 *
	 * @return  string   ユーザ名
	 */
	public function get_screen_name()
	{
		if (empty($this->user)) return false;
		return $this->user['username'];
	}
}

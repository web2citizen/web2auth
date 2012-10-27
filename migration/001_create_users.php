<?php

namespace Fuel\Migrations;

class Create_users
{
	public function up()
	{
		\DBUtil::create_table('users', array(
			'id' => array('constraint' => 11, 'type' => 'int', 'auto_increment' => true),
			'twitter_id' => array('constraint' => 20, 'type' => 'varchar'),
			'twitter_name' => array('constraint' => 20, 'type' => 'varchar'),
			'email' => array('constraint' => 128, 'type' => 'varchar'),
			'password' => array('constraint' => 128, 'type' => 'varchar'),
			'group' => array('constraint' => 11, 'type' => 'int'),
			'username' => array('constraint' => 40, 'type' => 'varchar'),
			'description' => array('type' => 'text'),
			'image' => array('constraint' => 255, 'type' => 'varchar'),
			'last_login' => array('constraint' => 11, 'type' => 'int'),
			'login_hash' => array('constraint' => 255, 'type' => 'varchar'),
			'created_at' => array('constraint' => 11, 'type' => 'int'),
			'updated_at' => array('constraint' => 11, 'type' => 'int'),
		), array('id'));
	}

	public function down()
	{
		\DBUtil::drop_table('users');
	}
}
#Web2Auth

## 概要

FuelPHPのSimpleAuthクラスを自分なりに使いやすいように書き換えた認証クラスです。

## SimpleAuthからの主な変更内容

* ユーザの詳細情報を個別のカラムに格納（Profile_fieldsカラムを削除）
* Twitterによる認証追加（別途Twitterパッケージが必要）
* ユーザ作成時に必要な情報からユーザ名を削除し、メールアドレスとパスワードで認証
* ユーザのグループ分け機能の削除（group=0 のユーザに限り誰からも（ログインしていなくても）変更できる）

## 使い方

各ファイルを fuel/app 以下の適切なフォルダに格納してください。
Twitterによる認証機能を使用するためには、別途FuelPHPのTwitterパッケージ（https://github.com/dhorrigan/fuel-twitter）を導入してください。

## ライセンス

MIT License
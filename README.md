#Web2Auth

## 概要

FuelPHPのSimpleAuthクラスを自分なりに使いやすいように書き換えた認証クラスです。

## SimpleAuthからの主な変更内容

* ユーザの詳細情報を個別のカラムに格納（Profile_fieldsカラムを削除）
* Twitterによる認証追加（別途Twitterパッケージが必要）
* ユーザ作成時に必要な情報からユーザ名を削除し、メールアドレスとパスワードで認証
* ユーザのグループ分け機能の削除（group=0 のユーザに限り誰からも（ログインしていなくても）変更できる）
  
group=0で登録したユーザは、ログイン前のユーザからも変更、削除が出来てしまいます。  
ゲストユーザのような使い方を想定しています。


## 使い方

各ファイルを fuel/app 以下の適切なディレクトリに格納してください。  
Twitterによる認証機能を使用するためには、別途FuelPHPの[Twitterパッケージ]（https://github.com/dhorrigan/fuel-twitter ）を導入してください。  
その他詳細は[こちら](http://www.web2citizen.info/blog/2012/10/fuelphp%E3%81%AE%E8%87%AA%E4%BD%9C%E8%AA%8D%E8%A8%BC%E3%82%AF%E3%83%A9%E3%82%B9%E3%82%92%E5%85%AC%E9%96%8B%E3%81%97%E3%81%BE%E3%81%97%E3%81%9F/)もご参照ください。

## ライセンス

MIT License
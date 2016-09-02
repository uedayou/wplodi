# WPLODI

WordPress で作成したデータをLinked Dataとして出力できるプラグインです。

## 特徴

[wp-linked-data](https://wordpress.org/plugins/wp-linked-data/)とできることはほぼ同じです。
記事タイトル、本文、著者情報で利用するプロパティも、wp-linked-dataと同じです。

wp-linked-data と異なる点は以下の2つです。

- コンテントネゴシエーションにより出力できる対応フォーマットが多い(JSON,JSON-LDにも対応)
- カスタムフィールドに追加したデータもRDFに出力される

カスタムフィールドに追加する場合は、

|項目|入力する値|
|---|---|
|name|プロパティ※1|
|value|プロパティに<br>対応するデータ※2|

を入力してください。

※1:[EasyRDF:RdfNamespace](https://github.com/njh/easyrdf/blob/master/lib/RdfNamespace.php)の`$initial_namespaces`で設定されているPrefixと、[共通語彙基盤](http://imi.ipa.go.jp/ns/core/rdf#)「`ic:`」については、Prefixによる省略表記(たとえば`rdfs:label`)が利用できます。

※2:`http(s)://`で始まる文字列はリソースとして、数値、日付(Y-m-d)、日付と日時(Y-m-d H:i:s)に合致する文字列はそれぞれのデータ型が付与されたリテラルとして扱われます。それ以外は文字列リテラルとなります。

## 使い方

wp-content/plugins にフォルダごとコピーしてください。

## 利用ライブラリ

- [EasyRdf](http://www.easyrdf.org/)
- [JsonLD](https://github.com/lanthaler/JsonLD)
- [IRI](https://github.com/lanthaler/IRI)
- [Negotiation](http://williamdurand.fr/Negotiation/)
- [wp-linked-data](https://wordpress.org/plugins/wp-linked-data/)

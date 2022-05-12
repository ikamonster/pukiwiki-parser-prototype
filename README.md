# PukiWiki　対応マークアップ拡張

任意のマークアップパーサーをPukiWikiに組み込むための仕組みの提案です。  
マークアップパーサーはいくつでも追加することができ、どれを使うかページごとに選ぶことができます。

<br>

## インストール

下記GitHubページからダウンロードした ``lib/`` と ``plugin/`` を、インストール直後の素の [PukiWiki v1.5.4](https://pukiwiki.osdn.jp/?PukiWiki/Download/1.5.4) のディレクトリに上書きコピーしてください。

https://github.com/ikamonster/pukiwiki-parser-prototype

すると、ページ編集画面にマークアップ選択ボックスが現れます。そこで「markdown」を選ぶと、ページをMarkdown記法で記述できるようになります。従来のPukiWiki記法か新たなMarkdown記法か、ページごとに選ぶことができます。

<br>

## 仕組み
基本的にはただのPukiWiki用プラグインです。  
plugin/parser.inc.php が窓口となるプラグイン本体、plugin/parser/マークアップ名/converter.inc.php が各マークアップに対応するパーサー（マークアップ→HTML変換実装）、という構成になっています。  
今のところ Markdown 実装しか用意していませんが、任意のマークアップパーサーをいくつでも配備することができます。 

そしてPukiWiki本体（lib/）を一部改修し、PukiWiki記法→HTML変換時などにこのプラグインを割り込ませ、処理を肩代わりさせることで対応マークアップの変更を実現しています。

標準のプラグイン機構を利用しているため、新たなユーザー拡張の仕組みを必要とせず、呼び出し手順が明快で見通しがよく、他の本体改造系機能拡張に干渉する恐れが少なく、処理も開発もPukiWiki本体とよく分離できます。  
たとえば、この機能拡張が不要なら plugin/parser.inc.php を削除するだけでプレーンなPukiWikiの動作に戻ります。

### Markdownパーサー実装について
plugin/parser/markdown/converter.inc.php 内のMarkdown→HTML変換のコードは、m0370氏作「[Pukiwiki 1.5.4 Markdown対応版（暫定版）v0.1](https://github.com/m0370/pukiwiki154_md/releases/tag/v0.1)」を流用させていただきました。Wikiテキスト内のタグで対応マークアップを判別するといったアイデアも同じく。要するに、m0370氏のコードを汎化してみたようなものです。

また、核となる変換処理にerusev氏作「[Parsedown](https://github.com/erusev/parsedown)」を、編集ヘルパーにlonaru氏作「[EasyMDE](https://github.com/Ionaru/easy-markdown-editor)」を使用しています。

<br>

## パーサー用プラグイン呼び出し（プラグインMOD）
マークアップの変更には、従来のPukiWiki記法に依存する既存のプラグインが正常に動作しなくなるという重大な副作用があります。  
この問題を、プラグイン機構にいわゆるMOD方式を導入することで解決します。

たとえばMarkdown記法のページを表示する際、plugin/parser/markdown/plugin/ 配下に標準プラグインディレクトリにあるのと同名のプラグインファイルが存在する場合、そちらが優先して実行されます。  
この仕組みにより、記法の違いにより正常動作しない既存のプラグインを、動作するものにそっくり置き換えることができます。また、対象マークアップ専用のプラグインを置くこともできます。  

このとおりMOD方式には、オリジナルのプラグインに手を加えずに済み、各マークアップ対応プラグインを明瞭に管理できるメリットがあります。

動作テスト用に plugin/parser/markdown/plugin/comment.inc.php を作成してあります。標準プラグインディレクトリにあるオリジナルの comment.inc.php を、インデント付きリストをMarkdown記法で出力するよう改修したものです。Markdown記法ページに「#comment」を記述するとこちらが呼ばれます。

<br>

## 必須環境
同名のパーサー実装クラスやパーサー用プラグイン関数の区別に名前空間を使用するため、PHP5.3以上が必要です。  
PHP5.3未満の環境ではこの機能拡張が自動的に無効化され、プレーンなPukiWikiとして動作します。

<br>

## ファイル構成

|ディレクトリ|ファイル|機能／改修点|種別|
|:---|:---|:---|:---:|
|lib/|convert_html.php|PukiWiki記法→HTML変換。パーサープラグインが割り込んで、各マークアップ変換処理を行う|改修|
| |html.php|ページ表示・編集。パーサープラグインが割り込んで、編集フォームにマークアップ選択ボックスと編集ヘルパーを追加する|改修|
| |plugin.php|プラグイン呼び出し。パーサープラグインが割り込んで、各パーサー対応プラグインを優先して呼び出す|改修|
| |pukiwiki.php|リクエスト受信等。パーサープラグインが割り込んで、他プラグインのaction実行時に対応パーサー名を加える|改修|
|plugin/|edit.inc.php|ページ編集プラグイン。パーサープラグインが割り込んで、ページにパーサー使用タグを挿入する|改修|
||parser.inc.php|パーサープラグイン本体|追加|
|plugin/parser/markdown/|converter.inc.php|Markdown記法パーサー|追加|
|plugin/parser/markdown/plugin/|comment.inc.php|Markdown記法用commentプラグイン|追加|
|plugin/parser/markdown/vendor/|Parsedown.php|Markdown→HTML変換ライブラリ Parsedown|追加|

### PukiWiki v1.5.4 オリジナルとの差分
https://github.com/ikamonster/pukiwiki-parser-prototype/compare/original...main?diff=split#files_bucket

<br><br><br><br>

# 新たなマークアップパーサーの追加方法（開発者向け）

マークアップパーサーの仕様は極めて小さいため、PukiWikiプラグイン開発経験のあるPHPプログラマーであれば、既存のMarkdownパーサー関連ファイルを読めば仕組みを理解できると思います。  
plugin/parser/markdown/ ディレクトリをまるごとコピペし、変えるべきところを変えればOKです。

ここでは最低限必要な開発範囲を示す例として、架空の“Sample”マークアップに対応する「sample」という名のパーサーを追加する手順を説明します。

<br>

## 1. パーサークラスを作成する
まず、``plugin/parser/`` ディレクトリ内にパーサー名となる ``sample`` というディレクトリを作ります。  
そして、その中にパーサー本体であるマークアップ→HTML変換実装クラスファイル ``converter.inc.php`` を作ります。

plugin/parser/sample/converter.inc.php

```PHP
// 必ず「parser\パーサー名」の名前空間を設定すること！
namespace parser\sample;

// 「\PluginParserConverter」を継承する「Converter」クラスを作成
class Converter extends \PluginParserConverter { 
    // マークアップ→HTML変換メソッド
    public function convertHtml(&$lines, $contentID = 0) {
        // ...
    }
}
```

コードはコピペでかまいませんが、名前空間をパーサー名に合わせて設定する（ここでは ``parser/sample``）のを忘れないでください。

<br>

## 2. マークアップ→HTML変換処理を実装する
``Converter::convertHtml(&$lines, $contentID)`` メソッドを実装していきます。  
第1引数 ``$lines`` が編集テキスト本文、つまり変換対象のマークアップです。文字列型ではなく、1行ごとの文字列が格納された配列となっています。第2引数はとりあえず無視してください。  
返り値は、変換後のHTMLです。こちらは文字列型です。  

入力テキストを変換せず素通しで出力する例を示します。

```PHP
    public function convertHtml(&$lines, $contentID = 0) {
        $html = '';

        while (!empty($lines)) {
            $line = array_shift($lines);

            // ※ここで $line を対象マークアップからHTMLに変換

            $html .= $line . "\n";
        }

        return $html;
    }
```

おわかりのとおり、※の部分で ``$line`` に対して行単位でのマークアップ→HTML変換を施せばよいだけです。  

メジャーなマークアップ記法であれば、PHP用の変換ライブラリが大抵すでに作られており利用できます。  
安全のため、ユーザーが直接書いたHTMLタグはサニタイズしましょう。  
また、必要に応じてPukiWiki形式の自動リンクやプラグイン記述を展開してください（少なくとも ``#author`` と ``#freeze`` 行は非表示にしなくてはならない）。PukiWiki本体（lib/）に用意されている関数やクラスをできるだけ利用するのが簡単で確実です。

以上です。  
記法に対応する編集ヘルパーやパーサー用プラグインの提供方法については、Markdownパーサーの実装を参照してください。

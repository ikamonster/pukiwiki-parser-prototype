<?php
// PukiWiki - Yet another WikiWikiWeb clone
// parser.inc.php, v1.0.1 2022 M.Taniguchi
// Copyright
//   2002-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// マークアップ記法汎用パーサープラグイン

/////////////////////////////////////////////////
// マークアップ記法汎用パーサープラグイン設定（parser.inc.php）
if (!defined('PLUGIN_PARSER_DEFAULT'))    define('PLUGIN_PARSER_DEFAULT',    '');                     // ページ新規作成時のデフォルトパーサー名（空ならPukiWiki記法）
if (!defined('PLUGIN_PARSER_DIR'))        define('PLUGIN_PARSER_DIR',        PLUGIN_DIR . 'parser/'); // パーサー実装ファイル配置ディレクトリ
if (!defined('PLUGIN_PARSER_NAME_REGEX')) define('PLUGIN_PARSER_NAME_REGEX', '[a-zA-Z0-9_\-]+');      // パーサー名を表す正規表現



function plugin_parser_inline() {
	static	$currentParser = null, $parsers = array();
	$markPrefix = '#parser-';
	$args = func_get_args();
	list($method, $arg1, $arg2, $arg3) = $args;
	$body = end($args);
	$result = null;

	switch ($method) {
	case 'list':	// パーサーのリストを返す
		$result = plugin_parser_makelist('list');
		break;

	case 'name':	// 最後に設定・取得した（≒現在使用中の）パーサー名を返す
		if ($arg1 && preg_match('/^(' . PLUGIN_PARSER_NAME_REGEX . ')$/i', $arg1)) $currentParser = $arg1;	// パーサー名を渡されていたら設定
		$result = $currentParser;
		break;

	case 'namespace':	// 最後に設定・取得した（≒現在使用中の）パーサー用名前空間を返す
		if ($arg1 && preg_match('/^(' . PLUGIN_PARSER_NAME_REGEX . ')$/i', $arg1)) $currentParser = $arg1;	// パーサー名を渡されていたら設定
		if ($currentParser) $result = '\\parser\\' . $currentParser . '\\';
		break;

	case 'plugin_dir':	// 最後に設定・取得した（≒現在使用中の）パーサー用プラグインディレクトリを返す
		if ($arg1 && preg_match('/^(' . PLUGIN_PARSER_NAME_REGEX . ')$/i', $arg1)) $currentParser = $arg1;	// パーサー名を渡されていたら設定
		if ($currentParser) $result = PLUGIN_PARSER_DIR . $currentParser . '/plugin/';
		break;

	case 'reset':	// 最後に設定・取得した（≒現在使用中の）パーサー名を消去
		$currentParser = null;
		break;

	case 'add_mark':	// Wikiテキスト先頭にパーサー使用印（$markPrefix＋パーサー名）を追加
		if ($arg1 && preg_match('/^(' . PLUGIN_PARSER_NAME_REGEX . ')$/i', $arg1)) {
			$currentParser = $arg1;	// 渡されたパーサー名を設定
			$result = (preg_match('/^' . $markPrefix . PLUGIN_PARSER_NAME_REGEX . '\s/im', $body) ? '' : ($markPrefix . $currentParser . "\n\n")) . $body;
		}
		break;

	case 'remove_mark':	// Wikiテキストからパーサー使用印を削除
		$result = preg_replace('/^' . $markPrefix . PLUGIN_PARSER_NAME_REGEX . '\s/im', '', $body);
		break;

	case 'get_mark':	// Wikiテキストにパーサー使用印があるか調べ、あるなら対応パーサー名を返す
		if (!is_array($body)) {
			if (preg_match('/^' . $markPrefix . '(' . PLUGIN_PARSER_NAME_REGEX . ')\s/im', $body, $matches) && count($matches) >= 1) $result = $matches[1];
		} else {
			foreach ($body as $line) {
				if (preg_match('/^' . $markPrefix . '(' . PLUGIN_PARSER_NAME_REGEX . ')\s/i', $line, $matches) && count($matches) >= 1) {
					$result = $matches[1];
					break;
				}
			}
		}
		$currentParser = $result;
		break;

	case 'convert_html':	// マークアップ→HTML変換
		{
			// Wikiテキストにパーサー使用印があるか調べ、あるなら対応パーサーを読み込み変換を実行
			$matches = preg_grep('/^' . $markPrefix . PLUGIN_PARSER_NAME_REGEX . '\s*$/im', $body);
			if (count($matches) > 0) {
				foreach ($matches as $key => $val) unset($body[$key]);
				$body = array_values($body);
				$currentParser = $parser = str_replace($markPrefix, '', trim($val));
				plugin_parser_create_instance($parsers, $currentParser);
				if (isset($parsers[$currentParser])) $result = $parsers[$currentParser]->convertHtml($body, $arg1);
				$currentParser = null;
			}
		}
		break;

	case 'edit_helper':	// テキスト編集ヘルパーを返す
		{
			$files = plugin_parser_makelist('files');
			foreach ($files as $parser => $file) {
				plugin_parser_create_instance($parsers, $parser);
				if (isset($parsers[$parser])) $result .= $parsers[$parser]->editHelper();
			}
		}
		break;
	}

	return $result;
}



// パーサー実装クラスインスタンス生成
function plugin_parser_create_instance(&$parsers, $parser) {
	if (!isset($parsers[$parser])) {
		$file = PLUGIN_PARSER_DIR . $parser . '/converter.inc.php';
		if (file_exists($file)) {
			include_once($file);
			$file = '\\parser\\' . $parser . '\\Converter';
			if (class_exists($file)) $parsers[$parser] = new $file;
			return true;
		}
	}

	return false;
}



// パーサーのリストを作成して返す（引数 $type = 'list'：パーサー名文字列（改行区切り）, 'files'：ファイル名配列）
function plugin_parser_makelist($type = 'files') {
	static $files = null, $list = null;

	if (!$files) {
		$files = array();
		foreach (glob(PLUGIN_PARSER_DIR . '*') as $filename) {
			$filename = basename($filename);
			if (!is_dir(PLUGIN_PARSER_DIR . $filename) || !preg_match('/^(' . PLUGIN_PARSER_NAME_REGEX . ')$/i', $filename)) continue;
			$files[$filename] = PLUGIN_PARSER_DIR . $filename . '/converter.inc.php';
		}
		ksort($files);	// パーサー名でソート
		foreach ($files as $key => $val) $list .= ($list ? "\n" : '') . $key;
	}

	return ($type == 'files')? $files : $list;
}



// パーサー実装クラスインターフェイス（パーサー実装クラス Converter はこれを継承すること）
class PluginParserConverter {
	public function convertHtml(&$lines, $contentID = 0) { return null; }	// マークアップ記法→HTML変換
	public function editHelper() { return null; }	// 編集ヘルパーHTMLを返す
}



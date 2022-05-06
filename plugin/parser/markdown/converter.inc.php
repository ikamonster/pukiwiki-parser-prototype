<?php
// PukiWiki - Yet another WikiWikiWeb clone
// converter.inc.php
// Copyright
//   2002-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// parser.inc.phpプラグイン用変換処理実装クラス
// 対応マークアップ記法：Markdown

namespace parser\markdown;	// 名前空間：必ず「parser\パーサー名（パーサーディレクトリ名と同じ）」とすること

if (!defined('PLUGIN_PARSER_CONVERTER_MARKDOWN_SAFE_MODE'))           define('PLUGIN_PARSER_CONVERTER_MARKDOWN_SAFE_MODE',           1);       // HTMLタグセーフモード（1：有効, 0：無効）
if (!defined('PLUGIN_PARSER_CONVERTER_MARKDOWN_HEADER_LEVEL_REVISE')) define('PLUGIN_PARSER_CONVERTER_MARKDOWN_HEADER_LEVEL_REVISE', 0);       // 見出しレベル補正（1："# "による見出しをPukiWiki記法と同じくh2始まりとする｛※ヘルパーのプレビューと矛盾する｝, 0：補正なし）
if (!defined('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_ENABLE'))   define('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_ENABLE',   1);       // 編集ヘルパー有効（1：有効, 0：無効）
if (!defined('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_HEIGHT'))   define('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_HEIGHT',   '390px'); // 編集ヘルパー領域高さ（空ならデフォルト）
if (!defined('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_JS_URL'))   define('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_JS_URL',   'https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js');  // 編集ヘルパーJavaScript URL
if (!defined('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_CSS_URL'))  define('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_CSS_URL',  'https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css'); // 編集ヘルパーCSS URL



class Converter extends \PluginParserConverter {

	// マークアップ記法→HTML変換
	// 変換処理はm0370氏作「Pukiwiki 1.5.4 Markdown対応版（暫定版）v0.1」（https://github.com/m0370/pukiwiki154_md/releases/tag/v0.1）より流用し改造
	protected	$parsedown = null;
	public function convertHtml(&$lines, $contentID = 0) {
		if (!$this->parsedown) $this->parsedown = new MyParsedown(); // Markdownライブラリ

		$text = '';
		while (!empty($lines)) {
			$line = array_shift($lines);

			if (!PKWKEXP_DISABLE_MULTILINE_PLUGIN_HACK && preg_match('/^#[^{]+(\{\{+)\s*$/', $line, $matches)) {
				// Multiline-enabled block plugin
				$len = strlen($matches[1]);
				$line .= "\r"; // Delimiter
				while (!empty($lines)) {
					$next_line = preg_replace("/[\r\n]*$/", '', array_shift($lines));
					if (preg_match('/\}{' . $len . '}/', $next_line)) {
						$line .= $next_line;
						break;
					} else {
						$line .= $next_line .= "\r"; // Delimiter
					}
				}
				$tmp = Factory_Div($tmp, rtrim($line, "\r\n"));
				if ($tmp) $line = $tmp->toString();
			} else
			if (preg_match('/^#([a-zA-Z0-9_]+)(\\(([^\\)\\n]*)?\\))?/', $line, $matches)) {
				// プラグイン
				$tmp = Factory_Div($tmp, rtrim($line, "\r\n"));
				if ($tmp) $line = $tmp->toString();
			} else
			if (preg_match('/^\!(\[.*\])(\((https?\:\/\/[\-_\.\!\~\*\'\(\)a-zA-Z0-9\;\/\?\:\@\&\=\+\$\,\%\#]+\.)?(jpe?g|png|gif|webp)\))/' . get_preg_u(), $line, $matchimg)) {
				// Markdown記法の画像の場合はmake_linkに渡さない
			} else {
				// $line = preg_replace('/\[(.*?)\]\((https?\:\/\/[\-_\.\!\~\*\'\(\)a-zA-Z0-9\;\/\?\:\@\&\=\+\$\,\%\#]+)( )?(\".*\")?\)/', "[[$1>$2]]", $line); // Markdown式リンクをPukiwiki式リンクに変換
				$line = preg_replace('/\[\[(.+)[\:\>](https?\:\/\/[\-_\.\!\~\*\'\(\)a-zA-Z0-9\;\/\?\:\@\&\=\+\$\,\%\#]+)\]\]/', "[$1]($2)", $line); // Pukiwiki式リンクをMarkdown式リンクに変換
				$line = preg_replace('/\[\#[a-zA-Z0-9]{8}\]$/', "", $line); // Pukiwiki式アンカーを非表示に
				$line = $this->make_link($line);
				// ファイル読み込んだ場合に改行コードが末尾に付いていることがあるので削除
				// 空白は削除しちゃだめなのでrtrim()は使ってはいけない
			}
			$line = str_replace(array("\r\n","\n","\r"), '', $line);

			$text .= $line . "\n";
		}

		$this->parsedown->resetAnchorID($contentID);
		return $this->parsedown
		->setSafeMode(false) // safemode
		->setBreaksEnabled(true) // enables automatic line breaks
		->text($text);
	}



	// 編集ヘルパーHTMLを返す
	public function editHelper() {
		$result = '';

		if (PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_ENABLE) {
			$height = (PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_HEIGHT) ? "maxHeight: '" . PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_HEIGHT . "'" : '';
			$js = PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_JS_URL;
			$css = PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_CSS_URL;
			$result = <<<EOT
			<link rel="stylesheet" href="${css}"/>
			<script src="${js}" defer></script>
			<script><!--'use strict';
				const	__PukiWikiParserMarkdownHelper__ = { textarea: null, instance: null };	// ヘルパー情報（よそと被らない名前にしておく）

				// 編集時のパーサー選択イベント
				document.addEventListener('PukiWikiParserEditHelperChange', function(e) {
					// 自パーサーが選ばれた？
					if (e.detail.parser == 'markdown') {
						// ヘルパー設定
						if (!__PukiWikiParserMarkdownHelper__.textarea) __PukiWikiParserMarkdownHelper__.textarea = document.querySelector(e.detail.textareaSelector);
						if (__PukiWikiParserMarkdownHelper__.textarea && !__PukiWikiParserMarkdownHelper__.instance) {
							__PukiWikiParserMarkdownHelper__.instance = new EasyMDE({
								element: __PukiWikiParserMarkdownHelper__.textarea,
								showIcons: ['table'],
								${height},
								spellChecker: false
							});
						}
					} else {
						// ヘルパー解除
						if (__PukiWikiParserMarkdownHelper__.textarea && __PukiWikiParserMarkdownHelper__.instance) {
							__PukiWikiParserMarkdownHelper__.instance.toTextArea();
							__PukiWikiParserMarkdownHelper__.instance = null;
						}
					}
				}, {passive: true});
			--></script>
			EOT;
		}

		return $result;
	}



	// 各種リンク生成（元関数は lib/make_link.php）
	protected	$inlineConverter = null;
	protected function make_link($string, $page = '') {
		global	$vars;

		if (!$this->inlineConverter) {
			$this->inlineConverter = new MyInlineConverter(array(
				'plugin',        // Inline plugins
				'note',          // Footnotes
			//	'url',           // URLs
			//	'url_interwiki', // URLs (interwiki definition)
			//	'mailto',        // mailto: URL schemes
			//	'interwikiname', // InterWikiNames
				'autoalias',     // AutoAlias
				'autolink',      // AutoLinks
				'bracketname',   // BracketNames
				'wikiname',      // WikiNames
			//	'autoalias_a',   // AutoAlias(alphabet)
			//	'autolink_a',    // AutoLinks(alphabet)
			));
		}

		$clone = $this->inlineConverter->get_clone($this->inlineConverter);

		return $clone->convert($string, ($page != '') ? $page : $vars['page'], PLUGIN_PARSER_CONVERTER_MARKDOWN_SAFE_MODE != 0);
	}

}



// 行変換クラス（親クラスは lib/make_link.php）
class MyInlineConverter extends \InlineConverter {

	// 変換をセーフモードの入切に対応させる
	function convert($string, $page, $safeMode = true)
	{
		$this->page   = $page;
		$this->result = array();

		$string = preg_replace_callback('/' . $this->pattern . '/x' . get_preg_u(),
			array(& $this, 'replace'), $string);

		$arr = explode("\x08", make_line_rules($safeMode ? htmlsc($string) : $string));
		$retval = '';
		while (! empty($arr)) {
			$retval .= array_shift($arr) . array_shift($this->result);
		}
		return $retval;
	}

}



// Markdown→HTML変換ライブラリ「Parsedown」（https://github.com/erusev/parsedown）
if (!class_exists('\\Parsedown')) require_once(__DIR__ . '/vendor/Parsedown.php');

class MyParsedown extends \Parsedown { //Parsedown→ParsedownExtraに変更しても良い

	// アンカーID設定用プロパティを設定
	protected	$myID = 1, $mySerial = 0;
	public function resetAnchorID($id) {
		$myID = $id;
		$mySerial = 0;
	}



	// 見出し生成メソッドをオーバーライドし、PukiWikiプラグイン記述と混同しないよう"#"の後の空白を必須とする。さらにid属性やaタグを追加する
	protected function blockHeader($Line) {
		if (!preg_match('/#+\s+/', $Line['text'])) return; // "#"の後の空白判定

		$Block = parent::blockHeader($Line);
		if (!is_array($Block)) return;

		// id属性とアンカーを設定
		$level = (int)($Block['element']['name'][1]);

		// PukiWiki記法と同じく本文見出しを最大h2から始まるようにレベルを調整
		if (PLUGIN_PARSER_CONVERTER_MARKDOWN_HEADER_LEVEL_REVISE) {
			$level++;
			if ($level > 6) return;
		}

		global $_symbol_anchor;
		$anchorID = $this->myID . '_' . ++$this->mySerial;
		$anchorTag = ($level <= 4 && exist_plugin('aname'))? do_plugin_inline('aname', 'anchor_' . $anchorID . ',super,full,nouserselect', $_symbol_anchor) : '';

		$text = $Block['element']['handler']['argument'];
		$Block['element']['name'] = 'h' . $level . ' id="content_' . $anchorID . '"';
		$Block['element']['handler']['argument'] = $text . $anchorTag;

		return $Block;
	}



	// テーブル生成メソッドをオーバーライドし、tableタグに"style_table"クラス等を追加
	protected function blockTable($Line, array $Block = null) {
		$Block = parent::blockTable($Line, $Block);
		if (is_array($Block)) $Block['element']['name'] .= ' class="style_table" cellspacing="1" border="0"';
		return $Block;
	}

}



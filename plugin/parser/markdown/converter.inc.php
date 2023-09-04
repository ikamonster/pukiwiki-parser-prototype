<?php
// PukiWiki - Yet another WikiWikiWeb clone
// converter.inc.php, v1.0.4 2022 M.Taniguchi
// Copyright
//   2002-2020 PukiWiki Development Team
//   2001-2002 Originally written by yu-ji
// License: GPL v2 or (at your option) any later version
//
// parser.inc.phpプラグイン用変換処理実装クラス
// 対応マークアップ記法：Markdown

namespace parser\markdown;	// 名前空間：必ず「parser\パーサー名（パーサーディレクトリ名と同じ）」とすること

/////////////////////////////////////////////////
// parser.inc.phpプラグイン用Markdownコンバーター設定（plugin/converter.inc.php）
if (!defined('PLUGIN_PARSER_CONVERTER_MARKDOWN_SAFE_MODE'))           define('PLUGIN_PARSER_CONVERTER_MARKDOWN_SAFE_MODE',           1); // HTMLタグセーフモード（1：有効, 0：無効）
if (!defined('PLUGIN_PARSER_CONVERTER_MARKDOWN_HEADER_LEVEL_REVISE')) define('PLUGIN_PARSER_CONVERTER_MARKDOWN_HEADER_LEVEL_REVISE', 1); // 見出しレベル補正（1："# "による見出しをPukiWiki記法と同じくh2始まりとする。表示が矛盾するため、ヘルパーのプレビュー機能は無効となる, 0：補正なし）
if (!defined('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_ENABLE'))   define('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_ENABLE',   1); // 編集ヘルパー有効（1：有効, 0：無効）
if (!defined('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_HEIGHT'))   define('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_HEIGHT',  ''); // 編集ヘルパー領域高さ（空ならデフォルト）
if (!defined('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_JS_URL'))   define('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_JS_URL',   'https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js');  // 編集ヘルパーJavaScript URL
if (!defined('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_CSS_URL'))  define('PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_CSS_URL',  'https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css'); // 編集ヘルパーCSS URL



// 【開発用】各行 変換前・変換後 文字列出力（0：無効, 1：有効）
define('PLUGIN_PARSER_CONVERTER_MARKDOWN_TEST', 0);

class Converter extends \PluginParserConverter {

	// マークアップ記法→HTML変換
	// 変換処理はm0370氏作「Pukiwiki 1.5.4 Markdown対応版（暫定版）v0.1」（https://github.com/m0370/pukiwiki154_md/releases/tag/v0.1）より流用し改造
	protected	$parsedown = null;
	public function convertHtml(&$lines, $contentID = 0) {
		if (!$this->parsedown) $this->parsedown = new MyParsedown(); // Markdownライブラリ

		$text = '';
		$pregU = get_preg_u();
		while (!empty($lines)) {
			$line = array_shift($lines);
			if (PLUGIN_PARSER_CONVERTER_MARKDOWN_TEST) { var_dump(htmlsc($line)); echo "<br/>\n"; }; //【開発用】

			// HTMLエンコード／デコードの影響を受けないよう &amp;,&lt;,&gt; を置換
			$line = str_replace(['&amp;','&lt;','&gt;'], ['iXLNVqVwDrLXix9fRieiFnC6d','dg57zQaB94fkztb44F2DrT4i','mnJ2LKsjgHpSp3hcTzkmVaFa'], $line);

			if (!PKWKEXP_DISABLE_MULTILINE_PLUGIN_HACK && preg_match('/^#[^{]+(\{\{+)\s*$/' . $pregU, $line, $matches)) {
				// Multiline-enabled block plugin
				$len = strlen($matches[1]);
				$line .= "\r"; // Delimiter
				while (!empty($lines)) {
					$next_line = preg_replace("/[\r\n]*$/", '', array_shift($lines));
					if (preg_match('/\}{' . $len . '}/' . $pregU, $next_line)) {
						$line .= $next_line;
						break;
					} else {
						$line .= $next_line .= "\r"; // Delimiter
					}
				}
				$tmp = Factory_Div($tmp, rtrim($line, "\r\n"));
				if ($tmp) $line = $tmp->toString();
			} else
			if (preg_match('/^#([a-zA-Z0-9_]+)(\\(([^\\)\\n]*)?\\))?/' . $pregU, $line, $matches)) {
				// プラグイン
				$tmp = Factory_Div($tmp, rtrim($line, "\r\n"));
				if ($tmp) $line = $tmp->toString();

				// contentsプラグイン対策：contentsプラグインは「<#_contents_>」を返すだけで、目次は最後にlib/convet_htmlが作る仕組み。同様のことをMyParsedownで行う。ここでは処理の都合上タグ形式を避けて置換しておく
				$line = str_replace('<#_contents_>', 'pvy9ym52Crs32q3GAZQ6q92d', $line);
			} else
			if (preg_match('/^\!(\[.*\])(\((https?\:\/\/[\-_\.\!\~\*\'\(\)a-zA-Z0-9\;\/\?\:\@\&\=\+\$\,\%\#]+\.)?(jpe?g|png|gif|webp|avi|bmp|svg)\))/' . $pregU, $line)) {
				// Markdown記法の画像の場合はmake_linkに渡さない
			} else {
				// $line = preg_replace('/\[(.*?)\]\((https?\:\/\/[\-_\.\!\~\*\'\(\)a-zA-Z0-9\;\/\?\:\@\&\=\+\$\,\%\#]+)( )?(\".*\")?\)/', "[[$1>$2]]", $line); // Markdown式リンクをPukiwiki式リンクに変換
				$line = preg_replace('/\[\[(.+)[\:\>](https?\:\/\/[\-_\.\!\~\*\'\(\)a-zA-Z0-9\;\/\?\:\@\&\=\+\$\,\%\#]+)\]\]/', "[$1]($2)", $line); // Pukiwiki式リンクをMarkdown式リンクに変換
				$line = preg_replace('/\[\#[a-zA-Z0-9]{8}\]$/', "", $line); // Pukiwiki式アンカーを非表示に
				$line = $this->make_link($line);

			}

			if (PLUGIN_PARSER_CONVERTER_MARKDOWN_TEST) { var_dump(htmlsc($line)); echo "<br/><br/>\n"; }; //【開発用】

			// ファイル読み込んだ場合に改行コードが末尾に付いていることがあるので削除
			$line = preg_replace('/[\n\r]$/', '', $line);
			$text .= $line . "\n";
		}

		$this->parsedown->resetAnchorID($contentID);
		$text = $this->parsedown
		->setSafeMode(false) // safemode
		->setBreaksEnabled(true) // enables automatic line breaks
		->text($text);

		// 目次生成
		$contents = $this->makeContents($this->parsedown->contents);
		$text = str_replace('pvy9ym52Crs32q3GAZQ6q92d', $contents, $text);
		$text = preg_replace_callback('/hZjr2j2h45kGdruc9Yavy4Ee/' . $pregU, function ($matches) {
			static	$id = 1;
			return $id++;
		}, $text);

		// 置換しておいた &amp;,&lt;,&gt; を表示用に戻す
		$text = str_replace(['iXLNVqVwDrLXix9fRieiFnC6d','dg57zQaB94fkztb44F2DrT4i','mnJ2LKsjgHpSp3hcTzkmVaFa'], ['&amp;amp;','&amp;lt;','&amp;gt;'], $text);

		return $text;
	}



	// 目次生成
	protected function makeContents($table) {
		$contents = '';
		$current = -1;
		forEach ($table[0] as $i => $level) {
			$head = $table[1][$i];
			$anchor = $table[2][$i];

			if ($current > $level) {
				for ($i = $current; $i > $level; --$i) $contents .= "</li>\n</ul>\n";
			} else
			if ($current < $level) {
				for ($i = $current; $i < $level; $i++) $contents .= "\n<ul class=\"list" . ($i + 2) . " list-indent1\">\n";
			} else {
				$contents .= "</li>\n";
			}
			$current = $level;

			$contents .= "<li>";
			$contents .= '<a href="#' . $anchor . '">' . $head . '</a>';
		}
		for ($i = $current; $i > 0; --$i) $contents .= "</li>\n</ul>\n";

		$contents = '<div class="contents"><a id="contents_' . 'hZjr2j2h45kGdruc9Yavy4Ee' . '"></a>' . "\n" . $contents . "\n" . '</div>' . "\n";

		return $contents;
	}



	// 編集ヘルパーHTMLを返す
	public function editHelper() {
		$result = '';

		if (PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_ENABLE) {
			$height = "maxHeight:'" . (PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_HEIGHT ? PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_HEIGHT : '25em') . "'";
			$js = PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_JS_URL;
			$css = PLUGIN_PARSER_CONVERTER_MARKDOWN_EDITHELPER_CSS_URL;
			$preview = PLUGIN_PARSER_CONVERTER_MARKDOWN_HEADER_LEVEL_REVISE ? '' : ",'|','preview'";
			$result = <<<EOT
			<link rel="stylesheet" href="{$css}"/>
			<script src="{$js}" defer></script>
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
								toolbar: ['bold','italic','strikethrough','heading','|','code', 'quote','unordered-list','ordered-list','|','link','image','table','horizontal-rule'{$preview},'|','guide'],
								SideBySideFullscreen: false,
								status: false,
								tabSize: 4,
								unorderedListStyle: '-',
								spellChecker: false,
								nativeSpellcheck: false,
								{$height}
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
		$this->myID = $id;
		$this->mySerial = 0;
		$this->contents = [[], [], []];
	}



	// 見出し生成メソッドをオーバーライドし、PukiWikiプラグイン記述と混同しないよう"#"の後の空白を必須とする。さらにid属性やaタグを追加する
	public	$contents = [[], [], []];	// 目次用テーブル
	protected function blockHeader($Line) {
		if (!preg_match('/#+\s+/', $Line['text'])) return; // "#"の後の空白判定

		$Block = parent::blockHeader($Line);
		if (!is_array($Block)) return;

		// id属性とアンカーを設定
		$level = (int)($Block['element']['name'][1]);
		$level_ = $level - 1;

		// PukiWiki記法と同じく本文見出しを最大h2から始まるようにレベルを調整
		if (PLUGIN_PARSER_CONVERTER_MARKDOWN_HEADER_LEVEL_REVISE) {
			$level++;
			if ($level > 6) return;
		}

		global $_symbol_anchor;
		$anchorID = $this->myID . '_' . ++$this->mySerial;
		$anchorTag = ($level <= 4 && exist_plugin('aname'))? do_plugin_inline('aname', 'anchor_' . $anchorID . ',super,full,nouserselect', $_symbol_anchor) : '';

		$text = $Block['element']['handler']['argument'];
		$anchorID = 'content_' . $anchorID;
		$Block['element']['name'] = 'h' . $level . ' id="' . $anchorID . '"';
		$Block['element']['handler']['argument'] = $text . $anchorTag;

		// 目次用テーブル作成
		$label_ = trim(preg_replace('/^#+\s+/', '', $Line['text']));
		$len_ = count($this->contents[0]);
		$this->contents[0][$len_] = $level_;
		$this->contents[1][$len_] = $label_;
		$this->contents[2][$len_] = $anchorID;

		return $Block;
	}



	// テーブル生成メソッドをオーバーライドし、tableタグに"style_table"クラス等を追加
	protected function blockTable($Line, array $Block = null) {
		$Block = parent::blockTable($Line, $Block);
		if (is_array($Block)) $Block['element']['name'] .= ' class="style_table" cellspacing="1" border="0"';
		return $Block;
	}



	// コードブロック生成メソッドをオーバーライドし、必要に応じてHTMLエスケープを解除する
	protected function blockFencedCodeContinue($Line, $Block) {
		if (isset($Block['complete'])) return;

		if (isset($Block['interrupted'])) {
			$Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);

			unset($Block['interrupted']);
		}

		if (($len = strspn($Line['text'], $Block['char'])) >= $Block['openerLength'] and chop(substr($Line['text'], $len), ' ') === '') {
			$text = substr($Block['element']['element']['text'], 1);
			$Block['element']['element']['text'] = PLUGIN_PARSER_CONVERTER_MARKDOWN_SAFE_MODE ? htmlspecialchars_decode($text) : $text;

			$Block['complete'] = true;

			return $Block;
		}

		$text = $Line['body'];
		$Block['element']['element']['text'] .= "\n" . (PLUGIN_PARSER_CONVERTER_MARKDOWN_SAFE_MODE ? htmlspecialchars_decode($text) : $text);

		return $Block;
	}
	protected function blockCode($Line, $Block = null) {
		if (isset($Block) and $Block['type'] === 'Paragraph' and ! isset($Block['interrupted'])) return;

		if ($Line['indent'] >= 4) {
			$text = substr($Line['body'], 4);

			$Block = array(
				'element' => array(
					'name' => 'pre',
					'element' => array(
						'name' => 'code',
						'text' => (PLUGIN_PARSER_CONVERTER_MARKDOWN_SAFE_MODE ? htmlspecialchars_decode($text) : $text),
					),
				),
			);

			return $Block;
		}
	}
	protected function blockCodeContinue($Line, $Block) {
		if ($Line['indent'] >= 4) {
			if (isset($Block['interrupted'])) {
				$Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);

				unset($Block['interrupted']);
			}

			$Block['element']['element']['text'] .= "\n";

			$text = substr($Line['body'], 4);

			$Block['element']['element']['text'] .= (PLUGIN_PARSER_CONVERTER_MARKDOWN_SAFE_MODE ? htmlspecialchars_decode($text) : $text);

			return $Block;
		}
	}
	protected function inlineCode($Excerpt) {
		$marker = $Excerpt['text'][0];

		if (preg_match('/^(['.$marker.']++)[ ]*+(.+?)[ ]*+(?<!['.$marker.'])\1(?!'.$marker.')/s', $Excerpt['text'], $matches)) {
			$text = $matches[2];
			$text = preg_replace('/[ ]*+\n/', ' ', $text);

			return array(
				'extent' => strlen($matches[0]),
				'element' => array(
					'name' => 'code',
					'text' => (PLUGIN_PARSER_CONVERTER_MARKDOWN_SAFE_MODE ? htmlspecialchars_decode($text) : $text),
				),
			);
		}
	}


}



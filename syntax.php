<?php
/**
 * DokuWiki Rubifier Plugin, Syntax component［青空文庫形式に準じたルビ記法］
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * Provide a ruby annotation of Aozora-Bunko style markup, which is used
 * to indicate the pronunciation or meaning of the corresponding characters.
 * This kind of annotation is often used in Japanese publications.
 * @see also https://www.aozora.gr.jp/aozora-manual/index-input.html#markup
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class syntax_plugin_rubifier extends DokuWiki_Syntax_Plugin {

    protected $mode;
    protected $pattern = [];

    function __construct() {
        $this->mode = substr(get_class($this), 7); // drop 'syntax_' from class name
    }


    function getType() { return 'substition'; }
    function getSort() { return 59; } // < Doku_Parser_Mode_table (=60)

    /**
     * Connect lookup pattern to lexer
     */
    function connectTo($mode) {
        // Japanese syntax pattern
        static $pattern;
        if (!isset($pattern)) {
            // ルビ記法（青空文庫形式）
            // ・開始記号には全角縦棒を使用する
            // ・約物（文章で使用される句読点や記号）を含む語句にルビを振ることはない
            // ・ベースが空白の場合、二重山括弧以降をそのまま出力（ルビ扱いにしない）
            $base[] = '｜[^\n\p{P}]*';

            // ・ルビのかかる部分が漢字だけの場合、縦棒を省略できる
            $base[] = '[\p{Han}仝々〆〇ヶ]+';
            // ・ルビのかかる部分が英字だけで構成される単語の場合、縦棒を省略できる
            $base[] = '\p{Latin}+';

            // ルビテキストの範囲指定には二重山括弧《》を使用する
            $pattern = '(?:'. implode('|', $base) . ')《[^\s》]+》';
        }
        if (in_array('syntax', explode(',', $this->getConf('rubify')))) {
            $this->Lexer->addSpecialPattern($pattern, $mode, $this->mode);
        }

        // ASCII-based alternative syntax pattern
        // ・「全角二重山括弧」《》のかわりに「半角山括弧2回」を使用する
        // ・行頭の「|」がDokuWikiのテーブルマークアップと認識される場合、
        //   バックスラッシュでエスケープする必要がある
        $this->Lexer->addSpecialPattern('\\\\?\|[^\n|<>]*\<\<[^\n<>]+\>\>', $mode, $this->mode.'alt');
        $this->Lexer->mapHandler($this->mode.'alt', $this->mode);
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {

        if (substr($match, -2) == '>>') {
            // 先頭に backslash がある場合 stripslashes() で除去する
            list($base, $text) = explode('<<', substr(stripslashes($match), 1, -2));
        } else {
            list($base, $text) = preg_split('/《/u', $match, 2);
            $base = preg_replace('/\A[｜]++/u', '', $base); // 先頭の縦棒をltrimする
            $text = preg_replace('/[》]++\z/u', '', $text); // 末尾の二重山括弧をrtrimする
        }

        // ルビベースが空白の場合
        if (empty($base)) {
            // ルビテキスト（括弧も含めて）そのまま出力する
            // prepend zero width space(U+200B) action側での置換防止
            $handler->_addCall('cdata', ['​'.'《'.$text.'》'], $pos);
            return false;
        }

        // load helper object
        isset($rubify) || $rubify = $this->loadHelper($this->getPluginName());

        $method = $rubify->parse($text, $annotation);
        return $data = [$base, $annotation, $method];
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $data) {

        list($base, $annotation, $method) = $data;

        // load helper object
        isset($rubify) || $rubify = $this->loadHelper($this->getPluginName());

        if ($format == 'xhtml') {
            // create a ruby annotation
            $renderer->doc .= $rubify->build_html($base, $annotation, $method);
        }
    }
}
// vim:set fileencoding=utf-8 :

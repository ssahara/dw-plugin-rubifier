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

if (!defined('DOKU_INC')) die();

class syntax_plugin_rubifier extends DokuWiki_Syntax_Plugin
{
    protected $rubifier; // helper object

    function __construct()
    {
        // load helper object
        $this->rubifier = $this->loadHelper($this->getPluginName());
    }

    function getType() { return 'substition'; }
    function getSort() { return 59; } // < Doku_Parser_Mode_table (=60)

    /**
     * Connect lookup pattern to lexer
     */
    protected $mode, $pattern;

    function preConnect()
    {
        // syntax mode, drop 'syntax_' from class name
        $this->mode = substr(get_class($this), 7);
    }

    function connectTo($mode)
    {
        $rubify = & $this->rubifier; // helper object

        // Japanese syntax pattern
        if (in_array('syntax', explode(',', $this->getConf('rubify')))) {
            $pattern = $rubify->getPattern($this->mode);
            $this->Lexer->addSpecialPattern($pattern, $mode, $this->mode);
        }

        // ASCII-based alternative syntax pattern
        alternative: {
            $pattern_alt = $rubify->getPattern($this->mode.'alt');
            $this->Lexer->addSpecialPattern($pattern_alt, $mode, $this->mode.'alt');
            $this->Lexer->mapHandler($this->mode.'alt', $this->mode);
        }
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler)
    {
        if (substr($match, -2) == '>>') {
            // 先頭に backslash がある場合 stripslashes() で除去する
            list($base, $text) = explode('<<', stripslashes($match));
            $base = ltrim($base, '|');
            $text = rtrim($text, '>');
        } else {
            list($base, $text) = preg_split('/《/u', $match, 2);
            $base = preg_replace('/\A[｜]++/u', '', $base); // 先頭の縦棒をltrimする
            $text = preg_replace('/[》]++\z/u', '', $text); // 末尾の二重山括弧をrtrimする
        }

        // ルビベースが空白の場合
        if (empty($base)) {
            // ルビテキスト（括弧も含めて）そのまま出力する
            // prepend zero width space(U+200B) action側での置換防止
            $handler->base('​'.'《'.$text.'》', DOKU_LEXER_UNMATCHED, $pos);
            return false;
        }

        $rubify = & $this->rubifier; // helper object

        $method = $rubify->parse($text, $annotation);
        return $data = [$base, $annotation, $method];
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $data)
    {
        list($base, $annotation, $method) = $data;

        $rubify = & $this->rubifier; // helper object

        if ($format == 'xhtml') {
            // create a ruby annotation
            $renderer->doc .= $rubify->build_html($base, $annotation, $method);
        }
    }
}
// vim:set fileencoding=utf-8 :

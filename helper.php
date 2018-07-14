<?php
/**
 * DokuWiki Rubifier Plugin; Helper component［青空文庫形式に準じたルビ記法］
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * Provide a ruby annotation of Aozora-Bunko style markup, which is used
 * to indicate the pronunciation or meaning of the corresponding characters.
 * This kind of annotation is often used in Japanese publications.
 * @see also https://www.aozora.gr.jp/aozora-manual/index-input.html#markup
 *
 * Three methods to treat ruby
 * 1. Mono-ruby
 *    ｜日本語《に.ほん.ご》
 *     <ruby><rb>日<rp>(<rt>に<rp>)<rb>本<rp>(<rt>ほん<rp>)<rb>語<rp>(<rt>ご<rp>)</ruby>
 * 2. Jukugo-ruby
 *    ｜日本語《に,ほん,ご》
 *     <ruby><rb>日<rb>本<rb>語<rp>(<rt>に<rt>ほん<rt>ご<rp>)</ruby>
 * 3. Group-ruby
 *    ｜日本語《にほんご》
 *    <ruby><rb>日本語<rp>(<rt>にほんご<rp>)</ruby>
 *
 * @see also https://www.w3.org/TR/html-ruby-extensions/
 */

if (!defined('DOKU_INC')) die();

class helper_plugin_rubifier extends DokuWiki_Plugin {

    protected $rp;      // parenthetical fallback
    protected $pattern; // ルビ記法（青空文庫形式）search pattern

    function __construct() {
        // get a pair of ruby parentheses
        if (utf8_strlen($this->getConf('parentheses')) > 1) {
            $this->rp[0] = utf8_substr($this->getConf('parentheses'), 0, 1);
            $this->rp[1] = utf8_substr($this->getConf('parentheses'), 1, 1);
        } else {
            $this->rp = array();  // set an empty array
        }
    }


    /**
     * Wikiソーステキストに含まれるルビ記法（青空文庫形式）をHTMLにコンバートする
     * RENDERER_CONTENT_POSTPROCESS イベントで処理する
     */
    function convert($source, $format='xhtml') {

        if (!isset($this->pattern)) {
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
            $this->pattern = '/('. implode('|', $base) . ')'.'《([^\s》]+)》'.'/u';
        }

        $source = preg_replace_callback(
            $this->pattern,
            function ($matches) {
                $base = preg_replace('/\A[｜]++/u', '', $matches[1]);
                $text = $matches[2];
                return $this->build_html($base, $text);
            },
            $source
        );
        return $source;
    }


    /**
     * Tokenise the ruby text component which are separated by period or comma
     * @param string $text  ruby text component
     * @param array $annotation  return separated ruby text component
     * @return string  ruby method (Group-ruby, Mono-ruby, or Jukugo-ruby)
     *
     * ルビテキストを「.」または「,」で分解する。
     * 最初の区切り文字が「.」の場合は Mono-ruby、「,」の場合は Jukugo-rubyとして扱う
     */
    function parse($text, &$annotation=[]) {
        $c = preg_match_all('/([^.,]+)(?:[.,]?)/u', $text, $matches);
        $annotation = $matches[1];
        if ($c > 1) {
            $delimiter = substr($matches[0][0], -1);
            $ret = ($delimiter == '.') ? 'Mono-ruby' : 'Jukugo-ruby';
        } elseif ($c) {
            $ret = 'Group-ruby';
        } else {
            //return false;
            $msg = 'error in rubifier->parse('.$text.')';
            error_log('plugin_'.$this->getPluginName().': '. $msg);
            $ret = false;
        }
        return $ret;
    }


    /**
     * Build html5 ruby annotations
     * Note: omit close tags </rb>, </rt>, </rp> inside a ruby element
     *
     * @param string $base           base component of a ruby annotation
     * @param string or array $text  text component of a ruby annotation
     * @param string $method         method to treat ruby (option)
     * @return string  html of a ruby annotation
     */
    function build_html($base, $text, $method=null) {

        // parenthetical fallback for browsers that don't support ruby annotations
        static $html_rp;
        if (!isset($html_rp)) {
             $html_rp[0] = isset($this->rp[0]) ? '<rp>'.hsc($this->rp[0]) : '';
             $html_rp[1] = isset($this->rp[1]) ? '<rp>'.hsc($this->rp[1]) : '';
        }

        if (!in_array($method, ['Mono-ruby', 'Jukugo-ruby', 'Group-ruby'])) {
            // parse($text, $annotation) の結果、配列 $annotation が返される
            $method = $this->parse($text, $annotation);
        } else {
            // 有効な第3引数が指定されていなかった場合も、$annotation を配列とする
            $annotation = (array) $text; // cast to array
        }

        // ベーステキストが空の場合はルビ扱いにせず、二重山括弧以降をそのまま出力
        if (empty($base)) {
            $text = array_map('hsc', $annotation);
            $html = '《'.implode('', $text).'》';
            return $html;
        }

        // check
        if (in_array($method, ['Mono-ruby', 'Jukugo-ruby'])) {
            // convert special characters to HTML entities
            $base = array_map('hsc', preg_split('//u', $base, -1, PREG_SPLIT_NO_EMPTY));
            $text = array_map('hsc', $annotation);

            if (count($base) != count($text)) {
                // ベース文字数とルビ要素数が一致していない場合、Group-rubyとして扱う
                $msg = 'Wrong '.$method.' ['.implode('-', $text).'] for base ['.$base.']';
                error_log('plugin_'.$this->getPluginName().': '. $msg);
                $method = 'Group-ruby';
                $base = implode('', $base);
                $annotation[0] = implode('-', $text);
            }
        }

        switch ($method) {
            case 'Mono-ruby':
                $html = '';
                for ($i = 0, $c = count($base); $i < $c; $i++) {
                    $html .= '<rb>'.$base[$i] .$html_rp[0];
                    $html .= '<rt>'.$text[$i] .$html_rp[1];
                }
                break;
            case 'Jukugo-ruby':
                $html = '<rb>'.implode('<rb>', $base) .$html_rp[0];
                $html.= '<rt>'.implode('<rt>', $text) .$html_rp[1];
                break;
            case 'Group-ruby':
                $html = '<rb>'.hsc($base)          .$html_rp[0];
                $html.= '<rt>'.hsc($annotation[0]) .$html_rp[1];
                break;
        } // end of switch
        return '<ruby>'.$html.'</ruby>';
    }
}
// vim:set fileencoding=utf-8 :

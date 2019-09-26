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

class helper_plugin_rubifier extends DokuWiki_Plugin
{
    /**
     * ルビ記法（青空文庫形式）の正規表現パターン
     */
    public function getPattern($mode=null) {

        if ($mode == 'plugin_rubifieralt') goto alternative;

        // ルビ記法（青空文庫形式）
        // ・ルビテキストは「全角二重山括弧」《》で囲んで指定する
        // ・ルビベースの開始記号には全角縦棒を使用する
        // ・約物（文章で使用される句読点や記号）を含む語句にルビを振ることはない
        // ・ベースが空白の場合、二重山括弧以降をそのまま出力（ルビ扱いにしない）
        $base[] = '｜[^\n\p{P}]*';

        // ・ルビのかかる部分が漢字だけの場合、縦棒を省略できる
        $base[] = '[\p{Han}仝々〆〇ヶ]+';

        // ・ルビのかかる部分が英字だけで構成される単語の場合、縦棒を省略できる
        $base[] = '\p{Latin}+';

        if ($mode == 'plugin_rubifier') {
            // Syntax component で使用する構文パターン
            $pattern = '(?:'. implode('|', $base) . ')《[^\s》]+》';
        } else {
            // convert() メソッドで使用する検索パターン
            $pattern = '/('. implode('|', $base) . ')'.'《([^\s》]+)》'.'/u';
        }
        return $pattern;

        // ASCII-based alternative syntax pattern
        // ・「全角二重山括弧」《》のかわりに「半角山括弧2回」を使用する
        // ・行頭の「|」がDokuWikiのテーブルマークアップと認識される場合、
        //   バックスラッシュでエスケープする必要がある
        // ・「|」の直後にスペースは入らない
        alternative: {
            $pattern = '(?:'.'\\\\?\|\b[^\n|<>]*'.'|'.'\w+'.')\<\<[^\n<>]+\>\>';
        }
        return $pattern;
    }


    /**
     * Wikiソーステキストに含まれるルビ記法（青空文庫形式）をHTMLにコンバートする
     * RENDERER_CONTENT_POSTPROCESS イベントで処理する
     */
    public function convert($source, $format='xhtml')
    {
        $source = preg_replace_callback(
            $this->getPattern(),
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
    public function parse($text, &$annotation=[])
    {
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
    public function build_html($base, $text, $method=null)
    {
        // parenthetical fallback for browsers that don't support ruby annotations
        static $html_rp;
        if (!isset($html_rp)) {
            if (utf8_strlen($this->getConf('parentheses')) > 1) {
                $rp[0] = utf8_substr($this->getConf('parentheses'), 0, 1);
                $rp[1] = utf8_substr($this->getConf('parentheses'), 1, 1);
            }
             $html_rp[0] = isset($rp[0]) ? '<rp>'.hsc($rp[0]) : '';
             $html_rp[1] = isset($rp[1]) ? '<rp>'.hsc($rp[1]) : '';
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

        // ensure any data output has all HTML special characters converted to HTML entities
        if (in_array($method, ['Mono-ruby', 'Jukugo-ruby'])) {
            // convert special characters to HTML entities
            $html_base = array_map('hsc', preg_split('//u', $base, -1, PREG_SPLIT_NO_EMPTY));
            $html_text = array_map('hsc', $annotation);

            if (count($html_base) != count($html_text)) {
                // ベース文字数とルビ要素数が一致していない場合、Group-rubyとして扱う
                $html_base = implode('', $html_base);
                $html_text = implode('-', $html_text);
                $msg = 'Wrong '.$method.' ['.$html_text.'] for base ['.$html_base.']';
                error_log('plugin_'.$this->getPluginName().': '. $msg);
                $method = 'Group-ruby';
            }
        } else { // 'Group-ruby
            $html_base = hsc($base);
            $html_text = hsc($annotation[0]);
        }

        switch ($method) {
            case 'Mono-ruby':
                $html = '';
                for ($i = 0, $c = count($html_base); $i < $c; $i++) {
                    $html .= '<rb>'.$html_base[$i] .$html_rp[0];
                    $html .= '<rt>'.$html_text[$i] .$html_rp[1];
                }
                break;
            case 'Jukugo-ruby':
                $html = '<rb>'.implode('<rb>', $html_base) .$html_rp[0];
                $html.= '<rt>'.implode('<rt>', $html_text) .$html_rp[1];
                break;
            case 'Group-ruby':
                $html = '<rb>'.$html_base .$html_rp[0];
                $html.= '<rt>'.$html_text .$html_rp[1];
                break;
        } // end of switch
        return '<ruby>'.$html.'</ruby>';
    }
}
// vim:set fileencoding=utf-8 :

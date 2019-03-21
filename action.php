<?php
/**
 * DokuWiki Rubifier Plugin; Action component［青空文庫形式に準じたルビ記法］
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

class action_plugin_rubifier extends DokuWiki_Action_Plugin
{
    /**
     * Registers a callback function for a given event
     */
    function register(Doku_Event_Handler $controller)
    {
        if (in_array('action', explode(',', $this->getConf('rubify')))) {
            $controller->register_hook(
                'RENDERER_CONTENT_POSTPROCESS', 'AFTER', $this, '_rubify'
            );
        }
    }

    /**
     * RENDERER_CONTENT_POSTPROCESS
     * Convert rubi-syntax to HTML5 ruby annotation
     * 青空文庫風のルビ記法を実現する。見出し文字列にも適用されます
     */
    function _rubify(Doku_Event $event)
    {
        // load helper object
        isset($rubify) || $rubify = $this->loadHelper($this->getPluginName());

        // check the format of the renderer output
        if ($event->data[0] == 'xhtml') {
            // The <ruby> tag is new in HTML5.
            $event->data[1] = $rubify->convert($event->data[1]);
        }
        return;
    }

}
// vim:set fileencoding=utf-8 :

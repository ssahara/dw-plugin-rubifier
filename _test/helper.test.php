<?php
/**
 * Tests to ensure rubifier
 *
 * @group plugin_rubifier
 * @group plugins
 */
class plugin_rubifier_units_test extends DokuWikiTest {

    function setup() {
        $this->pluginsEnabled[] = 'rubifier';
        parent::setup();
    }


    function test_parse() {
        $rubifier = new helper_plugin_rubifier();

        $text = 'に.ほん.ご';
        $expected_method = 'Mono-ruby';
        $expected_annotation = ['に','ほん','ご'];
        $this->assertEquals($rubifier->parse($text, $annotation), $expected_method);
        $this->assertEquals($annotation, $expected_annotation);

        $text = 'に,ほん.ご';
        $expected_method = 'Jukugo-ruby';
        $expected_annotation = ['に','ほん','ご'];
        $this->assertEquals($rubifier->parse($text, $annotation), $expected_method);
        $this->assertEquals($annotation, $expected_annotation);

        $text = 'にほんご';
        $expected_method = 'Group-ruby';
        $expected_annotation = ['にほんご'];
        $this->assertEquals($rubifier->parse($text, $annotation), $expected_method);
        $this->assertEquals($annotation, $expected_annotation);
        
    }

    function test_build_html() {
        $rubifier = new helper_plugin_rubifier();

        $base = '日本語';
        $text = 'に,ほん.ご';
        $expected = '<ruby><rb>日<rb>本<rb>語<rp>(<rt>に<rt>ほん<rt>ご<rp>)</ruby>';

        $this->assertEquals($rubifier->build_html($base, $text), $expected);
    }

    function test_convert() {
        $rubifier = new helper_plugin_rubifier();

        $source = '｜日本語《にほんご》';
        $expected = '<ruby><rb>日本語<rp>(<rt>にほんご<rp>)</ruby>';

        $this->assertEquals($rubifier->convert($source), $expected);
    }
}
// vim:set fileencoding=utf-8 :

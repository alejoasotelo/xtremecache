<?php

abstract class Controller extends ControllerCore
{

    /*
    * module: xtremecache
    * date: 2017-08-09 17:41:58
    * version: 1.0.7
    */
    protected function smartyOutputContent($content)
    {
        $this->context->cookie->write();

        $html = '';
        $js_tag = 'js_def';
        $this->context->smarty->assign($js_tag, $js_tag);

        if (is_array($content)) {
            foreach ($content as $tpl) {
                $html = $this->context->smarty->fetch($tpl);
            }
        } else {
            $html = $this->context->smarty->fetch($content);
        }

        $html = trim($html);

        if (in_array($this->controller_type, array('front', 'modulefront')) && !empty($html) && $this->getLayout()) {
            $live_edit_content = '';
            if (!$this->useMobileTheme() && $this->checkLiveEditAccess()) {
                $live_edit_content = $this->getLiveEditFooter();
            }

            $dom_available = extension_loaded('dom') ? true : false;
            $defer = (bool)Configuration::get('PS_JS_DEFER');

            if ($defer && $dom_available) {
                $html = Media::deferInlineScripts($html);
            }
            $html = trim(str_replace(array('</body>', '</html>'), '', $html))."\n";

            $this->context->smarty->assign(array(
                $js_tag => Media::getJsDef(),
                'js_files' => array_unique($this->js_files),
                'js_inline' => $dom_available ? Media::getInlineScript() : array()
            ));

            $javascript = $this->context->smarty->fetch(_PS_ALL_THEMES_DIR_.'javascript.tpl');

            $output = '';
            if ($defer && (!isset($this->ajax) || ! $this->ajax)) {
                $output = $html.$javascript;
            } else {
                $output = preg_replace('/(?<!\$)'.$js_tag.'/', $javascript, $html);
            }

            $output .= $live_edit_content.((!isset($this->ajax) || ! $this->ajax) ? '</body></html>' : '');

            Hook::exec('actionRequestComplete', array(
                'controller' => $this,
                'output' => $output
            ));

            echo $output;
        } else {
            Hook::exec('actionRequestComplete', array(
                'controller' => $this,
                'output' => $html
            ));

            echo $html;
        }
    }
}

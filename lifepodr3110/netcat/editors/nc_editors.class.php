<?php

class nc_Editors {
    protected $core, $editor_id;
    protected $editor, $html;
    // имя textarea и значение
    protected $name, $value;

    protected int $panel = 0;

    public function  __construct($editor_id, $name, $value = '', $panel = 0) {
        $this->core = nc_Core::get_object();
        $this->name = $name;
        $this->value = $value;
        $this->panel = $panel;

        $this->_make_ckeditor4();
    }

    public function get_html() {
        return $this->html;
    }

    protected function _make_ckeditor4() {
        if (!class_exists('CKEditor')) {
            include_once $this->core->ROOT_FOLDER . 'editors/ckeditor4/ckeditor.php';
        }

        $this->editor = new CKEditor($this->name, $this->value, $this->panel);
        $this->html = $this->editor->CreateHtml();
    }
}
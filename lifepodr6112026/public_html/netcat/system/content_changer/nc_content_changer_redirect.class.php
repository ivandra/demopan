<?php

class nc_content_changer_redirect extends nc_content_changer {

    /**
     * @inheritDoc
     */
    protected function validate() {
        /** @var nc_content_changer_redirect_instruction $instruction */
        $instruction = $this->instruction;

        $old_url = $instruction->get_old_url();
        $new_url = $instruction->get_new_url();
        $status_code = $instruction->get_status_code();

        // Валидация обязательных полей
        if (empty($old_url) || empty($new_url)) {
            return false;
        }

        // Валидация статус кода
        if (!in_array($status_code, [301, 302])) {
            return false;
        }

        // Для действия create проверяем уникальность old_url
        if ($this->action === 'create') {
            $existing_redirect = new nc_redirect();
            if ($existing_redirect->load_where('old_url', $old_url) && $existing_redirect->get_id()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function do_create() {
        /** @var nc_content_changer_redirect_instruction $instruction */
        $instruction = $this->instruction;

        $redirect = new nc_redirect();
        $redirect->set_values_from_form([
            'id' => null,
            'old_url' => $instruction->get_old_url(),
            'new_url' => $instruction->get_new_url(),
            'header' => $instruction->get_status_code(),
            'group' => 1,
            'checked' => 1,
        ]);

        if ($redirect->validate()) {
            return $redirect->save();
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function do_delete() {
        /** @var nc_content_changer_redirect_instruction $instruction */
        $instruction = $this->instruction;

        $redirect = new nc_redirect();
        $redirect->load_where('old_url', $instruction->get_old_url());

        if ($redirect->get_id()) {
            return $redirect->delete();
        }

        return false;
    }

    /**
     * @return bool
     */
    protected function do_update() {
        // Update не предусмотрен в текущих требованиях, но оставляем заглушку
        return false;
    }
}

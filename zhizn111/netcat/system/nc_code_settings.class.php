<?php

class nc_code_settings {

    private static $allowed_tags = ['div', 'section', 'article', 'aside', 'header', 'footer', 'nav', 'main', 'figure'];

    /**
     * @param string|null $json
     * @return array{tag: string, class_attr: string, style_attr: string, id: string|null}
     */
    public static function parse($json) {
        $settings = json_decode($json ?: '', true) ?: [];
        $settings = self::sanitize($settings);

        $class_attr = !empty($settings['class']) ? ' ' . $settings['class'] : '';
        $style_attr = !empty($settings['style']) ? ' style="' . htmlspecialchars($settings['style'], ENT_QUOTES, 'UTF-8') . '"' : '';
        $inner_class_attr = !empty($settings['inner_class']) ? ' ' . $settings['inner_class'] : '';
        $list_class_attr = !empty($settings['list_class']) ? ' ' . $settings['list_class'] : '';

        return [
            'tag' => $settings['tag'] ?? 'div',
            'class_attr' => $class_attr,
            'style_attr' => $style_attr,
            'id' => !empty($settings['id']) ? $settings['id'] : null,
            'inner_class_attr' => $inner_class_attr,
            'list_class_attr' => $list_class_attr,
        ];
    }

    /**
     * @param array $settings
     * @return array
     */
    public static function sanitize(array $settings) {
        $result = [];

        if (isset($settings['tag'])) {
            $tag = strtolower(trim($settings['tag']));
            $result['tag'] = in_array($tag, self::$allowed_tags, true) ? $tag : 'div';
        }

        if (isset($settings['class'])) {
            $result['class'] = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $settings['class']);
        }

        if (isset($settings['style'])) {
            $result['style'] = (string)$settings['style'];
        }

        if (isset($settings['id'])) {
            $result['id'] = preg_replace('/[^a-zA-Z0-9\-_]/', '', $settings['id']);
        }

        if (isset($settings['inner_class'])) {
            $result['inner_class'] = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $settings['inner_class']);
        }

        if (isset($settings['list_class'])) {
            $result['list_class'] = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $settings['list_class']);
        }

        return $result;
    }
}

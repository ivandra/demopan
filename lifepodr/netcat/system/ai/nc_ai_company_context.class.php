<?php

class nc_ai_company_context {

    private array $context_data = [];


    public static function getFromCurrentSite(): nc_ai_company_context {
        $context_data = nc_core::get_object()->catalogue->get_current('AiContextData');

        return new self(json_decode($context_data ?? '[]', true) ?? []);
    }

    public function __construct(array $context_data) {
        $this->context_data = empty($context_data) ? $this->to_array() : $context_data;
    }

    public function get_company_name() {
        return $this->get('company_name') ?? '';
    }

    public function get_company_description(): string {
        return $this->get('company_description') ?? '';
    }

    public function get_target_audience(): string {
        return $this->get('target_audience') ?? '';
    }

    public function get_advantages(): ?string {
        return is_array($this->get('advantages')) ? implode(', ', $this->get('advantages')) : $this->get('advantages');
    }

    public function get_services(): ?string {
        return is_array($this->get('services')) ? implode(', ', $this->get('services')) : $this->get('services');
    }

    public function get_feelings(): ?string {
        return is_array($this->get('feelings')) ? implode(', ', $this->get('feelings')) : $this->get('feelings');
    }

    public function set_company_name(string $company_name): nc_ai_company_context {
        return $this->set('company_name', $company_name);
    }

    public function set_company_description(string $company_description): nc_ai_company_context {
        return $this->set('company_description', $company_description);
    }

    public function set_target_audience(string $target_audience): nc_ai_company_context {
        return $this->set('target_audience', $target_audience);
    }

    public function set_services(string $services): nc_ai_company_context {
        return $this->set('services', $services);
    }

    public function set_advantages(string $advantages): nc_ai_company_context {
        return $this->set('advantages', $advantages);
    }

    public function set_feelings(string $feelings): nc_ai_company_context {
        return $this->set('feelings', $feelings);
    }

    private function set(string $property, $value): nc_ai_company_context {
        $this->context_data[$property] = $value;
        return $this;
    }

    public function update() {
        nc_db_table::make('Catalogue')
                   ->where('Catalogue_ID', nc_core::get_object()->catalogue->get_current('Catalogue_ID'))
                   ->update(['AiContextData' => nc_array_json($this->context_data)]);
    }

    public function to_array(): array {
        return [
            'company_name' => $this->get_company_name(),
            'company_description' => $this->get_company_description(),
            'target_audience' => $this->get_target_audience(),
            'advantages' => $this->get_advantages(),
            'services' => $this->get_services(),
            'feelings' => $this->get_feelings(),
        ];
    }

    /**
     * @return mixed|string|null
     */
    private function get(string $property) {
        if (empty($this->context_data[$property])) {
            return null;
        }
        $prop_context = is_array($this->context_data[$property]) ? implode(', ', $this->context_data[$property]) : $this->context_data[$property];
        return isset($prop_context) ? trim($prop_context) : null;
    }


}

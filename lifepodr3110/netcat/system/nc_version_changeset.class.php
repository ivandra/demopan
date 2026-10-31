<?php

class nc_version_changeset extends nc_record {

    protected $mapping = false;
    protected $primary_key = 'Version_Changeset_ID';
    protected $table_name = 'Version_Changeset';

    protected $properties = array(
        'Version_Changeset_ID' => null,
        'ChangeCount' => null,
        'Description' => null,
    );

    /**
     * @param string $order ASC или DESC
     * @return nc_version_record_collection
     */
    public function get_records($order = 'DESC') {
        return nc_version_record_collection::load_records(
            "SELECT * FROM `%t%` WHERE `Version_Changeset_ID` = " . (int)$this->get_id() .
            " ORDER BY `Version_ID` " . $order
        );
    }

    public function restore() {
        // ASC: сначала инфоблоки, потом объекты (родители перед детьми)
        return $this->get_records('ASC')->restore();
    }

    public function undo() {
        // DESC: сначала объекты, потом инфоблоки (дети перед родителями)
        return $this->get_records('DESC')->undo();
    }

}

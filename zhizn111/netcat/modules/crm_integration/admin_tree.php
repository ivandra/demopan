<?php

if (empty($NETCAT_FOLDER)) {
    $NETCAT_FOLDER = realpath(__DIR__ . "/../../..") . DIRECTORY_SEPARATOR;
    require_once $NETCAT_FOLDER . "vars.inc.php";
    require_once $ADMIN_FOLDER . "function.inc.php";
}

$module_node_id = "module-" . $module["Module_ID"];

// Return path to current section
if (nc_core::get_object()->input->fetch_get("action") == "get_path") {
    echo nc_array_json(array($module_node_id));
    exit;
}

$children_nodes = array();

/** @var Permission $perm */
global $perm;

if ($node_type === "module") {
    // Службы (Providers list)
    $children_nodes[] = array(
        "nodeId" => "crm_integration-providers",
        "parentNodeId" => $module_node_id,
        "name" => NETCAT_MODULE_CRM_INTEGRATION_PROVIDERS,
        "href" => "#module.crm_integration",
        "sprite" => "settings",
        "hasChildren" => false,
    );

    // Источники лидов (Lead sources)
    $children_nodes[] = array(
        "nodeId" => "crm_integration-sources",
        "parentNodeId" => $module_node_id,
        "name" => NETCAT_MODULE_CRM_INTEGRATION_SOURCES,
        "href" => "#module.crm_integration.sources",
        "sprite" => "folder-dark",
        "hasChildren" => false,
    );

    // Журнал событий (Event log)
    $children_nodes[] = array(
        "nodeId" => "crm_integration-log",
        "parentNodeId" => $module_node_id,
        "name" => NETCAT_MODULE_CRM_INTEGRATION_LOG,
        "href" => "#module.crm_integration.log",
        "sprite" => "mod-logging",
        "hasChildren" => false,
    );
}

echo nc_array_json($children_nodes);

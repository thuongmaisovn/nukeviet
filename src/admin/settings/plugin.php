<?php

/**
 * @Project NUKEVIET 4.x
 * @Author VINADES.,JSC <contact@vinades.vn>
 * @Copyright (C) 2014 VINADES.,JSC. All rights reserved
 * @License GNU/GPL version 2 or any later version
 * @Createdate 28/10/2012, 14:51
 */

if (!defined('NV_IS_FILE_SETTINGS')) {
    die('Stop!!!');
}

$pattern_plugin = '/^([a-zA-Z0-9\_]+)\.php$/';
$page_title = $nv_Lang->getModule('plugin');

$xtpl = new XTemplate($op . '.tpl', NV_ROOTDIR . '/themes/' . $global_config['module_theme'] . '/modules/' . $module_file);
$xtpl->assign('LANG', \NukeViet\Core\Language::$lang_module);
$xtpl->assign('GLANG', \NukeViet\Core\Language::$lang_global);
$xtpl->assign('NV_BASE_ADMINURL', NV_BASE_ADMINURL);
$xtpl->assign('NV_NAME_VARIABLE', NV_NAME_VARIABLE);
$xtpl->assign('MODULE_NAME', $module_name);
$xtpl->assign('NV_OP_VARIABLE', NV_OP_VARIABLE);
$xtpl->assign('OP', $op);

// Lấy list các HOOK theo module hoặc hệ thống
$sql = "SELECT DISTINCT plugin_area, hook_module FROM " . $db_config['prefix'] . "_plugin WHERE plugin_lang='all' OR plugin_lang='" . NV_LANG_DATA . "'";
$result = $db->query($sql);
$array_plugin_area = array();
while ($row = $result->fetch()) {
    $_key = (empty($row['hook_module']) ? '' : $row['hook_module'] . ':') . $row['plugin_area'];
    $array_plugin_area[$_key] = $_key;
}

// Tìm kiếm
$array_search = array(
    'plugin_area' => $nv_Request->get_title('a', 'get', ''),
    's_plugin_area' => '',
    's_hook_module' => ''
);
if (!empty($array_search['plugin_area']) and !isset($array_plugin_area[$array_search['plugin_area']])) {
    nv_redirect_location(NV_BASE_ADMINURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name . '&' . NV_OP_VARIABLE . '=' . $op . '&rand=' . nv_genpass());
}

// Xuất các plugin trong CSDL
$max_weight = 0;
$sql = "SELECT * FROM " . $db_config['prefix'] . "_plugin WHERE plugin_lang='all' OR plugin_lang='" . NV_LANG_DATA . "'";
if (!empty($array_search['plugin_area'])) {
    // Xử lý lại phần tìm kiếm
    $_s_plugin_area = explode(':', $array_search['plugin_area']);
    if (isset($_s_plugin_area[1])) {
        $array_search['s_plugin_area'] = $_s_plugin_area[1];
        $array_search['s_hook_module'] = $_s_plugin_area[0];
    } else {
        $array_search['s_plugin_area'] = $array_search['plugin_area'];
    }

    $sql .= " ORDER BY weight ASC";
    $max_weight = $db->query('SELECT MAX(weight) FROM ' . $db_config['prefix'] . '_plugin WHERE (plugin_lang=\'all\' OR plugin_lang=\'' . NV_LANG_DATA . '\') AND plugin_area=' . $db->quote($array_search['s_plugin_area']) . ' AND hook_module=' . $db->quote($array_search['s_hook_module']))->fetchColumn();
    $xtpl->parse('main.col_weight');
} else {
    $sql .= " ORDER BY hook_module ASC, plugin_area ASC";
}
$result = $db->query($sql);
$array_plugin_db = array();

while ($row = $result->fetch()) {
    $key = ($row['hook_module'] ? $site_mods[$row['hook_module']]['module_file'] : '__') . ':' . $row['plugin_area'] . ':' . ($row['plugin_module_name'] ? $site_mods[$row['plugin_module_name']]['module_file'] : '__');
    if (empty($array_search['plugin_area']) or ($array_search['s_plugin_area'] == $row['plugin_area'] and $array_search['s_hook_module'] == $row['hook_module'])) {
        $row['hook_module'] = empty($row['hook_module']) ? '' : ($row['hook_module'] . ':');
        $xtpl->assign('ROW', $row);

        if (!empty($array_search['plugin_area'])) {
            for ($i = 1; $i <= $max_weight; $i++) {
                $weight = array(
                    'key' => $i,
                    'title' => $i,
                    'selected' => $i == $row['weight'] ? ' selected="selected"' : ''
                );
                $xtpl->assign('WEIGHT', $weight);
                $xtpl->parse('main.loop.weight.loop');
            }
            $xtpl->parse('main.loop.weight');
        }

        if (empty($row['plugin_module_name'])) {
            $xtpl->parse('main.loop.type_sys');
        } else {
            $xtpl->parse('main.loop.type_module');
        }
        if (empty($row['plugin_module_file'])) {
            $xtpl->parse('main.loop.delete');
        }

        $xtpl->parse('main.loop');
    }
    if (isset($array_plugin_db[$key])) {
        $array_plugin_db[$key] += 1;
    } else {
        $array_plugin_db[$key] = 1;
    }
}

foreach ($array_plugin_area as $plugin_area) {
    $xtpl->assign('PLUGIN_AREA_SELECTED', $plugin_area == $array_search['plugin_area'] ? ' selected="selected"' : '');
    $xtpl->assign('PLUGIN_AREA', $plugin_area);
    $xtpl->parse('main.plugin_area');
}

// Chuyển module của site theo dạng module_file để xác định số plugin
$array_plstat_bymod = array();
foreach ($site_mods as $mtitle => $mvalue) {
    if (isset($array_plstat_bymod[$mvalue['module_file']])) {
        $array_plstat_bymod[$mvalue['module_file']] += 1;
    } else {
        $array_plstat_bymod[$mvalue['module_file']] = 1;
    }
}

// Xuất các plugin chưa thêm vào CSDL
$file_plugins = nv_scandir(NV_ROOTDIR . '/includes/plugin', $pattern_plugin);
$available_plugins = array();

foreach ($file_plugins as $_plugin) {
    $pl_area = nv_get_plugin_area(NV_ROOTDIR . '/includes/plugin/' . $_plugin);
    $pl_hook_module = nv_get_hook_require(NV_ROOTDIR . '/includes/plugin/' . $_plugin);
    $pl_receive_module = nv_get_hook_revmod(NV_ROOTDIR . '/includes/plugin/' . $_plugin);

    if (isset($pl_area[0])) {
        $_key = (empty($pl_hook_module) ? '__' : $pl_hook_module) . ':' . $pl_area[0] . ':' . (empty($pl_receive_module) ? '__' : $pl_receive_module);
        $_remaining = 1;
        if (!empty($array_plstat_bymod[$pl_receive_module]) and !empty($array_plstat_bymod[$pl_hook_module])) {
            $_remaining = $array_plstat_bymod[$pl_receive_module] * $array_plstat_bymod[$pl_hook_module];
        }
        if (!isset($array_plugin_db[$_key]) or $array_plugin_db[$_key] < $_remaining) {
            $available_plugins[$_key] = array(
                'file' => $_plugin,
                'area' => $pl_area,
                'hook_module' => $pl_hook_module,
                'receive_module' => $pl_receive_module
            );
        }
    }
}

// Tích hợp plugin mới
if ($nv_Request->isset_request('plugin_file', 'get')) {
    $plugin_file = $nv_Request->get_title('plugin_file', 'get');
    if (!in_array($plugin_file, $file_plugins)) {
        nv_redirect_location(NV_BASE_ADMINURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name . '&' . NV_OP_VARIABLE . '=' . $op . '&rand=' . nv_genpass());
    }
    $pl_area = nv_get_plugin_area(NV_ROOTDIR . '/includes/plugin/' . $plugin_file);
    $pl_hook_module = nv_get_hook_require(NV_ROOTDIR . '/includes/plugin/' . $plugin_file);
    $pl_receive_module = nv_get_hook_revmod(NV_ROOTDIR . '/includes/plugin/' . $plugin_file);
    if (sizeof($pl_area) != 1) {
        nv_redirect_location(NV_BASE_ADMINURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name . '&' . NV_OP_VARIABLE . '=' . $op . '&rand=' . nv_genpass());
    }
    $_key = (empty($pl_hook_module) ? '__' : $pl_hook_module) . ':' . $pl_area[0] . ':' . (empty($pl_receive_module) ? '__' : $pl_receive_module);
    if (!isset($available_plugins[$_key])) {
        nv_redirect_location(NV_BASE_ADMINURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name . '&' . NV_OP_VARIABLE . '=' . $op . '&rand=' . nv_genpass());
    }

    // Thiết lập plugin module
    if (!empty($available_plugins[$_key]['receive_module'])) {
        $page_title .= ': ' . $plugin_file;
        $xtpl->assign('FORM_ACTION', NV_BASE_ADMINURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&amp;' . NV_NAME_VARIABLE . '=' . $module_name . '&amp;' . NV_OP_VARIABLE . '=' . $op . '&amp;plugin_file=' . $plugin_file . '&amp;rand=' . nv_genpass());

        // Xác định module nguồn và module đích
        $array_hook_mods = $array_receive_mods = array();
        foreach ($site_mods as $mod_title => $mod_data) {
            if (!empty($available_plugins[$_key]['hook_module']) and $mod_data['module_file'] == $available_plugins[$_key]['hook_module']) {
                $array_hook_mods[$mod_title] = array(
                    'key' => $mod_title,
                    'title' => $mod_title . ' (' . $mod_data['custom_title'] . ')',
                    'selected' => ''
                );
            }
            if ($mod_data['module_file'] == $available_plugins[$_key]['receive_module']) {
                $array_receive_mods[$mod_title] = array(
                    'key' => $mod_title,
                    'title' => $mod_title . ' (' . $mod_data['custom_title'] . ')',
                    'selected' => ''
                );
            }
        }

        $error = $plugin_hook_module = $plugin_module_name = '';
        $plugin_lang = NV_LANG_DATA;
        $is_submit = false;

        if ($nv_Request->isset_request('submit', 'post')) {
            $is_submit = true;
            $plugin_hook_module = $nv_Request->get_title('hook_module', 'post', '');
            $plugin_module_name = $nv_Request->get_title('receive_module', 'post', '');

            // Kiểm tra tồn tại
            $sql = 'SELECT pid FROM ' . $db_config['prefix'] . '_plugin WHERE plugin_file=' . $db->quote($plugin_file) . ' AND plugin_module_file=\'\' AND plugin_module_name=' . $db->quote($plugin_module_name) . ' AND hook_module=' . $db->quote($plugin_hook_module);
            $is_exists = $db->query($sql)->fetchColumn();

            if (!isset($array_hook_mods[$plugin_hook_module])) {
                $error = $nv_Lang->getModule('plugin_error_exists_module', $plugin_hook_module);
            } elseif (!isset($array_receive_mods[$plugin_module_name])) {
                $error = $nv_Lang->getModule('plugin_error_exists_module', $plugin_module_name);
            } elseif ($is_exists) {
                $error = $nv_Lang->getModule('plugin_error_exists');
            }
        }

        if (!$is_submit or $error) {
            if (!empty($error)) {
                $xtpl->assign('ERROR', $error);
                $xtpl->parse('setting.error');
            }

            $submit_allowed = true;
            // Xuất module hook
            if (!empty($available_plugins[$_key]['hook_module'])) {
                foreach ($array_hook_mods as $hook_module) {
                    $hook_module['selected'] = $hook_module['key'] == $plugin_hook_module ? ' selected="selected"' : '';
                    $xtpl->assign('HOOK_MODULE', $hook_module);
                    $xtpl->parse('setting.hook_module.loop');
                }
                if ($array_hook_mods) {
                    $xtpl->parse('setting.hook_module');
                } else {
                    $xtpl->assign('NO_HOOK_MODULE', $nv_Lang->getModule('plugin_error_no_hook', $available_plugins[$_key]['hook_module'], $available_plugins[$_key]['hook_module']));
                    $xtpl->parse('setting.no_hook_module');
                    $submit_allowed = false;
                }
            }
            foreach ($array_receive_mods as $receive_module) {
                $receive_module['selected'] = $receive_module['key'] == $plugin_module_name ? ' selected="selected"' : '';
                $xtpl->assign('RECEIVE_MODULE', $receive_module);
                $xtpl->parse('setting.receive_module.loop');
            }
            if ($array_receive_mods) {
                $xtpl->parse('setting.receive_module');
            } else {
                $xtpl->assign('NO_RECEIVE_MODULE', $nv_Lang->getModule('plugin_error_no_receive', $available_plugins[$_key]['receive_module'], $available_plugins[$_key]['receive_module']));
                $xtpl->parse('setting.no_receive_module');
                $submit_allowed = false;
            }

            if ($submit_allowed) {
                $xtpl->parse('setting.submit');
            }

            $xtpl->parse('setting');
            $contents = $xtpl->text('setting');

            include NV_ROOTDIR . '/includes/header.php';
            echo nv_admin_theme($contents);
            include NV_ROOTDIR . '/includes/footer.php';
        }
    } else {
        $plugin_hook_module = '';
        $plugin_module_name = '';
        $plugin_lang = 'all';
    }

    $plugin_area = $available_plugins[$_key]['area'][0];

    // Lấy vị trí mới
    $_sql = 'SELECT max(weight) FROM ' . $db_config['prefix'] . '_plugin WHERE plugin_lang=' . $db->quote($plugin_lang) . ' AND plugin_area=' . $db->quote($plugin_area) . ' AND hook_module=' . $db->quote($plugin_hook_module);
    $weight = $db->query($_sql)->fetchColumn();
    $weight = intval($weight) + 1;

    try {
        $sth = $db->prepare('INSERT INTO ' . $db_config['prefix'] . '_plugin (
            plugin_lang, plugin_file, plugin_area, plugin_module_name, hook_module, weight
        ) VALUES (
            :plugin_lang, :plugin_file, :plugin_area, :plugin_module_name, :hook_module, :weight
        )');
        $sth->bindParam(':plugin_lang', $plugin_lang, PDO::PARAM_STR);
        $sth->bindParam(':plugin_file', $plugin_file, PDO::PARAM_STR);
        $sth->bindParam(':plugin_area', $plugin_area, PDO::PARAM_STR);
        $sth->bindParam(':plugin_module_name', $plugin_module_name, PDO::PARAM_STR);
        $sth->bindParam(':hook_module', $plugin_hook_module, PDO::PARAM_STR);
        $sth->bindParam(':weight', $weight, PDO::PARAM_INT);
        $sth->execute();

        nv_save_file_config_global();
    } catch (PDOException $e) {
        trigger_error($e->getMessage());
    }

    nv_redirect_location(NV_BASE_ADMINURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&' . NV_NAME_VARIABLE . '=' . $module_name . '&' . NV_OP_VARIABLE . '=' . $op . '&rand=' . nv_genpass());
}

// Xóa plugin
if ($nv_Request->isset_request('del', 'post')) {
    if (!defined('NV_IS_AJAX')) {
        die('Wrong URL!!!');
    }

    $pid = $nv_Request->get_int('pid', 'post', 0);

    if ($pid > 0) {
        $row = $db->query('SELECT * FROM ' . $db_config['prefix'] . '_plugin WHERE pid=' . $pid)->fetch();
        if (!empty($row) and empty($row['plugin_module_file']) and ($row['plugin_lang'] == 'all' or $row['plugin_lang'] == NV_LANG_DATA) and $db->exec('DELETE FROM ' . $db_config['prefix'] . '_plugin WHERE pid = ' . $pid)) {
            $weight = intval($row['weight']);
            $_query = $db->query('SELECT pid FROM ' . $db_config['prefix'] . '_plugin WHERE (plugin_lang=' . $db->quote(NV_LANG_DATA) . ' OR plugin_lang=\'all\') AND plugin_area=' . $db->quote($row['plugin_area']) . ' AND hook_module=' . $db->quote($row['hook_module']) . ' AND weight > ' . $weight . ' ORDER BY weight ASC');
            while (list ($pid) = $_query->fetch(3)) {
                $db->query('UPDATE ' . $db_config['prefix'] . '_plugin SET weight = ' . $weight++ . ' WHERE pid=' . $pid);
            }

            nv_save_file_config_global();
            nv_htmlOutput('OK');
        }
    }
    nv_htmlOutput('ERROR');
}

// Thay đổi thứ tự ưu tiên
if ($nv_Request->isset_request('changeweight', 'post')) {
    if (!defined('NV_IS_AJAX')) {
        die('Wrong URL!!!');
    }

    $pid = $nv_Request->get_int('pid', 'post', 0);
    $new_weight = $nv_Request->get_int('new_weight', 'post', 0);

    if ($pid > 0) {
        $row = $db->query('SELECT * FROM ' . $db_config['prefix'] . '_plugin WHERE pid=' . $pid)->fetch();
        if (!empty($row)) {
            $sql = 'SELECT pid FROM ' . $db_config['prefix'] . '_plugin WHERE pid!=' . $pid . ' AND (plugin_lang=' . $db->quote(NV_LANG_DATA) . ' OR plugin_lang=\'all\') AND plugin_area=' . $db->quote($row['plugin_area']) . ' AND hook_module=' . $db->quote($row['hook_module']) . ' ORDER BY weight ASC';
            $result = $db->query($sql);
            $weight = 0;
            while ($row = $result->fetch()) {
                ++$weight;
                if ($weight == $new_weight) {
                    ++$weight;
                }
                $db->query('UPDATE ' . $db_config['prefix'] . '_plugin SET weight=' . $weight . ' WHERE pid=' . $row['pid']);
            }

            $db->query('UPDATE ' . $db_config['prefix'] . '_plugin SET weight=' . $new_weight . ' WHERE pid=' . $pid);

            nv_save_file_config_global();
            nv_htmlOutput('OK');
        }
    }
    nv_htmlOutput('ERROR');
}

// Các plugin chưa được tích hợp
if (!empty($available_plugins)) {
    foreach ($available_plugins as $plugin) {
        $plugin['parea'] = implode(', ', $plugin['area']);
        $plugin['hook_module'] = empty($plugin['hook_module']) ? '' : ($plugin['hook_module'] . ':');

        if (empty($plugin['receive_module'])) {
            $plugin['type'] = $nv_Lang->getModule('plugin_type_sys');
        } else {
            $plugin['type'] = $nv_Lang->getModule('plugin_type_module') . ':' . $plugin['receive_module'];
        }

        $xtpl->assign('ROW', $plugin);

        if (sizeof($plugin['area']) == 1) {
            $xtpl->assign('LINK_INTEGRATE', NV_BASE_ADMINURL . 'index.php?' . NV_LANG_VARIABLE . '=' . NV_LANG_DATA . '&amp;' . NV_NAME_VARIABLE . '=' . $module_name . '&amp;' . NV_OP_VARIABLE . '=' . $op . '&amp;plugin_file=' . $plugin['file'] . '&amp;rand=' . nv_genpass());
            $xtpl->parse('main.available.loop.status_ok');
            $xtpl->parse('main.available.loop.integrate');
        } else {
            $xtpl->parse('main.available.loop.status_error');
        }

        $xtpl->parse('main.available.loop');
    }
    $xtpl->parse('main.available');
}

$xtpl->parse('main');
$contents = $xtpl->text('main');

include NV_ROOTDIR . '/includes/header.php';
echo nv_admin_theme($contents);
include NV_ROOTDIR . '/includes/footer.php';

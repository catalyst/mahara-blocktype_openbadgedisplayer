<?php
/**
 * Mahara: Electronic portfolio, weblog, resume builder and social networking
 * Copyright (C) 2006-2011 Catalyst IT Ltd and others; see:
 *                         http://wiki.mahara.org/Contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    mahara
 * @subpackage blocktype-openbadgedisplayer
 * @author     Discendum Oy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2012 Discedum Oy http://discendum.com
 * @copyright  (C) 2011 Catalyst IT Ltd http://catalyst.net.nz
 *
 */


defined('INTERNAL') || die();

class PluginBlocktypeOpenbadgedisplayer extends SystemBlocktype {

    private static $backpack = 'https://backpack.openbadges.org/';

    public static function single_only() {
        return false;
    }

    public static function get_title() {
        return get_string('title', 'blocktype.openbadgedisplayer');
    }

    public static function get_description() {
        return get_string('description', 'blocktype.openbadgedisplayer');
    }

    public static function get_categories() {
        return array('external');
    }

    public static function get_viewtypes() {
        return array('portfolio', 'profile');
    }

    public static function render_instance(BlockInstance $instance, $editing=false) {
        $configdata = $instance->get('configdata');
        if (empty($configdata) || !isset($configdata['badgegroup'])) {
            return;
        }
        if ($editing) {
            $badgegroup = $configdata['badgegroup'];
            list($bid, $group) = explode(':', $badgegroup);
            $res = mahara_http_request(array(CURLOPT_URL => self::$backpack. "displayer/{$bid}/groups.json"));
            $res = json_decode($res->data);
            if (!empty($res->groups)) {
                foreach ($res->groups AS $g) {
                    if ($badgegroup === $bid . ':' .$g->groupId) {
                        return hsc($g->name) . ' (' . get_string('nbadges', 'blocktype.openbadgedisplayer', $g->badges) . ')';
                    }
                }
            }
            return;
        }

        $smarty = smarty_core();
        $smarty->assign('baseurl', self::$backpack);
        $smarty->assign('id', $instance->get('id'));
        $smarty->assign('badgegroup', $configdata['badgegroup']);
        return $smarty->fetch('blocktype:openbadgedisplayer:openbadgedisplayer.tpl');
    }

    public static function has_instance_config() {
        return true;
    }

    public static function instance_config_form($instance) {
        global $USER;
        $configdata = $instance->get('configdata');

        $addresses = get_column('artefact_internal_profile_email', 'email', 'owner', $USER->id, 'verified', 1);
        $backpackid = array();
        foreach ($addresses AS $address) {
            $res = mahara_http_request(
                array(
                    CURLOPT_URL        => self::$backpack . 'displayer/convert/email',
                    CURLOPT_POST       => 1,
                    CURLOPT_POSTFIELDS => 'email=' . urlencode($address)
                )
            );
            $res = json_decode($res->data);
            if (isset($res->userId)) {
                $backpackid[] = $res->userId;
            }
        }

        if (empty($backpackid)) {
            $profileurl = get_config('wwwroot') . 'artefact/internal/index.php?fs=contact';
            return array(
                'colorcode' => array('type' => 'hidden', 'value' => ''),
                'title' => array('type' => 'hidden', 'value' => ''),
                'message' => array(
                    'type' => 'html',
                    'value' => '<p>'. get_string('nobackpack', 'blocktype.openbadgedisplayer', self::$backpack, $profileurl) .'</p>'
                )
            );
        }

        $opt = array();
        $default = null;
        foreach ($backpackid AS $bid) {
            $res = mahara_http_request(array(CURLOPT_URL => self::$backpack. "displayer/{$bid}/groups.json"));
            $res = json_decode($res->data);
            if (!empty($res->groups)) {
                foreach ($res->groups AS $g) {
                    if (is_null($default)) {
                        $default = $bid . ':' . $g->groupId;
                    }
                    $opt[$bid . ':' .$g->groupId] = hsc($g->name) . ' (' . get_string('nbadges', 'blocktype.openbadgedisplayer', $g->badges) . ')';
                }
            }
        }

        if (empty($opt)) {
            return array(
                'colorcode' => array('type' => 'hidden', 'value' => ''),
                'title' => array('type' => 'hidden', 'value' => ''),
                'message' => array(
                    'type' => 'html',
                    'value' => '<p>'. get_string('nogroups', 'blocktype.openbadgedisplayer', self::$backpack) .'</p>'
                )
            );
        }

        if (isset($configdata['badgegroup']) && isset($opt[$configdata['badgegroup']])) {
            $default = $configdata['badgegroup'];
        }

        return array(
            'message' => array(
                'type' => 'html',
                'value' => '<p>'. get_string('confighelp', 'blocktype.openbadgedisplayer', self::$backpack) .'</p>'
            ),
            'badgegroup' => array(
                'type' => 'radio',
                'options' => $opt,
                'defaultvalue' => $default,
                'separator' => '<br>'
            ),
        );
    }

    public static function instance_config_save($values) {
        unset($values['message']);
        return $values;
    }

    public static function default_copy_type() {
        return 'shallow';
    }

    public static function allowed_in_view(View $view) {
        return $view->get('owner') != null;
    }

    public static function get_instance_javascript() {
        return array(get_config('wwwroot') . 'js/preview.js');
    }

}
